<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\ModalFrame;
use Codeception\Test\Unit;

final class ModalFrameTest extends Unit
{
    public function testRecognisesCreateAndEditPaths(): void
    {
        $this->assertTrue(ModalFrame::isFormPath('/kunden/neu'));
        $this->assertTrue(ModalFrame::isFormPath('/artikel/neues-bundle'));
        $this->assertTrue(ModalFrame::isFormPath('/artikel/42/bearbeiten'));
        $this->assertTrue(ModalFrame::isFormPath('/inventurnummern/7/rma/3/bearbeiten'));

        $this->assertFalse(ModalFrame::isFormPath('/kunden'));
        $this->assertFalse(ModalFrame::isFormPath('/kunden/42'));
        $this->assertFalse(ModalFrame::isFormPath('/artikel/neuheiten'));
        $this->assertFalse(ModalFrame::isFormPath('/erneu'));
    }

    public function testFrameRequestIsMarkedByParameterInQueryOrPost(): void
    {
        $this->assertTrue(ModalFrame::isFrameRequest(['_modal' => '1'], []));
        $this->assertTrue(ModalFrame::isFrameRequest([], ['_modal' => '1']));
        $this->assertFalse(ModalFrame::isFrameRequest(['page' => '2'], ['name' => 'x']));
    }

    public function testRedirectToAFormPageStaysInsideTheFrame(): void
    {
        $this->assertSame('/kunden/5/bearbeiten?_modal=1', ModalFrame::redirectInsideFrame('/kunden/5/bearbeiten'));
        $this->assertSame('/kunden/neu?typ=firma&_modal=1', ModalFrame::redirectInsideFrame('/kunden/neu?typ=firma'));
        $this->assertSame(
            '/kunden/5/bearbeiten?_modal=1#kontakte',
            ModalFrame::redirectInsideFrame('/kunden/5/bearbeiten#kontakte'),
        );
    }

    public function testRedirectToARegularPageLeavesTheFrame(): void
    {
        $this->assertNull(ModalFrame::redirectInsideFrame('/kunden'));
        $this->assertNull(ModalFrame::redirectInsideFrame('/kunden/5?tab=neu'));
        $this->assertNull(ModalFrame::redirectInsideFrame('/login'));
    }

    public function testBreakoutPageEscapesTheTarget(): void
    {
        $html = ModalFrame::breakoutPage('/kunden?x=</script><script>alert(1)//&y="1"');

        $this->assertStringContainsString('data-modal-breakout', $html);
        $this->assertStringNotContainsString('</script><script>alert', $html);
        $this->assertSame(1, substr_count($html, '</script>'));
    }
}
