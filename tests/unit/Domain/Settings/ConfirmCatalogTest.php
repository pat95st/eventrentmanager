<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Settings;

use App\Application\Settings\Command\UpdateConfirmSettings;
use App\Application\Settings\Command\UpdateConfirmSettingsHandler;
use App\Domain\Settings\ConfirmCatalog;
use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SecuritySettingsRepositoryInterface;
use Codeception\Test\Unit;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the catalog of confirmations that holding Shift may skip: every
 * confirmation in the views needs a key from ConfirmCatalog, otherwise
 * administrators could not configure it under Einstellungen › Sicherheit.
 */
final class ConfirmCatalogTest extends Unit
{
    private const VIEWS = __DIR__ . '/../../../../resources/views';

    /** @return array<string, string> relative path => content */
    private function views(): array
    {
        $views = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::VIEWS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $views[substr($file->getPathname(), strlen(self::VIEWS) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($views);

        return $views;
    }

    /** @return array<string, list<string>> key => views using it */
    private function keysUsedInViews(): array
    {
        $used = [];
        foreach ($this->views() as $path => $content) {
            preg_match_all('/data-confirm-key="([^"<]+)"|confirmGate\(\'([^\']+)\'/', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $used[$match[1] !== '' ? $match[1] : $match[2]][] = $path;
            }
        }

        return $used;
    }

    public function testEveryConfirmFormNamesAKnownKey(): void
    {
        $problems = [];
        foreach ($this->views() as $path => $content) {
            // A form tag; PHP echo tags inside its attribute values may contain ">".
            preg_match_all('/<form\b(?:<\?=.*?\?>|[^>])*>/s', $content, $forms);
            foreach ($forms[0] as $form) {
                if (!str_contains($form, 'data-confirm=')) {
                    continue;
                }
                if (preg_match('/data-confirm-key="([^"]+)"/', $form, $match) !== 1) {
                    $problems[] = $path . ': form with data-confirm but without data-confirm-key';
                } elseif (!ConfirmCatalog::has($match[1])) {
                    $problems[] = $path . ': unknown data-confirm-key "' . $match[1] . '"';
                }
            }
        }

        self::assertSame([], $problems, 'Give each data-confirm form a data-confirm-key and add it to ConfirmCatalog.');
    }

    public function testEveryKeyUsedInViewsIsInTheCatalog(): void
    {
        $unknown = array_values(array_diff(array_keys($this->keysUsedInViews()), ConfirmCatalog::keys()));

        self::assertSame([], $unknown);
    }

    public function testEveryCatalogEntryIsUsed(): void
    {
        $unused = array_values(array_diff(ConfirmCatalog::keys(), array_keys($this->keysUsedInViews())));

        self::assertSame([], $unused, 'Remove catalog entries whose confirmation no longer exists.');
    }

    public function testNoNativeConfirmOnForms(): void
    {
        $offenders = [];
        foreach ($this->views() as $path => $content) {
            if ($path !== 'layout.php' && str_contains($content, 'onsubmit="return confirm')) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders, 'Use data-confirm with data-confirm-key instead of onsubmit="return confirm(...)".');
    }

    public function testDefaultsAndOverrides(): void
    {
        $settings = new SecuritySettings();

        self::assertTrue($settings->confirmBypassEnabled);
        self::assertTrue($settings->isConfirmRequired('invoices.delete'));
        self::assertFalse($settings->isConfirmRequired('customers.delete'));
        self::assertSame(ConfirmCatalog::defaultRequiredKeys(), $settings->requiredConfirmKeys());

        $required = array_values(array_diff(ConfirmCatalog::defaultRequiredKeys(), ['invoices.delete']));
        $required[] = 'customers.delete';
        $required[] = 'no.such.key';
        $settings->setRequiredConfirmKeys($required);

        // Only the deviations from the defaults are kept.
        self::assertSame(['customers.delete' => true, 'invoices.delete' => false], $this->sorted($settings->confirmOverrides));
        self::assertFalse($settings->isConfirmRequired('invoices.delete'));
        self::assertTrue($settings->isConfirmRequired('customers.delete'));
        self::assertTrue($settings->isConfirmRequired('users.delete'));
    }

    public function testHandlerSavesAndResets(): void
    {
        $repository = new class () implements SecuritySettingsRepositoryInterface {
            public SecuritySettings $settings;

            public function __construct()
            {
                $this->settings = new SecuritySettings();
            }

            public function get(): SecuritySettings
            {
                return $this->settings;
            }

            public function save(SecuritySettings $settings): void
            {
                $this->settings = $settings;
            }
        };
        $handler = new UpdateConfirmSettingsHandler($repository);

        $handler->handle(new UpdateConfirmSettings(bypassEnabled: false, requiredKeys: ['customers.delete']));
        self::assertFalse($repository->settings->confirmBypassEnabled);
        self::assertSame(['customers.delete'], $repository->settings->requiredConfirmKeys());

        // Resetting restores the list but leaves the switch alone.
        $handler->handle(new UpdateConfirmSettings(bypassEnabled: true, requiredKeys: [], reset: true));
        self::assertFalse($repository->settings->confirmBypassEnabled);
        self::assertSame([], $repository->settings->confirmOverrides);
        self::assertSame(ConfirmCatalog::defaultRequiredKeys(), $repository->settings->requiredConfirmKeys());
    }

    /**
     * @param array<string, bool> $map
     * @return array<string, bool>
     */
    private function sorted(array $map): array
    {
        ksort($map);

        return $map;
    }
}
