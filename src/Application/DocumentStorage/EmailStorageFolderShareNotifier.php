<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage;

use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Infrastructure\Mail\MailerInterface;

use function App\Infrastructure\Http\e;

/**
 * Verschickt den Freigabe-Link über die bearbeitbare E-Mail-Vorlage
 * "Ordner-Freigabe". Das Passwort steht bewusst NICHT in der Mail: Link und
 * Passwort sollen den Kunden auf getrennten Wegen erreichen.
 */
final class EmailStorageFolderShareNotifier implements StorageFolderShareNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
    ) {
    }

    public function notify(string $recipientEmail, string $customerName, StorageFolder $folder): void
    {
        $rendered = $this->templateRenderer->render(EmailTemplateKey::StorageFolderShare, [
            'customer_name' => e($customerName),
            'folder_name' => e($folder->name()),
            'link' => e((string) $folder->shareUrl),
            'permission' => e(($folder->sharePermission ?? SharePermission::Read)->label()),
            'password_line' => $folder->shareHasPassword
                ? '<p>Der Ordner ist mit einem Passwort geschützt. Das Passwort erhalten Sie separat von uns.</p>'
                : '',
        ]);

        $attachments = [];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        $this->mailer->send(
            $recipientEmail,
            $rendered->subject,
            $rendered->textBody,
            $attachments,
            $rendered->htmlBody,
            MailCategory::StorageFolderShare,
        );
    }
}
