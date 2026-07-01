<?php

it('documents the database queue connection in the environment example', function () {
    $envExample = file_get_contents(base_path('.env.example'));

    expect($envExample)->not->toBeFalse()
        ->and($envExample)->toContain('QUEUE_CONNECTION=database');
});

it('registers the R2 disk with a private s3-compatible driver', function () {
    $disk = config('filesystems.disks.r2');

    expect($disk)->toBeArray()
        ->and($disk['driver'])->toBe('s3')
        ->and($disk['use_path_style_endpoint'])->toBeTrue();
});

it('documents Indonesian locale defaults in the environment example', function () {
    $envExample = file_get_contents(base_path('.env.example'));

    expect($envExample)->not->toBeFalse()
        ->and($envExample)->toContain('APP_LOCALE=id')
        ->and($envExample)->toContain('APP_FALLBACK_LOCALE=id');
});
