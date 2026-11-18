<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Api;

use App\Domain\Api\ApiScope;
use App\Domain\Api\ApiToken;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class ApiScopeTest extends Unit
{
    public function testParseListDropsUnknownValuesAndDuplicatesAndSorts(): void
    {
        $this->assertSame(
            [ApiScope::CustomersRead, ApiScope::InvoicesFinalize],
            ApiScope::parseList(['invoices:finalize', 'nope', 'customers:read', 'customers:read', 42]),
        );
    }

    public function testReadScopes(): void
    {
        foreach (ApiScope::readScopes() as $scope) {
            $this->assertStringEndsWith(':read', $scope->value);
        }
        $this->assertFalse(ApiScope::InvoicesFinalize->isRead());
        $this->assertFalse(ApiScope::EmailSend->isRead());
    }

    public function testEveryScopeHasALabel(): void
    {
        foreach (ApiScope::cases() as $scope) {
            $this->assertNotSame('', $scope->label());
        }
    }

    public function testTokenAllowsOnlyGrantedScopesAndReadOnlyBlocksWrites(): void
    {
        $token = new ApiToken('t', 'T', 'h', 'abcd', null, true, null, new DateTimeImmutable(), [ApiScope::CustomersRead, ApiScope::CustomersWrite]);
        $this->assertTrue($token->allows(ApiScope::CustomersWrite));
        $this->assertFalse($token->allows(ApiScope::ArticlesRead));

        $token->readOnly = true;
        $this->assertTrue($token->allows(ApiScope::CustomersRead));
        $this->assertFalse($token->allows(ApiScope::CustomersWrite));
    }
}
