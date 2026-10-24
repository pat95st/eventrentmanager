<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/** Ergebnis eines einzelnen Push-Versuchs, siehe DocumentStorageUploadLogEntry. */
enum UploadStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
