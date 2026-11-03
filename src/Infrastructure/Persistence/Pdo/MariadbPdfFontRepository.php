<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\PdfFont;
use App\Domain\Settings\PdfFontRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbPdfFontRepository implements PdfFontRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM pdf_fonts ORDER BY family');

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findById(string $id): ?PdfFont
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pdf_fonts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByFamily(string $family): ?PdfFont
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pdf_fonts WHERE family = :family');
        $stmt->execute(['family' => $family]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(PdfFont $font): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pdf_fonts (id, family, source, regular_file, bold_file, italic_file, bold_italic_file, created_at)
             VALUES (:id, :family, :source, :regular_file, :bold_file, :italic_file, :bold_italic_file, :created_at)
             ON DUPLICATE KEY UPDATE family = VALUES(family), source = VALUES(source), regular_file = VALUES(regular_file),
                bold_file = VALUES(bold_file), italic_file = VALUES(italic_file), bold_italic_file = VALUES(bold_italic_file)'
        );
        $stmt->execute([
            'id' => $font->id,
            'family' => $font->family,
            'source' => $font->source,
            'regular_file' => $font->regularFile,
            'bold_file' => $font->boldFile,
            'italic_file' => $font->italicFile,
            'bold_italic_file' => $font->boldItalicFile,
            'created_at' => $font->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM pdf_fonts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function assignments(): array
    {
        $stmt = $this->pdo->query('SELECT role, font_id FROM pdf_font_assignments WHERE font_id IS NOT NULL');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['role']] = (string) $row['font_id'];
        }

        return $result;
    }

    public function assign(string $role, ?string $fontId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pdf_font_assignments (role, font_id) VALUES (:role, :font_id)
             ON DUPLICATE KEY UPDATE font_id = VALUES(font_id)'
        );
        $stmt->execute(['role' => $role, 'font_id' => $fontId]);
    }

    private function hydrate(array $row): PdfFont
    {
        return new PdfFont(
            id: $row['id'],
            family: $row['family'],
            source: $row['source'],
            regularFile: $row['regular_file'],
            boldFile: $row['bold_file'] ?? null,
            italicFile: $row['italic_file'] ?? null,
            boldItalicFile: $row['bold_italic_file'] ?? null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
