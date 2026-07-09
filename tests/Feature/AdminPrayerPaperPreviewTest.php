<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Models\User;

it('shows the quick preview page for admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/kertas-doa/cek-cepat')
        ->assertOk()
        ->assertSee('admin\\/prayer-paper-preview\\/index', false)
        ->assertSee('Kertas Doa')
        ->assertSee('Kertas Hio');
});

it('downloads prayer paper preview as png', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get('/admin/kertas-doa/cek-cepat/download?type=A&name_1_indonesian=Tan%20Ah%20Kok&index=1');

    $response->assertOk();
    $response->assertHeader('content-type', 'image/png');
    $response->assertHeader('content-disposition', 'attachment; filename="kertas-doa-1.png"');
    expect($response->getContent())->toStartWith("\x89PNG\r\n\x1a\n");
});

it('downloads hio preview as png', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get('/admin/kertas-doa/cek-cepat/download?type=B&incense_indonesian=Keluarga%20Tan&index=1');

    $response->assertOk();
    $response->assertHeader('content-type', 'image/png');
    $response->assertHeader('content-disposition', 'attachment; filename="kertas-hio.png"');
    expect($response->getContent())->toStartWith("\x89PNG\r\n\x1a\n");
});

it('allows admin to save prayer paper text settings from quick preview page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put('/admin/kertas-doa/cek-cepat/pengaturan-tulisan', [
            'prayer' => [
                'vertical' => [
                    'font_scale' => 0.91,
                    'line_height' => 1.45,
                    'column_gap_scale' => 0.81,
                ],
                'rotated' => [
                    'font_scale' => 0.88,
                ],
            ],
            'incense' => [
                'vertical' => [
                    'font_scale' => 0.93,
                    'line_height' => 1.52,
                    'column_gap_scale' => 0.95,
                ],
                'horizontal' => [
                    'font_scale' => 0.89,
                    'line_height' => 1.33,
                ],
            ],
        ])
        ->assertRedirect();

    expect(AppSetting::query()->where('key', 'prayer_text_incense_horizontal_font_scale')->value('value'))
        ->toBe('0.89')
        ->and(AppSetting::query()->where('key', 'prayer_text_prayer_vertical_line_height')->value('value'))
        ->toBe('1.45');
});
