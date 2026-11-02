<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\RichText\HtmlSanitizer;
use DateTimeImmutable;

final class UpdateCaseInventoryNoticeHandler implements CommandHandlerInterface
{
    private const MAX_LENGTH = 20000;

    public function __construct(
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly HtmlSanitizer $sanitizer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCaseInventoryNotice);

        $caseInventoryNumber = $this->caseInventoryNumbers->findById($command->caseInventoryNumberId);
        if ($caseInventoryNumber === null) {
            throw new NotFoundException("Case-Inventurnummer \"{$command->caseInventoryNumberId}\" wurde nicht gefunden.");
        }

        $html = $this->sanitizer->sanitize($command->noticeHtml);
        if ($html !== null && trim(strip_tags($html)) === '') {
            $html = null; // leerer Editor ("<p></p>", "<p><br></p>")
        }
        if ($html !== null && mb_strlen($html) > self::MAX_LENGTH) {
            throw new ValidationException(['notice' => 'Der Hinweistext ist zu lang.']);
        }

        $caseInventoryNumber->noticeHtml = $html;
        $caseInventoryNumber->updatedAt = new DateTimeImmutable();
        $this->caseInventoryNumbers->save($caseInventoryNumber);

        return null;
    }
}
