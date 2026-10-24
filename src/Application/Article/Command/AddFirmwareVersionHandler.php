<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersion;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddFirmwareVersionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddFirmwareVersion);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        if (!$article->hasFirmware) {
            throw new ValidationException(['version' => 'Dieser Artikel hat keine aktualisierbare Firmware.']);
        }

        $version = trim($command->version);
        if ($version === '') {
            throw new ValidationException(['version' => 'Bitte eine Versionsbezeichnung angeben.']);
        }

        if (mb_strlen($version) > 100) {
            throw new ValidationException(['version' => 'Die Versionsbezeichnung darf höchstens 100 Zeichen lang sein.']);
        }

        foreach ($this->firmwareVersions->findAllForArticle($article->id) as $existing) {
            if (strcasecmp($existing->version, $version) === 0) {
                throw new ValidationException(['version' => 'Diese Version ist für diesen Artikel bereits hinterlegt.']);
            }
        }

        $releasedAt = $command->releasedAt !== null && trim($command->releasedAt) !== ''
            ? new DateTimeImmutable($command->releasedAt)
            : null;

        $firmwareVersion = new FirmwareVersion(
            id: Uuid::v4(),
            articleId: $article->id,
            version: $version,
            releasedAt: $releasedAt,
            createdAt: new DateTimeImmutable(),
        );

        $this->firmwareVersions->save($firmwareVersion);

        return $firmwareVersion->id;
    }
}
