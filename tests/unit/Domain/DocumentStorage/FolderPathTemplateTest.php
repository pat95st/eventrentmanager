<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DocumentStorage;

use App\Domain\DocumentStorage\FolderPathTemplate;
use Codeception\Test\Unit;

/** Namensregeln für Veranstaltungs-/Kundenordner: Werte dürfen nie eine zusätzliche Ordnerebene aufmachen. */
final class FolderPathTemplateTest extends Unit
{
    public function testResolvesPlaceholdersIntoNestedPath(): void
    {
        $this->assertSame(
            'Veranstaltungen/2026/2026-06-13 Sommerfest',
            FolderPathTemplate::resolve(FolderPathTemplate::DEFAULT_EVENT, [
                '{year}' => '2026',
                '{date}' => '2026-06-13',
                '{event_name}' => 'Sommerfest',
            ]),
        );
    }

    public function testSlashesAndForbiddenCharactersInValuesDoNotCreateLevels(): void
    {
        $this->assertSame(
            'Kunden/Müller - Söhne GmbH/Gala 24-25- Teil -1-',
            FolderPathTemplate::resolve('Kunden/{customer_name}/{event_name}', [
                '{customer_name}' => 'Müller / Söhne GmbH',
                '{event_name}' => "Gala 24/25: Teil \"1\"\n",
            ]),
        );
    }

    public function testValuesCannotEscapeWithDotSegments(): void
    {
        $this->assertSame('Kunden', FolderPathTemplate::resolve('Kunden/{customer_name}', ['{customer_name}' => '..']));
        $this->assertSame('Kunden/..-etc', FolderPathTemplate::resolve('Kunden/{customer_name}', ['{customer_name}' => '../etc']));
    }

    public function testEmptyLevelsAndTrailingDotsAreDropped(): void
    {
        $this->assertSame(
            'Kunden/Mustermann e.K',
            FolderPathTemplate::resolve('/Kunden//{company}/{customer_name}/', ['{company}' => '', '{customer_name}' => ' Mustermann e.K. ']),
        );
    }

    public function testValidationRejectsUnknownPlaceholdersAndStaticPaths(): void
    {
        $allowed = FolderPathTemplate::EVENT_PLACEHOLDERS;

        $this->assertNull(FolderPathTemplate::validationError(FolderPathTemplate::DEFAULT_EVENT, $allowed));
        $this->assertNull(FolderPathTemplate::validationError(FolderPathTemplate::DEFAULT_CUSTOMER, FolderPathTemplate::CUSTOMER_PLACEHOLDERS));
        $this->assertStringContainsString('{nummer}', (string) FolderPathTemplate::validationError('Events/{nummer}', $allowed));
        $this->assertNotNull(FolderPathTemplate::validationError('Events', $allowed));
        $this->assertNotNull(FolderPathTemplate::validationError('../{event_name}', $allowed));
        $this->assertNotNull(FolderPathTemplate::validationError('  ', $allowed));
        // {event_name} gibt es für Kundenordner nicht.
        $this->assertNotNull(FolderPathTemplate::validationError('Kunden/{event_name}', FolderPathTemplate::CUSTOMER_PLACEHOLDERS));
    }
}
