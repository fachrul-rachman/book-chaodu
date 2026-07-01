<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageImageController extends Controller
{
    public function show(Package $package): StreamedResponse
    {
        abort_if(blank($package->image_path), 404);

        return Storage::disk((string) config('phase1.package_image_disk'))
            ->response($package->image_path);
    }
}
