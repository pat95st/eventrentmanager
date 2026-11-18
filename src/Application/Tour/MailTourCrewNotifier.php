<?php

declare(strict_types=1);

namespace App\Application\Tour;

use App\Domain\Mail\MailCategory;
use App\Domain\Rental\Rental;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Mail\MailerInterface;

/** Plain e-mail to the technician who is no longer needed at some stops. Errors are swallowed. */
final class MailTourCrewNotifier implements TourCrewNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function notifyRemoved(Rental $tour, string $userId, array $stopLabels, ?string $replacementName): void
    {
        $user = $this->users->findById($userId);
        if ($user === null || trim($user->email) === '') {
            return;
        }
        $text = 'Hallo ' . ($user->firstName ?: $user->displayName()) . ",\n\n"
            . 'bei der Tour „' . $tour->eventName . '“ wirst du an folgenden Stops nicht mehr benötigt:' . "\n"
            . implode("\n", array_map(static fn (string $l) => '- ' . $l, $stopLabels)) . "\n\n"
            . ($replacementName !== null ? 'Vertretung: ' . $replacementName . "\n\n" : '')
            . "Für alle anderen Stops bleibt deine Einplanung bestehen.\n";
        try {
            $this->mailer->send($user->email, 'Änderung deiner Einplanung: ' . $tour->eventName, $text, [], null, MailCategory::TechnicianNotification);
        } catch (\Throwable) {
            // Notification only; the crew change itself is saved.
        }
    }
}
