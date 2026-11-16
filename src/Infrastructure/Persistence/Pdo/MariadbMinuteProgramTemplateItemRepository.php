<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\MinuteProgram\MinuteProgramTemplateItem;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTradeNotes;
use PDO;

final class MariadbMinuteProgramTemplateItemRepository implements MinuteProgramTemplateItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(MinuteProgramTemplateItem $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minute_program_template_items (
                id, template_id, sort_order, name, description,
                note_general, trade_notes, duration_minutes, no_strobe
             ) VALUES (
                :id, :template_id, :sort_order, :name, :description,
                :note_general, :trade_notes, :duration_minutes, :no_strobe
             )
             ON DUPLICATE KEY UPDATE
                sort_order = :sort_order2, name = :name2, description = :description2,
                note_general = :note_general2, trade_notes = :trade_notes2, duration_minutes = :duration_minutes2,
                no_strobe = :no_strobe2'
        );

        $stmt->execute([
            'id' => $item->id,
            'template_id' => $item->templateId,
            'sort_order' => $item->sortOrder,
            'name' => $item->name,
            'description' => $item->description,
            'note_general' => $item->noteGeneral,
            'trade_notes' => MinuteProgramTradeNotes::encode($item->tradeNotes),
            'no_strobe' => $item->noStrobe ? 1 : 0,
            'duration_minutes' => $item->durationMinutes,
            'sort_order2' => $item->sortOrder,
            'name2' => $item->name,
            'description2' => $item->description,
            'note_general2' => $item->noteGeneral,
            'trade_notes2' => MinuteProgramTradeNotes::encode($item->tradeNotes),
            'no_strobe2' => $item->noStrobe ? 1 : 0,
            'duration_minutes2' => $item->durationMinutes,
        ]);
    }

    public function findById(string $id): ?MinuteProgramTemplateItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_template_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTemplate(string $templateId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_template_items WHERE template_id = :template_id ORDER BY sort_order ASC');
        $stmt->execute(['template_id' => $templateId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function nextSortOrder(string $templateId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM minute_program_template_items WHERE template_id = :template_id');
        $stmt->execute(['template_id' => $templateId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM minute_program_template_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): MinuteProgramTemplateItem
    {
        return new MinuteProgramTemplateItem(
            id: $row['id'],
            templateId: $row['template_id'],
            sortOrder: (int) $row['sort_order'],
            name: $row['name'],
            description: $row['description'],
            noteGeneral: $row['note_general'],
            tradeNotes: MinuteProgramTradeNotes::decode(isset($row['trade_notes']) ? (string) $row['trade_notes'] : null),
            durationMinutes: $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : null,
            noStrobe: (bool) ($row['no_strobe'] ?? false),
        );
    }
}
