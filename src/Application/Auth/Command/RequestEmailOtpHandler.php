<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\Auth\LoginChallenge;
use App\Domain\Auth\LoginChallengeRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Mail\MailerInterface;
use App\Shared\Uuid;
use DateInterval;
use DateTimeImmutable;

final class RequestEmailOtpHandler implements CommandHandlerInterface
{
    private const CODE_VALID_MINUTES = 10;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly LoginChallengeRepositoryInterface $challenges,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RequestEmailOtp);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = new DateTimeImmutable();

        $challenge = new LoginChallenge(
            id: Uuid::v4(),
            userId: $user->id,
            codeHash: password_hash($code, PASSWORD_DEFAULT),
            expiresAt: $now->add(new DateInterval('PT' . self::CODE_VALID_MINUTES . 'M')),
            consumedAt: null,
            createdAt: $now,
        );

        $this->challenges->save($challenge);

        $rendered = $this->templateRenderer->render(EmailTemplateKey::LoginOtp, [
            'code' => $code,
            'valid_minutes' => (string) self::CODE_VALID_MINUTES,
        ]);

        $attachments = $rendered->logoAttachment !== null ? [$rendered->logoAttachment] : [];
        $this->mailer->send($user->email, $rendered->subject, $rendered->textBody, $attachments, $rendered->htmlBody, MailCategory::LoginOtp);

        return null;
    }
}
