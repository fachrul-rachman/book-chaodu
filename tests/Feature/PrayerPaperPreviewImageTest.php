<?php

declare(strict_types=1);

it('renders public hio preview image as png', function () {
    $response = $this->get(route('prayer-paper-preview.image', [
        'type' => 'B',
        'incense_indonesian' => "INI NAMA INDONESIA\nDAN KELUARGA",
        'index' => 1,
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'image/png');
    expect($response->getContent())->toStartWith("\x89PNG\r\n\x1a\n");
});
