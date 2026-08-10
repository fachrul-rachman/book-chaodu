<?php

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use App\Jobs\ProcessGalleryImage;
use App\Models\GalleryMedia;
use App\Models\GalleryMediaDeletion;
use App\Models\User;
use App\Services\GalleryDirectUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('gallery.storage_disk', 'gallery-test');
    config()->set('gallery.single_upload_max_bytes', 100 * 1024 * 1024);
    config()->set('gallery.multipart_part_size_bytes', 10 * 1024 * 1024);
    Storage::fake('gallery-test');
});

function globalGalleryMedia(array $attributes = []): GalleryMedia
{
    return GalleryMedia::query()->create(array_merge([
        'uuid' => (string) str()->uuid(),
        'scope' => GalleryMediaScope::Global,
        'media_type' => GalleryMediaType::Image,
        'status' => GalleryMediaStatus::Ready,
        'storage_disk' => 'gallery-test',
        'original_path' => 'gallery/global/'.str()->uuid().'/original.jpg',
        'original_filename' => 'pembukaan.jpg',
        'stored_filename' => 'original.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size_bytes' => 128,
        'uploaded_by' => User::factory()->contentTeam()->create()->id,
        'published_at' => now(),
    ], $attributes));
}

it('shows the global media workspace only to content team members', function () {
    $contentTeam = User::factory()->contentTeam()->create();
    globalGalleryMedia(['caption' => 'Doa pembukaan']);

    $this->actingAs($contentTeam)
        ->get(route('content.global-media.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('content/global-media/index')
            ->where('limits.photoMb', 30)
            ->where('limits.videoMb', 1024)
            ->has('media', 1)
            ->where('media.0.caption', 'Doa pembukaan'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('content.global-media.index'))
        ->assertForbidden();
});

it('initiates direct single and multipart uploads with trusted metadata', function () {
    $uploader = User::factory()->contentTeam()->create();
    $direct = Mockery::mock(GalleryDirectUploadService::class);
    $direct->shouldReceive('initiate')->twice()->andReturnUsing(
        fn (GalleryMedia $media) => $media->upload_mode === 'SINGLE'
            ? ['mode' => 'single', 'url' => 'https://upload.test/single', 'headers' => ['Content-Type' => $media->mime_type]]
            : ['mode' => 'multipart', 'partSize' => 10 * 1024 * 1024],
    );
    $this->app->instance(GalleryDirectUploadService::class, $direct);

    $this->actingAs($uploader)
        ->postJson(route('content.global-media.uploads.store'), [
            'upload_token' => (string) str()->uuid(),
            'original_filename' => 'pembukaan.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'caption' => 'Pembukaan',
        ])
        ->assertCreated()
        ->assertJsonPath('upload.mode', 'single');

    $this->actingAs($uploader)
        ->postJson(route('content.global-media.uploads.store'), [
            'upload_token' => (string) str()->uuid(),
            'original_filename' => 'acara.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 101 * 1024 * 1024,
        ])
        ->assertCreated()
        ->assertJsonPath('upload.mode', 'multipart');

    expect(GalleryMedia::query()->where('scope', GalleryMediaScope::Global)->count())->toBe(2)
        ->and(GalleryMedia::query()->where('upload_mode', 'MULTIPART')->value('media_type'))->toBe(GalleryMediaType::Video);
});

it('rejects unsupported files, oversized files, and long captions', function (array $payload, string $field) {
    $payload['upload_token'] = (string) str()->uuid();
    $this->actingAs(User::factory()->contentTeam()->create())
        ->postJson(route('content.global-media.uploads.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'pdf' => [[
        'original_filename' => 'dokumen.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
    ], 'mime_type'],
    'extension spoofing' => [[
        'original_filename' => 'dokumen.png', 'mime_type' => 'image/jpeg', 'size_bytes' => 100,
    ], 'original_filename'],
    'photo over 30 MB' => [[
        'original_filename' => 'besar.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 30 * 1024 * 1024 + 1,
    ], 'size_bytes'],
    'video over 1 GB' => [[
        'original_filename' => 'besar.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1024 * 1024 * 1024 + 1,
    ], 'size_bytes'],
    'caption over 200 characters' => [[
        'original_filename' => 'foto.webp', 'mime_type' => 'image/webp', 'size_bytes' => 100,
        'caption' => str_repeat('a', 201),
    ], 'caption'],
]);

it('signs multipart parts and completes a verified mp4 upload', function () {
    $media = globalGalleryMedia([
        'media_type' => GalleryMediaType::Video,
        'status' => GalleryMediaStatus::Processing,
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'stored_filename' => 'original.mp4',
        'original_filename' => 'acara.mp4',
        'size_bytes' => 16,
        'upload_mode' => 'MULTIPART',
        'upload_id' => 'upload-123',
        'upload_expires_at' => now()->addMinutes(15),
        'published_at' => null,
    ]);
    Storage::disk('gallery-test')->put($media->original_path, "\x00\x00\x00\x10ftypisom0000");

    $direct = Mockery::mock(GalleryDirectUploadService::class);
    $direct->shouldReceive('signPart')->once()->withArgs(fn (GalleryMedia $value, int $part) => $value->is($media) && $part === 1)
        ->andReturn(['url' => 'https://upload.test/part-1', 'headers' => []]);
    $direct->shouldReceive('complete')->once();
    $this->app->instance(GalleryDirectUploadService::class, $direct);

    $this->actingAs(User::factory()->contentTeam()->create())
        ->postJson(route('content.global-media.parts.store', $media), ['part_number' => 1])
        ->assertOk()
        ->assertJsonPath('url', 'https://upload.test/part-1');

    $this->postJson(route('content.global-media.uploads.complete', $media), [
        'parts' => [['part_number' => 1, 'etag' => 'etag-1']],
    ])->assertOk()->assertJsonPath('media.status', 'READY');

    expect($media->refresh()->status)->toBe(GalleryMediaStatus::Ready)
        ->and($media->published_at)->not->toBeNull();
});

it('uses the upload token to make repeated initiation idempotent', function () {
    $uploader = User::factory()->contentTeam()->create();
    $token = (string) str()->uuid();
    $direct = Mockery::mock(GalleryDirectUploadService::class);
    $direct->shouldReceive('initiate')->twice()->andReturn(['mode' => 'single', 'url' => 'https://upload.test', 'headers' => []]);
    $this->app->instance(GalleryDirectUploadService::class, $direct);
    $payload = [
        'upload_token' => $token,
        'original_filename' => 'foto.png',
        'mime_type' => 'image/png',
        'size_bytes' => 1024,
    ];

    $first = $this->actingAs($uploader)->postJson(route('content.global-media.uploads.store'), $payload)->assertCreated();
    $second = $this->postJson(route('content.global-media.uploads.store'), $payload)->assertCreated();

    expect($second->json('media.id'))->toBe($first->json('media.id'))
        ->and(GalleryMedia::query()->count())->toBe(1);
});

it('rejects uploaded bytes whose signature does not match the declared type', function () {
    $media = globalGalleryMedia([
        'status' => GalleryMediaStatus::Processing,
        'size_bytes' => 9,
        'upload_mode' => 'SINGLE',
        'upload_expires_at' => now()->addMinutes(15),
        'published_at' => null,
    ]);
    Storage::disk('gallery-test')->put($media->original_path, 'not-image');

    $this->actingAs(User::factory()->contentTeam()->create())
        ->postJson(route('content.global-media.uploads.complete', $media), ['parts' => []])
        ->assertUnprocessable();

    Storage::disk('gallery-test')->assertMissing($media->original_path);
    expect($media->refresh()->status)->toBe(GalleryMediaStatus::Failed);
});

it('treats repeated completion after success as idempotent', function () {
    $media = globalGalleryMedia();

    $this->actingAs(User::factory()->contentTeam()->create())
        ->postJson(route('content.global-media.uploads.complete', $media), ['parts' => []])
        ->assertOk()
        ->assertJsonPath('media.status', 'READY');

    expect(GalleryMedia::query()->count())->toBe(1);
});

it('queues and creates a private thumbnail for a valid photo', function () {
    Queue::fake();
    $bytes = UploadedFile::fake()->image('foto.png', 1200, 800)->getContent();
    $media = globalGalleryMedia([
        'status' => GalleryMediaStatus::Processing,
        'mime_type' => 'image/png',
        'extension' => 'png',
        'stored_filename' => 'original.png',
        'original_filename' => 'foto.png',
        'size_bytes' => strlen($bytes),
        'upload_mode' => 'SINGLE',
        'upload_expires_at' => now()->addMinutes(15),
        'published_at' => null,
    ]);
    Storage::disk('gallery-test')->put($media->original_path, $bytes);

    $this->actingAs(User::factory()->contentTeam()->create())
        ->postJson(route('content.global-media.uploads.complete', $media), ['parts' => []])
        ->assertOk()
        ->assertJsonPath('media.status', 'PROCESSING');

    Queue::assertPushed(ProcessGalleryImage::class, fn (ProcessGalleryImage $job) => $job->mediaId === $media->id);
    (new ProcessGalleryImage($media->id))->handle();

    $media->refresh();
    expect($media->status)->toBe(GalleryMediaStatus::Ready)
        ->and($media->width)->toBe(1200)
        ->and($media->height)->toBe(800)
        ->and($media->thumbnail_path)->toEndWith('/thumbnail.webp');
    Storage::disk('gallery-test')->assertExists($media->thumbnail_path);
});

it('updates captions and manual order for global media', function () {
    $first = globalGalleryMedia();
    $second = globalGalleryMedia();
    $user = User::factory()->contentTeam()->create();

    $this->actingAs($user)
        ->patchJson(route('content.global-media.update', $first), ['caption' => 'Acara pembukaan'])
        ->assertOk();

    $this->putJson(route('content.global-media.order'), [
        'media_ids' => [$second->id, $first->id],
    ])->assertOk();

    expect($first->refresh()->caption)->toBe('Acara pembukaan')
        ->and($first->sort_order)->toBe(2)
        ->and($second->refresh()->sort_order)->toBe(1);
});

it('can hide and republish global media', function () {
    $media = globalGalleryMedia();
    $user = User::factory()->contentTeam()->create();

    $this->actingAs($user)
        ->patchJson(route('content.global-media.status', $media), ['status' => 'HIDDEN'])
        ->assertOk()
        ->assertJsonPath('media.status', 'HIDDEN');

    $this->patchJson(route('content.global-media.status', $media), ['status' => 'READY'])
        ->assertOk()
        ->assertJsonPath('media.status', 'READY');
});

it('blocks non content users from global media mutations', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('content.global-media.uploads.store'), [
            'upload_token' => (string) str()->uuid(),
            'original_filename' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ])
        ->assertForbidden();
});

it('permanently deletes every object and records who deleted the media', function () {
    $media = globalGalleryMedia([
        'preview_path' => 'gallery/global/preview.jpg',
        'thumbnail_path' => 'gallery/global/thumb.jpg',
    ]);
    Storage::disk('gallery-test')->put($media->original_path, 'original');
    Storage::disk('gallery-test')->put($media->preview_path, 'preview');
    Storage::disk('gallery-test')->put($media->thumbnail_path, 'thumb');
    $user = User::factory()->contentTeam()->create();

    $this->actingAs($user)
        ->deleteJson(route('content.global-media.destroy', $media))
        ->assertOk();

    expect(GalleryMedia::query()->find($media->id))->toBeNull()
        ->and(GalleryMediaDeletion::query()->where('media_uuid', $media->uuid)->value('deleted_by'))->toBe($user->id);
    Storage::disk('gallery-test')->assertMissing($media->original_path);
    Storage::disk('gallery-test')->assertMissing($media->preview_path);
    Storage::disk('gallery-test')->assertMissing($media->thumbnail_path);
});
