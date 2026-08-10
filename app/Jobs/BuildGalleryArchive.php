<?php

namespace App\Jobs;

use App\Services\GalleryArchiveBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildGalleryArchive implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $archiveId) {}

    public function handle(GalleryArchiveBuilder $builder): void
    {
        $builder->build($this->archiveId);
    }
}
