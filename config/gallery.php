<?php

return [
    'storage_disk' => env('GALLERY_STORAGE_DISK', 'r2_gallery'),
    'event_name' => env('GALLERY_EVENT_NAME', 'Chao Du'),
    'event_date' => env('GALLERY_EVENT_DATE'),
    'album_title' => env('GALLERY_ALBUM_TITLE', 'Album Dokumentasi Acara'),
    'empty_state_text' => env('GALLERY_EMPTY_STATE_TEXT', 'Dokumentasi acara belum tersedia.'),
    'preview_cache_seconds' => 300,
    'photo_max_bytes' => 30 * 1024 * 1024,
    'video_max_bytes' => 1024 * 1024 * 1024,
    'single_upload_max_bytes' => 100 * 1024 * 1024,
    'multipart_part_size_bytes' => 10 * 1024 * 1024,
    'upload_url_ttl_minutes' => 15,
    'caption_max_characters' => 200,
    'archive_ttl_hours' => max(1, (int) env('GALLERY_ARCHIVE_TTL_HOURS', 24)),
    'ffprobe_binary' => env('GALLERY_FFPROBE_BINARY', 'ffprobe'),
    'ffmpeg_binary' => env('GALLERY_FFMPEG_BINARY', 'ffmpeg'),
    'video_inspection_timeout_seconds' => max(60, (int) env('GALLERY_VIDEO_INSPECTION_TIMEOUT_SECONDS', 1800)),
    'video_thumbnail_timeout_seconds' => max(30, (int) env('GALLERY_VIDEO_THUMBNAIL_TIMEOUT_SECONDS', 300)),
];
