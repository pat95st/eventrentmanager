<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\CalDav;

use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\CalDav\CalDavSigningCertificateProvider;
use App\Infrastructure\Http\CalDav\MobileConfigBuilder;
use Codeception\Test\Unit;
use ReflectionMethod;
use Tests\Support\Fakes\InMemoryCalDavSigningSettingsRepository;
use Tests\Support\Fakes\InMemoryCompanySettingsRepository;

final class MobileConfigBuilderTest extends Unit
{
    private MobileConfigBuilder $builder;

    protected function _before(): void
    {
        // Kein Zertifikat hinterlegt und noch keins erzeugt worden -> die
        // erste Anfrage lässt CalDavSigningCertificateProvider automatisch
        // ein selbstsigniertes Zertifikat erzeugen (siehe dort), rein aus
        // PHPs eingebauter openssl-Erweiterung, ohne externe Abhängigkeit.
        $provider = new CalDavSigningCertificateProvider(
            new InMemoryCalDavSigningSettingsRepository(),
            new LocalFileStorage(sys_get_temp_dir() . '/materialverleih-tests-' . uniqid('', true)),
            new InMemoryCompanySettingsRepository(),
        );

        $this->builder = new MobileConfigBuilder($provider);
    }

    public function testTheBuiltProfileIsSignedAndWrapsThePlainPlistInAPkcs7DerEnvelope(): void
    {
        $mobileConfig = $this->builder->build(
            organizationName: 'Test GmbH',
            accountDescription: 'CalDAV',
            host: 'materialverleih.example.test',
            port: 443,
            useSsl: true,
            username: 'user@example.test',
            password: 'geheim123',
        );

        $this->assertNotEmpty($mobileConfig);

        // WICHTIG: PKCS#7 "SignedData" SIGNIERT nur, es VERSCHLÜSSELT nicht
        // — der ursprüngliche Plist-Inhalt bleibt Byte für Byte eingebettet
        // (genau deshalb funktioniert `openssl smime -verify -inform DER`,
        // das ihn wieder extrahiert, siehe MobileConfigBuilder-Klassen-
        // docblock). Ein assertStringNotContainsString('<?xml', ...) wäre
        // also FALSCH — geprüft wird stattdessen die DER-Hülle: die Ausgabe
        // beginnt nicht mehr direkt mit dem XML-Prolog, sondern mit der
        // binären ASN.1/DER-SEQUENCE-Tag-Byte-Folge (0x30), und ist durch
        // Zertifikat + Signatur spürbar größer als der reine Plist.
        $this->assertStringStartsNotWith('<?xml', $mobileConfig);
        $this->assertSame("\x30", $mobileConfig[0], 'Ein DER-kodierter PKCS#7-Blob beginnt mit einem SEQUENCE-Tag-Byte (0x30).');

        $buildPlist = new ReflectionMethod(MobileConfigBuilder::class, 'buildPlist');
        $plainPlist = $buildPlist->invoke(
            $this->builder,
            'Test GmbH',
            'CalDAV',
            'materialverleih.example.test',
            443,
            true,
            'user@example.test',
            'geheim123',
        );

        $this->assertGreaterThan(strlen((string) $plainPlist), strlen($mobileConfig));
    }

    public function testEscapesXmlSpecialCharactersInThePlist(): void
    {
        // MobileConfigBuilder ist final und signiert das Profil im
        // Normalfall immer (siehe testTheBuiltProfileIsSignedAndDiffers...
        // oben) — das XML-Escaping selbst sitzt aber in der PRIVATEN
        // buildPlist()-Methode, unabhängig von der Signatur. Statt eines
        // Mocks von CalDavSigningCertificateProvider (nicht möglich, siehe
        // Klassendocblock: final) wird buildPlist() hier direkt per
        // Reflection aufgerufen, um genau diese Escaping-Logik isoliert zu
        // prüfen.
        $provider = new CalDavSigningCertificateProvider(
            new InMemoryCalDavSigningSettingsRepository(),
            new LocalFileStorage(sys_get_temp_dir() . '/materialverleih-tests-' . uniqid('', true)),
            new InMemoryCompanySettingsRepository(),
        );
        $builder = new MobileConfigBuilder($provider);

        $buildPlist = new ReflectionMethod(MobileConfigBuilder::class, 'buildPlist');
        $buildPlist->setAccessible(true);

        $plist = $buildPlist->invoke(
            $builder,
            'Test & Co <GmbH>',
            'CalDAV "Zugang"',
            'materialverleih.example.test',
            443,
            true,
            'a&b@example.test',
            'geheim123',
        );

        $this->assertIsString($plist);
        $this->assertStringContainsString('<?xml', $plist);
        $this->assertStringContainsString('Test &amp; Co &lt;GmbH&gt;', $plist);
        $this->assertStringContainsString('CalDAV &quot;Zugang&quot;', $plist);
        $this->assertStringContainsString('a&amp;b@example.test', $plist);
        $this->assertStringContainsString('<key>CalDAVUseSSL</key>', $plist);
        $this->assertStringContainsString('<true/>', $plist);
        $this->assertStringContainsString('<key>CalDAVAccountAutoDiscover</key>', $plist);
    }
}
