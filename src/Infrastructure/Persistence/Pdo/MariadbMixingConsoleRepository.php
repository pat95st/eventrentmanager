<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\CompatibleConsole;
use App\Domain\MixingConsole\MixingConsoleChannel;
use App\Domain\MixingConsole\MixingConsoleChannelKind;
use App\Domain\MixingConsole\MixingConsoleExpansionCard;
use App\Domain\MixingConsole\MixingConsoleInterface;
use App\Domain\MixingConsole\MixingConsoleRepositoryInterface;
use App\Domain\MixingConsole\MixingConsoleStagebox;
use App\Domain\MixingConsole\MixingConsoleType;
use App\Domain\MixingConsole\StageboxCompatibility;
use App\Domain\MixingConsole\StageboxOption;
use PDO;

/** Mixing consoles of articles with interfaces, DSP channels, expansion cards and stageboxes (migration 153). */
final class MariadbMixingConsoleRepository implements MixingConsoleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByArticle(string $articleId): ?ArticleMixingConsole
    {
        $row = $this->fetchOne('SELECT * FROM article_mixing_consoles WHERE article_id = :id', $articleId);
        if ($row === null) {
            return null;
        }

        return new ArticleMixingConsole(
            (string) $row['article_id'],
            MixingConsoleType::tryFrom((string) $row['console_type']) ?? MixingConsoleType::Analog,
            micInputs: (int) $row['mic_inputs'],
            stereoInputs: (int) $row['stereo_inputs'],
            comboInputs: (int) $row['combo_inputs'],
            auxPre: (int) $row['aux_pre'],
            auxPost: (int) $row['aux_post'],
            auxSwitchable: (int) $row['aux_switchable'],
            subgroups: (int) $row['subgroups'],
            hasMonoMaster: (bool) $row['has_mono_master'],
            localInputs: (int) $row['local_inputs'],
            localOutputs: (int) $row['local_outputs'],
            expansionSlots: (int) $row['expansion_slots'],
            expansionSlotType: $row['expansion_slot_type'] !== null ? (string) $row['expansion_slot_type'] : null,
            interfaces: array_map(
                static fn (array $r) => new MixingConsoleInterface((string) $r['protocol_id'], (int) $r['quantity']),
                $this->fetchAll('SELECT * FROM article_mixing_console_interfaces WHERE article_id = :id ORDER BY position', $articleId),
            ),
            channels: array_map(
                static fn (array $r) => new MixingConsoleChannel(
                    MixingConsoleChannelKind::tryFrom((string) $r['channel_kind']) ?? MixingConsoleChannelKind::Other,
                    (int) $r['quantity'],
                    (bool) $r['is_stereo'],
                    $r['manufacturer_label'] !== null ? (string) $r['manufacturer_label'] : null,
                ),
                $this->fetchAll('SELECT * FROM article_mixing_console_channels WHERE article_id = :id ORDER BY position', $articleId),
            ),
            cards: array_map(
                static fn (array $r) => new MixingConsoleExpansionCard(
                    (string) $r['name'],
                    $r['protocol_id'] !== null ? (string) $r['protocol_id'] : null,
                    (int) $r['inputs'],
                    (int) $r['outputs'],
                    $r['notes'] !== null ? (string) $r['notes'] : null,
                ),
                $this->fetchAll('SELECT * FROM article_mixing_console_cards WHERE article_id = :id ORDER BY position', $articleId),
            ),
            stageboxes: array_values(array_filter(array_map(
                static function (array $r): ?MixingConsoleStagebox {
                    $compatibility = StageboxCompatibility::tryFrom((string) $r['compatibility']);

                    return $compatibility !== null ? new MixingConsoleStagebox((string) $r['stagebox_article_id'], $compatibility) : null;
                },
                $this->fetchAll('SELECT * FROM article_mixing_console_stageboxes WHERE console_article_id = :id', $articleId),
            ))),
        );
    }

    public function save(ArticleMixingConsole $console): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->pdo->prepare(
                'INSERT INTO article_mixing_consoles (article_id, console_type, mic_inputs, stereo_inputs, combo_inputs, aux_pre, aux_post,
                    aux_switchable, subgroups, has_mono_master, local_inputs, local_outputs, expansion_slots, expansion_slot_type)
                 VALUES (:article_id, :console_type, :mic_inputs, :stereo_inputs, :combo_inputs, :aux_pre, :aux_post,
                    :aux_switchable, :subgroups, :has_mono_master, :local_inputs, :local_outputs, :expansion_slots, :expansion_slot_type)
                 ON DUPLICATE KEY UPDATE console_type = VALUES(console_type), mic_inputs = VALUES(mic_inputs),
                    stereo_inputs = VALUES(stereo_inputs), combo_inputs = VALUES(combo_inputs), aux_pre = VALUES(aux_pre),
                    aux_post = VALUES(aux_post), aux_switchable = VALUES(aux_switchable), subgroups = VALUES(subgroups),
                    has_mono_master = VALUES(has_mono_master), local_inputs = VALUES(local_inputs), local_outputs = VALUES(local_outputs),
                    expansion_slots = VALUES(expansion_slots), expansion_slot_type = VALUES(expansion_slot_type)'
            )->execute([
                'article_id' => $console->articleId,
                'console_type' => $console->type->value,
                'mic_inputs' => $console->micInputs,
                'stereo_inputs' => $console->stereoInputs,
                'combo_inputs' => $console->comboInputs,
                'aux_pre' => $console->auxPre,
                'aux_post' => $console->auxPost,
                'aux_switchable' => $console->auxSwitchable,
                'subgroups' => $console->subgroups,
                'has_mono_master' => $console->hasMonoMaster ? 1 : 0,
                'local_inputs' => $console->localInputs,
                'local_outputs' => $console->localOutputs,
                'expansion_slots' => $console->expansionSlots,
                'expansion_slot_type' => $console->expansionSlotType,
            ]);

            foreach (['article_mixing_console_interfaces', 'article_mixing_console_channels', 'article_mixing_console_cards'] as $table) {
                $this->pdo->prepare("DELETE FROM {$table} WHERE article_id = :id")->execute(['id' => $console->articleId]);
            }
            $this->pdo->prepare('DELETE FROM article_mixing_console_stageboxes WHERE console_article_id = :id')->execute(['id' => $console->articleId]);

            $insert = $this->pdo->prepare('INSERT INTO article_mixing_console_interfaces (article_id, position, protocol_id, quantity) VALUES (?, ?, ?, ?)');
            foreach (array_values($console->interfaces) as $position => $interface) {
                $insert->execute([$console->articleId, $position, $interface->protocolId, $interface->quantity]);
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO article_mixing_console_channels (article_id, position, channel_kind, quantity, is_stereo, manufacturer_label) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach (array_values($console->channels) as $position => $channel) {
                $insert->execute([$console->articleId, $position, $channel->kind->value, $channel->quantity, $channel->isStereo ? 1 : 0, $channel->manufacturerLabel]);
            }
            $insert = $this->pdo->prepare(
                'INSERT INTO article_mixing_console_cards (article_id, position, name, protocol_id, inputs, outputs, notes) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach (array_values($console->cards) as $position => $card) {
                $insert->execute([$console->articleId, $position, $card->name, $card->protocolId, $card->inputs, $card->outputs, $card->notes]);
            }
            $insert = $this->pdo->prepare('INSERT INTO article_mixing_console_stageboxes (console_article_id, stagebox_article_id, compatibility) VALUES (?, ?, ?)');
            foreach ($console->stageboxes as $stagebox) {
                $insert->execute([$console->articleId, $stagebox->stageboxArticleId, $stagebox->compatibility->value]);
            }

            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(string $articleId): void
    {
        // Rows of the child tables are removed by ON DELETE CASCADE.
        $this->pdo->prepare('DELETE FROM article_mixing_consoles WHERE article_id = :id')->execute(['id' => $articleId]);
    }

    public function protocolUsageCounts(): array
    {
        $counts = [];
        $rows = $this->pdo->query(
            'SELECT protocol_id, COUNT(DISTINCT article_id) AS cnt FROM (
                SELECT article_id, protocol_id FROM article_mixing_console_interfaces
                UNION ALL
                SELECT article_id, protocol_id FROM article_mixing_console_cards WHERE protocol_id IS NOT NULL
             ) used GROUP BY protocol_id'
        )->fetchAll();
        foreach ($rows as $row) {
            $counts[(string) $row['protocol_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function findStageboxOptions(): array
    {
        $rows = $this->pdo->query(
            'SELECT a.id, a.name, a.manufacturer_id, m.name AS manufacturer_name, s.protocol_id
             FROM article_digital_stageboxes s
             JOIN articles a ON a.id = s.article_id
             LEFT JOIN manufacturers m ON m.id = a.manufacturer_id
             ORDER BY m.name, a.name'
        )->fetchAll();

        return array_map(static fn (array $r) => new StageboxOption(
            (string) $r['id'],
            (string) $r['name'],
            $r['manufacturer_id'] !== null ? (string) $r['manufacturer_id'] : null,
            $r['manufacturer_name'] !== null ? (string) $r['manufacturer_name'] : null,
            (string) $r['protocol_id'],
        ), $rows);
    }

    public function findConsolesForStagebox(string $stageboxArticleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.name, m.name AS manufacturer_name, cs.compatibility
             FROM article_mixing_console_stageboxes cs
             JOIN articles a ON a.id = cs.console_article_id
             LEFT JOIN manufacturers m ON m.id = a.manufacturer_id
             WHERE cs.stagebox_article_id = :id
             ORDER BY cs.compatibility = \'limited\', m.name, a.name'
        );
        $stmt->execute(['id' => $stageboxArticleId]);

        $result = [];
        foreach ($stmt->fetchAll() as $r) {
            $compatibility = StageboxCompatibility::tryFrom((string) $r['compatibility']);
            if ($compatibility !== null) {
                $result[] = new CompatibleConsole((string) $r['id'], (string) $r['name'], $r['manufacturer_name'] !== null ? (string) $r['manufacturer_name'] : null, $compatibility);
            }
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function fetchOne(string $sql, string $id): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(string $sql, string $id): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
