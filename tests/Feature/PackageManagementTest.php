<?php

declare(strict_types=1);

use App\Enums\PackageCode;
use App\Models\AppSetting;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('seeds the three required packages', function () {
    $this->seed();

    expect(Package::query()->count())->toBe(3)
        ->and(
            Package::query()
                ->get()
                ->map(fn (Package $package): string => $package->code->value)
                ->all(),
        )->toEqualCanonicalizing([
            PackageCode::Prayer->value,
            PackageCode::Incense->value,
            PackageCode::Combo->value,
        ]);
});

it('allows an admin to update a package price, image, and visibility', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $package = Package::factory()->create([
        'price' => null,
        'image_path' => null,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.packages.update', $package), [
            'price' => '1250000',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('paket.jpg'),
        ])
        ->assertRedirect();

    $package->refresh();

    expect($package->price)->toBe('1250000.00')
        ->and($package->is_active)->toBeTrue()
        ->and($package->image_path)->not->toBeNull();
});

it('does not allow a package to be shown without price and photo', function () {
    $admin = User::factory()->admin()->create();
    $package = Package::factory()->create([
        'price' => null,
        'image_path' => null,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.packages.index'))
        ->post(route('admin.packages.update', $package), [
            'price' => '',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.packages.index'))
        ->assertSessionHasErrors('package');

    expect($package->fresh()?->is_active)->toBeFalse();
});

it('allows an admin to update payment information', function () {
    $admin = User::factory()->admin()->create();
    $this->seed(AppSettingSeeder::class);

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'bank_name' => 'BCA',
            'bank_account_holder' => 'PT Chao Du',
            'prayer_virtual_accounts' => "900001\n900002",
            'incense_virtual_accounts' => "910001",
            'combo_virtual_accounts' => "920001",
        ])
        ->assertRedirect();

    expect(AppSetting::getMany([
        'bank_name',
        'bank_account_holder',
    ]))->toMatchArray([
        'bank_name' => 'BCA',
        'bank_account_holder' => 'PT Chao Du',
    ]);
});

it('only returns active packages from the public package data', function () {
    Package::factory()->create([
        'code' => PackageCode::Prayer,
        'name' => 'Sembahyang',
        'is_active' => true,
        'price' => 1000,
    ]);
    Package::factory()->create([
        'code' => PackageCode::Incense,
        'name' => 'Hio',
        'is_active' => false,
        'price' => 2000,
    ]);

    $response = $this->getJson(route('api.public.packages.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.code', PackageCode::Prayer->value);
});

it('blocks checker users from the package page', function () {
    $checker = User::factory()->checker()->create();

    $this->actingAs($checker)
        ->get(route('admin.packages.index'))
        ->assertForbidden();
});
