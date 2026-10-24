<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\CalDav;

use App\Infrastructure\Http\CalDav\CalDavSelfSignedCertificateGenerator;
use Codeception\Test\Unit;

final class CalDavSelfSignedCertificateGeneratorTest extends Unit
{
    public function testGeneratesAMatchingPemCertificateAndPrivateKey(): void
    {
        $pair = CalDavSelfSignedCertificateGenerator::generate('Materialverleih Test GmbH');

        $this->assertNotNull($pair);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $pair['cert']);
        $this->assertStringContainsString('BEGIN', $pair['key']); // "BEGIN PRIVATE KEY" oder "BEGIN RSA PRIVATE KEY" je nach OpenSSL-Version

        $certificate = openssl_x509_read($pair['cert']);
        $privateKey = openssl_pkey_get_private($pair['key']);

        $this->assertNotFalse($certificate);
        $this->assertNotFalse($privateKey);
        $this->assertTrue(openssl_x509_check_private_key($certificate, $privateKey));
    }

    public function testTwoGeneratedCertificatesAreNotIdentical(): void
    {
        $first = CalDavSelfSignedCertificateGenerator::generate('A');
        $second = CalDavSelfSignedCertificateGenerator::generate('B');

        $this->assertNotSame($first['cert'], $second['cert']);
        $this->assertNotSame($first['key'], $second['key']);
    }
}
