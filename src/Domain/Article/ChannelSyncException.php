<?php

declare(strict_types=1);

namespace App\Domain\Article;

/**
 * Wird von den ArticleChannelPublisherInterface-Implementierungen geworfen,
 * wenn die Übermittlung an einen externen Kanal fehlschlägt (fehlende
 * Zugangsdaten, fehlende Pflichtangaben am Artikel, HTTP-Fehler der
 * jeweiligen API). Die Message ist bewusst deutschsprachig und
 * endnutzertauglich, da sie 1:1 in ArticleChannelListing::$lastError und
 * damit in der Oberfläche landet.
 */
final class ChannelSyncException extends \RuntimeException
{
}
