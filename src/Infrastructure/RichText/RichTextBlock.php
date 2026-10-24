<?php

declare(strict_types=1);

namespace App\Infrastructure\RichText;

/**
 * Ein Block-Element eines Rich-Text-Dokuments (ein Absatz, eine Überschrift
 * oder ein Listenpunkt), bestehend aus einer geordneten Liste von
 * RichTextRun-Inline-Formatierungen. Siehe HtmlToRichTextParser (erzeugt
 * diese Blöcke aus dem vom Editor gelieferten HTML) und
 * App\Infrastructure\Pdf\RichTextPdfRenderer (zeichnet sie ins PDF).
 */
final class RichTextBlock
{
    public const TYPE_PARAGRAPH = 'paragraph';
    public const TYPE_HEADING_1 = 'heading1';
    public const TYPE_HEADING_2 = 'heading2';
    public const TYPE_HEADING_3 = 'heading3';
    public const TYPE_BULLET = 'bullet';
    public const TYPE_NUMBERED = 'numbered';

    public const ALIGN_LEFT = 'left';
    public const ALIGN_CENTER = 'center';
    public const ALIGN_RIGHT = 'right';

    /**
     * @param list<RichTextRun> $runs
     * @param int|null $listIndex Fortlaufende Nummer bei TYPE_NUMBERED (1-basiert), sonst null.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $align,
        public readonly array $runs,
        public readonly ?int $listIndex = null,
    ) {
    }
}
