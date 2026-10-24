<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageAssignmentRepositoryInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\DocumentStorageUploadLogEntry;
use App\Domain\DocumentStorage\DocumentStorageUploadLogRepositoryInterface;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\DocumentStorage\UploadStatus;
use App\Shared\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Zentraler, synchroner Push-Dienst: schiebt ein fertiges Dokument (Bytes,
 * bereits im Speicher) im selben Web-Request, in dem es erzeugt wurde, auf
 * alle für seinen Dokumenttyp aktiven Ziellaufwerke.
 *
 * BEWUSST KEIN CQRS-Command/-Handler: Dieser Dienst wird nicht vom Nutzer
 * direkt über einen Controller ausgelöst, sondern als reine
 * Infrastruktur-Abhängigkeit aus fünf verschiedenen, fachlich völlig
 * unterschiedlichen Command-Handlern heraus aufgerufen (analog zu
 * MailerInterface, das ebenfalls kein Command ist) — ein
 * "PushDocumentToStorage"-Command hätte keinen eigenen fachlichen Sinn,
 * sondern wäre nur ein technischer Wrapper um genau diesen einen
 * Methodenaufruf.
 *
 * FEHLERVERHALTEN (wichtig): Ein fehlgeschlagener Push darf NIEMALS die
 * eigentliche Aktion (Rechnung versenden, Beleg anlegen, …) zum Scheitern
 * bringen — jede einzelne Ziellaufwerk-Übertragung läuft in ihrem eigenen
 * try/catch, ein Fehler bei Ziel A verhindert nicht den Versuch bei Ziel B,
 * und KEINE Exception wird an den Aufrufer durchgereicht. Jeder Versuch
 * (Erfolg wie Fehlschlag) wird in document_storage_upload_log protokolliert
 * und zusätzlich per error_log() ausgegeben, damit ein Fehlschlag trotz des
 * synchronen "Weiterlaufens" sichtbar bleibt (siehe
 * App\Infrastructure\Http\Controllers\DocumentStorageSettingsController für
 * die Admin-Oberfläche auf dieses Protokoll).
 */
final class DocumentStoragePusher
{
    public function __construct(
        private readonly DocumentStorageAssignmentRepositoryInterface $assignments,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly DocumentStorageUploadLogRepositoryInterface $log,
        private readonly StorageClientFactory $clientFactory,
    ) {
    }

    /**
     * @param array<string, string> $extraPlaceholders Zusätzliche/überschreibende
     *        Platzhalter für DocumentStoragePathResolver, i. d. R. mindestens
     *        ['{number}' => '...'] — {filename} und die Datum-/Dokumenttyp-
     *        Platzhalter werden hier bereits automatisch gesetzt.
     * @return array<int, array{targetName: string, success: bool, message: string}>
     *         Ergebnis je versuchtem Ziellaufwerk, NUR zur optionalen
     *         Anzeige einer Zusatz-Flash-Meldung durch den Aufrufer — wird
     *         niemals als Fehler geworfen.
     */
    public function push(
        DocumentType $documentType,
        string $sourceId,
        string $contents,
        string $filename,
        array $extraPlaceholders = [],
    ): array {
        $results = [];

        try {
            $assignments = $this->assignments->findEnabledForDocumentType($documentType);
        } catch (Throwable $e) {
            // Sollte die Zuordnungs-Abfrage selbst fehlschlagen (z. B. DB-
            // Problem), darf auch das die Hauptaktion nicht verhindern.
            error_log('DocumentStoragePusher: Zuordnungen konnten nicht geladen werden: ' . $e->getMessage());

            return $results;
        }

        if ($assignments === []) {
            return $results;
        }

        $now = new DateTimeImmutable();
        $placeholders = array_merge(
            \App\Infrastructure\DocumentStorage\DocumentStoragePathResolver::defaultPlaceholders($documentType, $now),
            ['{filename}' => $filename],
            $extraPlaceholders,
        );

        foreach ($assignments as $assignment) {
            $target = null;
            $remotePath = \App\Infrastructure\DocumentStorage\DocumentStoragePathResolver::resolvePathTemplate($assignment->targetPath, $placeholders);

            try {
                $target = $this->targets->findById($assignment->storageTargetId);
                if ($target === null || !$target->enabled) {
                    continue;
                }

                $client = $this->clientFactory->create($target);
                $client->upload($contents, $remotePath);

                $this->recordResult($documentType, $sourceId, $assignment->id, $target->id, $remotePath, UploadStatus::Success, null);
                $results[] = ['targetName' => $target->name, 'success' => true, 'message' => 'Erfolgreich übertragen.'];
            } catch (Throwable $e) {
                $targetName = $target?->name ?? $assignment->storageTargetId;
                $errorMessage = $e->getMessage();

                error_log("DocumentStoragePusher: Push für {$documentType->label()} (Quelle {$sourceId}) auf Ziellaufwerk \"{$targetName}\" fehlgeschlagen: {$errorMessage}");

                $this->recordResult($documentType, $sourceId, $assignment->id, $target?->id, $remotePath, UploadStatus::Failed, $errorMessage);
                $results[] = ['targetName' => $targetName, 'success' => false, 'message' => $errorMessage];
            }
        }

        return $results;
    }

    private function recordResult(
        DocumentType $documentType,
        string $sourceId,
        ?string $assignmentId,
        ?string $storageTargetId,
        string $remotePath,
        UploadStatus $status,
        ?string $errorMessage,
    ): void {
        try {
            $this->log->save(new DocumentStorageUploadLogEntry(
                id: Uuid::v4(),
                documentType: $documentType,
                sourceId: $sourceId,
                assignmentId: $assignmentId,
                storageTargetId: $storageTargetId,
                remotePath: $remotePath,
                status: $status,
                // MySQL/MariaDB VARCHAR(1000)-Spalte — vorsorglich kürzen,
                // damit eine ungewöhnlich lange Fehlermeldung nicht selbst
                // wieder zu einem (dann wirklich sichtbaren) Fehler führt.
                errorMessage: $errorMessage !== null ? mb_substr($errorMessage, 0, 1000) : null,
                createdAt: new DateTimeImmutable(),
            ));
        } catch (Throwable $e) {
            error_log('DocumentStoragePusher: Protokolleintrag konnte nicht gespeichert werden: ' . $e->getMessage());
        }
    }
}
