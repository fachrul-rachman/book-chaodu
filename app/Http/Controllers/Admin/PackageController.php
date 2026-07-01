<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/packages/index', [
            'packages' => Package::query()
                ->orderBy('id')
                ->get()
                ->map(fn (Package $package): array => [
                    'id' => $package->id,
                    'code' => $package->code->value,
                    'name' => $package->name,
                    'description' => $package->description,
                    'price' => $package->price,
                    'image_url' => $package->image_path
                        ? route('packages.image.show', $package)
                        : null,
                    'has_image' => filled($package->image_path),
                    'is_active' => $package->is_active,
                    'meal_quota' => $package->meal_quota,
                    'requires_table' => $package->requires_table,
                    'requires_incense' => $package->requires_incense,
                ])
                ->all(),
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $validated = $request->validated();
        $disk = (string) config('phase1.package_image_disk');

        if ($request->hasFile('image')) {
            $newPath = $request->file('image')->store('package-images', $disk);

            if (! is_string($newPath)) {
                return back()->withErrors([
                    'package' => 'Foto paket tidak berhasil disimpan.',
                ]);
            }

            if ($package->image_path) {
                Storage::disk($disk)->delete($package->image_path);
            }

            $package->image_path = $newPath;
        }

        $nextIsActive = (bool) ($validated['is_active'] ?? false);
        $nextPrice = $validated['price'] ?? $package->price;
        $hasImage = filled($package->image_path);

        if ($nextIsActive && (blank($nextPrice) || ! $hasImage)) {
            return back()->withErrors([
                'package' => 'Paket hanya bisa ditampilkan jika harga dan foto sudah diisi.',
            ]);
        }

        $package->fill([
            'price' => $validated['price'] ?? null,
            'is_active' => $nextIsActive,
        ]);
        $package->save();

        return back()->with('status', 'Paket berhasil diperbarui.');
    }
}
