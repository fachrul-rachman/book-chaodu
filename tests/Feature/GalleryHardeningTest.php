<?php

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Jobs\BuildGalleryArchive;
use App\Jobs\ProcessGalleryVideo;
use App\Models\GalleryMedia;
use App\Models\User;
use App\Services\GalleryVideoInspector;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-hardening-test');
    Storage::fake('gallery-hardening-test');
});

it('protects every content team endpoint with authentication and the content team role', function () {
    $contentRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'content.'));

    expect($contentRoutes)->not->toBeEmpty();

    foreach ($contentRoutes as $route) {
        expect($route->gatherMiddleware())
            ->toContain('auth')
            ->toContain('role:CONTENT_TEAM');
    }
});

it('adds privacy headers even when a secret album URL is invalid', function () {
    $this->get('/chaodu/TIDAK-ADA')
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('keeps archive building unique and scheduled cleanup non-overlapping', function () {
    $job = new BuildGalleryArchive(91);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('91');

    $cleanup = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'gallery:cleanup-archives'));

    expect($cleanup)->not->toBeNull()
        ->and($cleanup->withoutOverlapping)->toBeTrue();
});

it('accepts only h264 video with optional aac audio metadata', function () {
    $inspector = app(GalleryVideoInspector::class);

    $inspector->assertCompatibleStreams([
        ['codec_type' => 'video', 'codec_name' => 'h264'],
        ['codec_type' => 'audio', 'codec_name' => 'aac'],
    ]);
    $inspector->assertCompatibleStreams([
        ['codec_type' => 'video', 'codec_name' => 'h264'],
    ]);

    expect(fn () => $inspector->assertCompatibleStreams([
        ['codec_type' => 'video', 'codec_name' => 'hevc'],
        ['codec_type' => 'audio', 'codec_name' => 'aac'],
    ]))->toThrow(UnexpectedValueException::class)
        ->and(fn () => $inspector->assertCompatibleStreams([
            ['codec_type' => 'video', 'codec_name' => 'h264'],
            ['codec_type' => 'audio', 'codec_name' => 'mp3'],
        ]))->toThrow(UnexpectedValueException::class);
});

it('publishes a video only after asynchronous codec inspection succeeds', function () {
    $media = hardeningVideo('gallery/global/video/original.mp4');
    Storage::disk('gallery-hardening-test')->put($media->original_path, 'video-compatible');

    $inspector = Mockery::mock(GalleryVideoInspector::class);
    $inspector->shouldReceive('inspect')->once()->withArgs(fn (GalleryMedia $value): bool => $value->is($media));

    (new ProcessGalleryVideo($media->id))->handle($inspector);

    expect($media->refresh()->status)->toBe(GalleryMediaStatus::Ready)
        ->and($media->published_at)->not->toBeNull();
});

it('quarantines and removes an incompatible video original', function () {
    $media = hardeningVideo('gallery/global/video-bad/original.mp4');
    Storage::disk('gallery-hardening-test')->put($media->original_path, 'video-incompatible');

    $inspector = Mockery::mock(GalleryVideoInspector::class);
    $inspector->shouldReceive('inspect')->once()->andThrow(new UnexpectedValueException('codec tidak didukung'));

    (new ProcessGalleryVideo($media->id))->handle($inspector);

    expect($media->refresh()->status)->toBe(GalleryMediaStatus::Failed)
        ->and($media->error_message)->toBe('Format video harus H.264 dengan audio AAC.');
    Storage::disk('gallery-hardening-test')->assertMissing($media->original_path);
});

function hardeningVideo(string $path): GalleryMedia
{
    return GalleryMedia::query()->create([
        'uuid' => (string) str()->uuid(),
        'scope' => GalleryMediaScope::Global,
        'media_type' => GalleryMediaType::Video,
        'status' => GalleryMediaStatus::Processing,
        'storage_disk' => 'gallery-hardening-test',
        'original_path' => $path,
        'original_filename' => 'acara.mp4',
        'stored_filename' => 'original.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'size_bytes' => 16,
        'uploaded_by' => User::factory()->contentTeam()->create()->id,
    ]);
}
