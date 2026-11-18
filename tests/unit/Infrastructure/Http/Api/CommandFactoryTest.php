<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api;

use App\Domain\Customer\CustomerType;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Api\CommandFactory;
use Codeception\Test\Unit;
use Tests\Unit\Infrastructure\Http\Api\Fixtures\ObjectCommand;
use Tests\Unit\Infrastructure\Http\Api\Fixtures\SampleCommand;
use Tests\Unit\Infrastructure\Http\Api\Fixtures\StringCommand;
use DateTimeImmutable;

final class CommandFactoryTest extends Unit
{
    public function testBuildsCommandFromSnakeCaseBody(): void
    {
        $command = CommandFactory::create(SampleCommand::class, [
            'name' => 'Erika',
            'quantity' => '3',
            'price' => 4.5,
            'active' => 'true',
            'start_date' => '2026-12-01',
            'type' => 'geschaeftlich',
            'rows' => [['a' => 1]],
        ], ['id' => 'abc']);

        $this->assertSame('abc', $command->id);
        $this->assertSame('Erika', $command->name);
        $this->assertSame(3, $command->quantity);
        $this->assertSame(4.5, $command->price);
        $this->assertTrue($command->active);
        $this->assertEquals(new DateTimeImmutable('2026-12-01'), $command->startDate);
        $this->assertSame(CustomerType::Geschaeftlich, $command->type);
        $this->assertSame([['a' => 1]], $command->rows);
        $this->assertNull($command->note, 'optional parameter falls back to its default');
    }

    public function testScalarsAreCoercedToStringForFormStyleCommands(): void
    {
        $command = CommandFactory::create(SampleCommand::class, ['name' => 42, 'quantity' => 1, 'price' => 1, 'active' => false, 'start_date' => '2026-01-01', 'type' => 'privat', 'rows' => []], ['id' => 'x']);

        $this->assertSame('42', $command->name);
    }

    public function testPatchKeepsCurrentValuesForOmittedFields(): void
    {
        $current = [
            'name' => 'Alt',
            'quantity' => '7', // string in the entity, int in the command
            'price' => 2.0,
            'active' => true,
            'startDate' => new DateTimeImmutable('2026-05-01'),
            'type' => CustomerType::Privat,
            'rows' => [['b' => 2]],
            'note' => 'behalten',
        ];

        $command = CommandFactory::create(SampleCommand::class, ['name' => 'Neu'], ['id' => 'x'], $current);

        $this->assertSame('Neu', $command->name);
        $this->assertSame(7, $command->quantity);
        $this->assertSame('behalten', $command->note);
        $this->assertSame([['b' => 2]], $command->rows);
        $this->assertSame(CustomerType::Privat, $command->type);
    }

    public function testCurrentEnumAndDateValuesAreConvertedForStringParameters(): void
    {
        $command = CommandFactory::create(StringCommand::class, [], [], [
            'type' => CustomerType::Geschaeftlich,
            'startDate' => new DateTimeImmutable('2026-05-01 12:30'),
            'pickupAt' => new DateTimeImmutable('2026-05-01 12:30'),
            'amount' => 12.5,
            'flag' => null,
        ]);

        $this->assertSame('geschaeftlich', $command->type);
        $this->assertSame('2026-05-01', $command->startDate);
        $this->assertSame('2026-05-01T12:30', $command->pickupAt);
        $this->assertSame('12.5', $command->amount);
        $this->assertFalse($command->flag);
    }

    public function testAliasesMapRenamedFields(): void
    {
        $command = CommandFactory::create(StringCommand::class, ['kind' => 'privat', 'start_date' => 'x', 'pickup_at' => 'y', 'amount' => '1', 'flag' => true], [], null, ['kind' => 'type']);

        $this->assertSame('privat', $command->type);
    }

    public function testCollectsAllErrors(): void
    {
        try {
            CommandFactory::create(SampleCommand::class, ['quantity' => 'viele', 'type' => 'unbekannt', 'start_date' => 'kein datum'], ['id' => 'x']);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertSame('Pflichtfeld fehlt.', $errors['name']);
            $this->assertSame('Ganze Zahl erwartet.', $errors['quantity']);
            $this->assertStringContainsString('privat', $errors['type']);
            $this->assertArrayHasKey('start_date', $errors);
        }
    }

    public function testObjectParametersCannotBeSetFromJson(): void
    {
        $this->expectException(ValidationException::class);
        CommandFactory::create(ObjectCommand::class, ['input' => ['a' => 1]]);
    }
}
