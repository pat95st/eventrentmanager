<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Push\PushSettings;
use App\Domain\Push\PushSettingsRepositoryInterface;
use App\Infrastructure\Push\VapidTokenBuilder;
use RuntimeException;

/**
 * Generiert einmalig (und danach nie wieder, solange bereits ein
 * Schlüsselpaar existiert) das anwendungsweite VAPID-EC-P256-Schlüsselpaar
 * für den Web-Push-Versand (RFC 8292). Wird von
 * PushSettingsController::edit() bei jedem Aufruf der Push-Einstellungsseite
 * dispatcht — beim ERSTEN Mal (push_settings.vapid_public_key noch NULL)
 * wird generiert und gespeichert, bei jedem weiteren Aufruf bleibt das
 * bestehende Schlüsselpaar unverändert (ein Wechsel würde alle bereits
 * registrierten Browser-Subscriptions ungültig machen, siehe Migration 043).
 */
final class EnsureVapidKeysGeneratedHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PushSettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EnsureVapidKeysGenerated);

        $current = $this->settings->get();

        if ($current->hasKeys()) {
            return $current;
        }

        $keyPair = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($keyPair === false) {
            throw new RuntimeException('VAPID-Schlüsselpaar konnte nicht erzeugt werden (ext-openssl mit EC-Unterstützung prüfen).');
        }

        $privateKeyPem = '';
        if (!openssl_pkey_export($keyPair, $privateKeyPem)) {
            throw new RuntimeException('VAPID-Private-Key konnte nicht als PEM exportiert werden.');
        }

        $publicKeyBase64Url = VapidTokenBuilder::publicKeyBase64UrlFromPrivateKeyPem($privateKeyPem);

        $updated = new PushSettings(
            vapidPublicKey: $publicKeyBase64Url,
            vapidPrivateKeyPem: $privateKeyPem,
            subjectEmail: $current->subjectEmail,
        );

        $this->settings->save($updated);

        return $updated;
    }
}
