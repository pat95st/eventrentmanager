<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cable;

use App\Application\Cable\CableArticleRules;
use App\Application\Cable\CableInput;
use App\Application\Cable\Command\CableSettingsHandler;
use App\Application\Cable\Command\SaveCableType;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryCableRepository;

final class CableSettingsHandlerTest extends Unit
{
    public function testAnalogMulticoreTypeNeverRequiresElectricalInspection(): void
    {
        $cables = new InMemoryCableRepository();
        $command = new SaveCableType(null, 'Analoges Multicore', null, true, null, [], isAnalogMulticore: true);
        $id = (new CableSettingsHandler($cables))->handle($command);

        $type = $cables->findTypeById($id);
        $this->assertTrue($type->isAnalogMulticore);
        $this->assertFalse($type->requiresElectricalInspection);
        $this->assertFalse($type->requiresInspection());

        $rules = new CableArticleRules($this->createMock(ManufacturerRepositoryInterface::class), $cables);
        $this->assertFalse($rules->forcedValues(new CableInput($id, '10', null, null))['requiresElectricalInspection']);
    }

    public function testOrdinaryTypeKeepsInspectionSetting(): void
    {
        $cables = new InMemoryCableRepository();
        $id = (new CableSettingsHandler($cables))->handle(new SaveCableType(null, 'H07RN-F 3G2,5', null, true, null, []));

        $this->assertTrue($cables->findTypeById($id)->requiresInspection());
    }
}
