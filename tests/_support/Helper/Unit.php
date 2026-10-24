<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

/**
 * Platz für gemeinsame Hilfsmethoden der Unit-Suite (aktuell keine nötig —
 * die Unit-Tests bauen ihre Fakes direkt aus tests/_support/Fakes selbst
 * zusammen). Bleibt als Modul bestehen, damit die Suite ohne Änderung an
 * tests/unit.suite.yml um projektspezifische $I->...()-Methoden erweitert
 * werden kann.
 */
class Unit extends \Codeception\Module
{
}
