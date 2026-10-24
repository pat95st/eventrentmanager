<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbAiAdImageSettingsRepository implements AiAdImageSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): AiAdImageSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM ai_ad_image_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('KI-Werbebild-Einstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new AiAdImageSettings(
            openaiApiKey: $row['openai_api_key'],
        );
    }

    public function save(AiAdImageSettings $settings): void
    {
        $stmt = $this->pdo->prepare('UPDATE ai_ad_image_settings SET openai_api_key = :openai_api_key WHERE id = 1');

        $stmt->execute([
            'openai_api_key' => $settings->openaiApiKey,
        ]);
    }
}
