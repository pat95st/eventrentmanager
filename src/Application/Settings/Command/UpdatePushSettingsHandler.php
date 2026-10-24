<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Push\PushSettings;
use App\Domain\Push\PushSettingsRepositoryInterface;

final class UpdatePushSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PushSettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePushSettings);

        $email = strtolower(trim($command->subjectEmail));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['subjectEmail' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        $current = $this->settings->get();

        $updated = new PushSettings(
            vapidPublicKey: $current->vapidPublicKey,
            vapidPrivateKeyPem: $current->vapidPrivateKeyPem,
            // JWT-"sub"-Claim laut RFC 8292 als "mailto:"-URI, siehe VapidTokenBuilder::build().
            subjectEmail: 'mailto:' . $email,
        );

        $this->settings->save($updated);

        return null;
    }
}
