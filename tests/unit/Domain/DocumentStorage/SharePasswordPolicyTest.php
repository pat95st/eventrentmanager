<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DocumentStorage;

use App\Domain\DocumentStorage\SharePasswordPolicy;
use Codeception\Test\Unit;

final class SharePasswordPolicyTest extends Unit
{
    public function testGeneratedPasswordHasConfiguredLengthAndEveryCharacterClass(): void
    {
        $policy = new SharePasswordPolicy(12, true, true, true, true);

        for ($i = 0; $i < 200; $i++) {
            $password = $policy->generate();
            $this->assertSame(12, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
        }
    }

    public function testOnlySelectedCharacterClassesAreUsed(): void
    {
        $policy = new SharePasswordPolicy(20, false, false, true, false);

        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^[2-9]{20}$/', $policy->generate());
        }
    }

    public function testAmbiguousCharactersAreNeverUsed(): void
    {
        $policy = new SharePasswordPolicy(64, true, true, true, false);

        for ($i = 0; $i < 50; $i++) {
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $policy->generate());
        }
    }

    public function testPasswordsDiffer(): void
    {
        $policy = new SharePasswordPolicy();

        $this->assertNotSame($policy->generate(), $policy->generate());
    }

    public function testValidation(): void
    {
        $this->assertSame([], (new SharePasswordPolicy())->validate());
        $this->assertArrayHasKey('sharePasswordLength', (new SharePasswordPolicy(4))->validate());
        $this->assertArrayHasKey('sharePasswordLength', (new SharePasswordPolicy(200))->validate());
        $this->assertArrayHasKey('sharePasswordClasses', (new SharePasswordPolicy(16, false, false, false, false))->validate());
    }
}
