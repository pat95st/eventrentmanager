<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use RuntimeException;

/** Wird von jedem StorageClientInterface-Client bei einem fehlgeschlagenen Upload/Verbindungsversuch geworfen. */
final class StorageUploadException extends RuntimeException
{
}
