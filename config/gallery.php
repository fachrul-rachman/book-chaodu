<?php

return [
    'storage_disk' => env('GALLERY_STORAGE_DISK', 'r2_gallery'),
    'event_name' => env('GALLERY_EVENT_NAME', 'Chao Du'),
    'event_date' => env('GALLERY_EVENT_DATE'),
    'album_title' => env('GALLERY_ALBUM_TITLE', 'Album Dokumentasi Acara'),
    'preview_cache_seconds' => 300,
    'photo_max_bytes' => 30 * 1024 * 1024,
    'video_max_bytes' => 1024 * 1024 * 1024,
    'single_upload_max_bytes' => 100 * 1024 * 1024,
    'multipart_part_size_bytes' => 10 * 1024 * 1024,
    'upload_url_ttl_minutes' => 15,
    'caption_max_characters' => 200,
    'archive_ttl_hours' => max(1, (int) env('GALLERY_ARCHIVE_TTL_HOURS', 24)),
];
