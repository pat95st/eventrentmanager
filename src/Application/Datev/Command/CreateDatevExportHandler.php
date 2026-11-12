<?php

declare(strict_types=1);

namespace App\Application\Datev\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Datev\DatevExportBuilder;
use App\Application\Datev\DatevExportPackage;
use App\Domain\Datev\DatevExport;
use App\Domain\Datev\DatevExportRepositoryInterface;
use App\Domain\Datev\DatevExportTrigger;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\MailCategory;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

use function App\Infrastructure\Http\e;

final class CreateDatevExportHandler implements CommandHandlerInterface
{
    /** Größere Anhänge lehnen viele Mailserver ab — dann bitte herunterladen. */
    private const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly DatevExportBuilder $builder,
        private readonly DatevSettingsRepositoryInterface $settings,
        private readonly DatevExportRepositoryInterface $exports,
        private readonly MailerInterface $mailer,
        private readonly CompanySettingsRepositoryInterface $companySettings,
    ) {
    }

    public function handle(CommandInterface $command): DatevExportPackage
    {
        assert($command instanceof CreateDatevExport);

        $settings = $this->settings->get();
        $recipient = null;

        if ($command->trigger !== DatevExportTrigger::Download) {
            $recipient = $settings->advisorEmail;
            if ($recipient === null || $recipient === '') {
                throw new ValidationException(['datev_advisor_email' => 'Bitte zuerst die E-Mail-Adresse des Steuerberaters hinterlegen.']);
            }
        }

        $package = $this->builder->build($command->from, $command->to);

        if ($recipient !== null) {
            if (strlen($package->zipContent) > self::MAX_ATTACHMENT_BYTES) {
                throw new ValidationException(['datev_period' => 'Das DATEV-Paket ist mit ' . round(strlen($package->zipContent) / 1024 / 1024, 1) . ' MB zu groß für den E-Mail-Versand. Bitte einen kürzeren Zeitraum wählen oder das Paket herunterladen.']);
            }
            $this->send($recipient, $package);
        }

        $this->exports->save(new DatevExport(
            id: Uuid::v4(),
            periodFrom: $package->from,
            periodTo: $package->to,
            trigger: $command->trigger,
            recipient: $recipient,
            bookingCount: $package->bookingCount(),
            documentCount: $package->documentCount,
            createdAt: new DateTimeImmutable(),
        ));

        return $package;
    }

    private function send(string $recipient, DatevExportPackage $package): void
    {
        $company = trim((string) ($this->companySettings->get()->name ?? ''));
        $period = $package->from->format('d.m.Y') . ' – ' . $package->to->format('d.m.Y');
        $subject = 'DATEV-Export ' . $period . ($company !== '' ? ' — ' . $company : '');

        $lines = [
            'Guten Tag,',
            '',
            'anbei erhalten Sie den DATEV-Export für den Zeitraum ' . $period . '.',
            '',
            'Ausgangsrechnungen: ' . $package->invoiceCount,
            'Rechnungskorrekturen/Stornos: ' . $package->correctionCount,
            'Eingangsbelege: ' . $package->receiptCount,
            'Belegdateien: ' . $package->documentCount,
            '',
            'Die ZIP-Datei enthält den Buchungsstapel im DATEV-Format (EXTF, CSV) zum Import in DATEV',
            'sowie die zugehörigen Belege. Details und Kontenzuordnung stehen in der Datei Hinweise.txt.',
        ];
        if ($package->warnings !== []) {
            $lines[] = '';
            $lines[] = 'Bitte prüfen:';
            foreach ($package->warnings as $warning) {
                $lines[] = '- ' . $warning;
            }
        }
        $lines[] = '';
        $lines[] = 'Mit freundlichen Grüßen';
        if ($company !== '') {
            $lines[] = $company;
        }

        $text = implode("\n", $lines);
        $html = '<p>' . implode('<br>', array_map(static fn (string $line) => e($line), $lines)) . '</p>';

        $this->mailer->send(
            $recipient,
            $subject,
            $text,
            [new MailAttachment($package->filename, 'application/zip', $package->zipContent)],
            $html,
            MailCategory::DatevExport,
        );
    }
}
