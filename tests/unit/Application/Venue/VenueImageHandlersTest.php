<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Venue;

use App\Application\Venue\Command\AddVenueImage;
use App\Application\Venue\Command\AddVenueImageHandler;
use App\Application\Venue\Command\DeleteVenue;
use App\Application\Venue\Command\DeleteVenueHandler;
use App\Application\Venue\Command\DeleteVenueImage;
use App\Application\Venue\Command\DeleteVenueImageHandler;
use App\Application\Venue\Command\UpdateVenueImage;
use App\Application\Venue\Command\UpdateVenueImageHandler;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\Venue;
use App\Domain\Venue\VenueImage;
use App\Infrastructure\FileStorage\LocalFileStorage;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryVenueImageRepository;
use Tests\Support\Fakes\InMemoryVenueRepository;

final class VenueImageHandlersTest extends Unit
{
    private string $baseDirectory;
    private LocalFileStorage $storage;
    private InMemoryVenueRepository $venues;
    private InMemoryVenueImageRepository $images;

    protected function _before(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . '/venue-image-test-' . bin2hex(random_bytes(4));
        mkdir($this->baseDirectory . '/venues/venue-1', 0777, true);
        $this->storage = new LocalFileStorage($this->baseDirectory);

        $this->venues = new InMemoryVenueRepository();
        $this->venues->save($this->venue('venue-1'));
        $this->venues->save($this->venue('venue-2'));
        $this->images = new InMemoryVenueImageRepository();
    }

    protected function _after(): void
    {
        foreach (glob($this->baseDirectory . '/venues/venue-1/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->baseDirectory . '/venues/venue-1');
        @rmdir($this->baseDirectory . '/venues');
        @rmdir($this->baseDirectory);
    }

    public function testUpdatesDescriptionTrimmedAndClearsEmptyOne(): void
    {
        $this->images->save($this->image('img-1', 'venue-1', 'Alt'));
        $handler = new UpdateVenueImageHandler($this->images);

        $handler->handle(new UpdateVenueImage('img-1', 'venue-1', "  Zufahrt über den Hof\r\nLadezone links  "));
        $this->assertSame("Zufahrt über den Hof\nLadezone links", $this->images->findById('img-1')?->description);

        $handler->handle(new UpdateVenueImage('img-1', 'venue-1', '   '));
        $this->assertNull($this->images->findById('img-1')?->description);
    }

    public function testRejectsTooLongDescription(): void
    {
        $this->images->save($this->image('img-1', 'venue-1', 'Alt'));

        $this->expectException(ValidationException::class);
        (new UpdateVenueImageHandler($this->images))->handle(new UpdateVenueImage('img-1', 'venue-1', str_repeat('x', VenueImage::MAX_DESCRIPTION_LENGTH + 1)));
    }

    public function testUpdateRejectsImageOfOtherVenue(): void
    {
        $this->images->save($this->image('img-1', 'venue-1', null));

        $this->expectException(NotFoundException::class);
        (new UpdateVenueImageHandler($this->images))->handle(new UpdateVenueImage('img-1', 'venue-2', 'Text'));
    }

    public function testAddRejectsUnsupportedFileType(): void
    {
        $handler = new AddVenueImageHandler($this->venues, $this->images, $this->storage);

        try {
            $handler->handle(new AddVenueImage('venue-1', $this->upload('plan.pdf', 'application/pdf'), 'Plan'));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('image', $e->errors());
        }
        $this->assertSame([], $this->images->findByVenue('venue-1'));
    }

    public function testAddRejectsUnknownVenue(): void
    {
        $this->expectException(NotFoundException::class);
        (new AddVenueImageHandler($this->venues, $this->images, $this->storage))
            ->handle(new AddVenueImage('missing', $this->upload('a.jpg', 'image/jpeg'), null));
    }

    public function testDeletesRecordAndStoredFile(): void
    {
        $filePath = $this->baseDirectory . '/venues/venue-1/img-1.jpg';
        file_put_contents($filePath, 'jpg');
        $this->images->save($this->image('img-1', 'venue-1', 'Bühne'));

        (new DeleteVenueImageHandler($this->images, $this->storage))->handle(new DeleteVenueImage('img-1', 'venue-1'));

        $this->assertNull($this->images->findById('img-1'));
        $this->assertFileDoesNotExist($filePath);
    }

    public function testDeleteRejectsImageOfOtherVenue(): void
    {
        $this->images->save($this->image('img-1', 'venue-1', null));

        $this->expectException(NotFoundException::class);
        (new DeleteVenueImageHandler($this->images, $this->storage))->handle(new DeleteVenueImage('img-1', 'venue-2'));
    }

    public function testDeletingVenueRemovesImageFiles(): void
    {
        $filePath = $this->baseDirectory . '/venues/venue-1/img-1.jpg';
        file_put_contents($filePath, 'jpg');
        $this->images->save($this->image('img-1', 'venue-1', null));

        (new DeleteVenueHandler($this->venues, $this->images, $this->storage))->handle(new DeleteVenue('venue-1'));

        $this->assertNull($this->venues->findById('venue-1'));
        $this->assertFileDoesNotExist($filePath);
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private function upload(string $name, string $type): array
    {
        return ['name' => $name, 'type' => $type, 'tmp_name' => '/nonexistent', 'error' => UPLOAD_ERR_OK, 'size' => 100];
    }

    private function image(string $id, string $venueId, ?string $description): VenueImage
    {
        return new VenueImage($id, $venueId, $venueId . '/' . $id . '.jpg', $description, 0, new DateTimeImmutable('2026-01-01 12:00:00'));
    }

    private function venue(string $id): Venue
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        return new Venue($id, 'Halle ' . $id, 'Hauptstr. 1', '12345', 'Teststadt', null, null, null, null, null, null, null, $now, $now);
    }
}
