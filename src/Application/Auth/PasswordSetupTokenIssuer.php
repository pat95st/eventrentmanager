<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\Auth\PasswordSetupToken;
use App\Domain\Auth\PasswordSetupTokenRepositoryInterface;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\User\User;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Mail\MailerInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

use function App\Infrastructure\Http\e;

/**
 * Gemeinsame Logik zum Ausstellen und Versenden eines
 * "Passwort festlegen"-Links, genutzt von zwei fachlich verschiedenen, aber
 * technisch identischen Einstiegspunkten (siehe CreateUserHandler für die
 * Admin-Einladung ohne Erstpasswort, RequestPasswordSetupHandler für
 * "Passwort vergessen") — bewusst als schlanker Application-Service statt
 * eines eigenen Command/Handler-Paars, da hier nichts "kommandiert" wird,
 * das nicht bereits ein umgebender Handler entscheidet.
 *
 * Kein Rate-Limiting über mehrere Anfragen hinweg: für dieses interne Tool
 * ist das unverhältnismäßig (keine öffentlich erreichbare Registrierung,
 * nur von Administratoren angelegte bzw. bereits bekannte Konten). Um
 * trotzdem nicht mehrere gleichzeitig gültige Links entstehen zu lassen,
 * werden bestehende, noch nicht verwendete Tokens des Benutzers bei jeder
 * neuen Ausstellung invalidiert (siehe invalidateAllForUser()) — der zuletzt
 * versendete Link ist immer der einzig gültige.
 */
final class PasswordSetupTokenIssuer
{
    private const TOKEN_BYTES = 32;
    private const EXPIRY_HOURS = 48;

    public function __construct(
        private readonly PasswordSetupTokenRepositoryInterface $tokens,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
    ) {
    }

    /**
     * Erstellt ein neues Token und versendet den Link per E-Mail.
     * Fängt einen Mailer-Fehlschlag ab und meldet ihn nur als
     * Rückgabewert — der Aufrufer entscheidet, wie er das dem
     * Administrator/Benutzer meldet (z. B. Flash-Warnung), verliert dabei
     * aber nie den bereits angelegten Benutzer bzw. die bereits erfolgte
     * Anfrage.
     */
    public function issueAndSend(User $user, string $originUrl, bool $isInvite): bool
    {
        $this->tokens->invalidateAllForUser($user->id);

        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTES));
        $now = new DateTimeImmutable();

        $token = new PasswordSetupToken(
            id: Uuid::v4(),
            userId: $user->id,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: $now->modify('+' . self::EXPIRY_HOURS . ' hours'),
            usedAt: null,
            createdAt: $now,
        );

        $this->tokens->save($token);

        $link = rtrim($originUrl, '/') . '/passwort-einrichten/' . $rawToken;

        $templateKey = $isInvite ? EmailTemplateKey::PasswordSetupInvite : EmailTemplateKey::PasswordReset;
        $mailCategory = $isInvite ? MailCategory::PasswordSetupInvite : MailCategory::PasswordReset;

        $rendered = $this->templateRenderer->render($templateKey, [
            // Serverseitig gebaut (keine Benutzereingabe), wird aber wie
            // jeder andere Platzhalterwert im HTML-Body eingesetzt und
            // daher konsistent escaped.
            'link' => e($link),
            'valid_hours' => (string) self::EXPIRY_HOURS,
        ]);

        try {
            $attachments = $rendered->logoAttachment !== null ? [$rendered->logoAttachment] : [];
            $this->mailer->send($user->email, $rendered->subject, $rendered->textBody, $attachments, $rendered->htmlBody, $mailCategory);

            return true;
        } catch (MailerException) {
            return false;
        }
    }
}
