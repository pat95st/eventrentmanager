<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;

/** Abstraktion über StorageClientFactory, damit Handler ohne echte Netzwerkverbindung testbar sind. */
interface StorageClientFactoryInterface
{
    public function create(DocumentStorageTarget $target): StorageClientInterface;

    /** null, wenn das Protokoll des Ziellaufwerks keine Freigaben kennt (alles außer Nextcloud/ownCloud). */
    public function createShareClient(DocumentStorageTarget $target): ?ShareClientInterface;
}
