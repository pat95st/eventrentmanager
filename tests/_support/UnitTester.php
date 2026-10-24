<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Der Unit-Test-Akteur. Die tatsächlich verfügbaren Methoden kommen von den
 * in tests/unit.suite.yml aktivierten Modulen (Asserts, Helper\Unit) und
 * werden von `vendor/bin/codecept build` automatisch als Trait in
 * tests/_support/_generated/UnitTesterActions.php erzeugt (nicht
 * eingecheckt, siehe .gitignore — läuft automatisch bei `composer install`
 * über den post-install-cmd-Hook in composer.json).
 */
class UnitTester extends \Codeception\Actor
{
    use _generated\UnitTesterActions;
}
