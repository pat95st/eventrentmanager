<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;

final class GetMinuteProgramTemplateDetailHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramTemplateRepositoryInterface $templates,
        private readonly MinuteProgramTemplateItemRepositoryInterface $items,
    ) {
    }

    public function handle(QueryInterface $query): MinuteProgramTemplateDetail
    {
        assert($query instanceof GetMinuteProgramTemplateDetail);

        $template = $this->templates->findById($query->templateId);
        if ($template === null) {
            throw new NotFoundException("Vorlage \"{$query->templateId}\" wurde nicht gefunden.");
        }

        return new MinuteProgramTemplateDetail($template, $this->items->findByTemplate($template->id));
    }
}
