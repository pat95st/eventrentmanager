<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\View;
use Codeception\Test\Unit;

final class ViewTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/view-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/test', 0777, true);
        file_put_contents($this->dir . '/test/vars.php', '<?= $view ?>|<?= $path ?>|<?= $data ?>');
        View::setViewsPath($this->dir);
    }

    protected function _after(): void
    {
        unlink($this->dir . '/test/vars.php');
        rmdir($this->dir . '/test');
        rmdir($this->dir);
        View::setViewsPath(dirname(__DIR__, 4) . '/resources/views');
    }

    public function testDataKeysAreNotShadowedByRendererLocals(): void
    {
        // Regression: the calendar passes 'view' => 'woche'; it used to be
        // replaced by the template name, so prev/next jumped to the month view.
        $html = View::render('test/vars', ['view' => 'woche', 'path' => 'p', 'data' => 'd']);

        $this->assertSame('woche|p|d', $html);
    }
}
