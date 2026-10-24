<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Der Acceptance-Test-Akteur. Die tatsächlich verfügbaren Methoden kommen
 * von den in tests/acceptance.suite.yml aktivierten Modulen (PhpBrowser, Db,
 * Helper\Acceptance) und werden von `vendor/bin/codecept build` automatisch
 * als Trait in tests/_support/_generated/AcceptanceTesterActions.php erzeugt
 * (nicht eingecheckt, siehe .gitignore — läuft automatisch bei
 * `composer install` über den post-install-cmd-Hook in composer.json).
 */
class AcceptanceTester extends \Codeception\Actor
{
    use _generated\AcceptanceTesterActions;
}
