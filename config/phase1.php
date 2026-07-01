<?php

return [
    'package_image_disk' => env('PACKAGE_IMAGE_DISK', 'public'),
    'package_image_max_kb' => (int) env('PACKAGE_IMAGE_MAX_KB', 2048),
];
