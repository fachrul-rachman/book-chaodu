<?php

namespace App\Http\Requests\Admin;

use App\Enums\PrayerPaperType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdatePrayerPaperMarkingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([PrayerPaperType::A->value, PrayerPaperType::B->value])],
            'template_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5120)],
            'canvas_width' => ['required', 'integer', 'min:1'],
            'canvas_height' => ['required', 'integer', 'min:1'],
            'markers' => ['required', 'array'],
            'markers.single' => ['required', 'array'],
            'markers.left' => ['required', 'array'],
            'markers.right' => ['required', 'array'],
            'markers.single.x' => ['required', 'numeric', 'min:0'],
            'markers.single.y' => ['required', 'numeric', 'min:0'],
            'markers.single.width' => ['required', 'numeric', 'min:1'],
            'markers.single.height' => ['required', 'numeric', 'min:1'],
            'markers.left.x' => ['required', 'numeric', 'min:0'],
            'markers.left.y' => ['required', 'numeric', 'min:0'],
            'markers.left.width' => ['required', 'numeric', 'min:1'],
            'markers.left.height' => ['required', 'numeric', 'min:1'],
            'markers.right.x' => ['required', 'numeric', 'min:0'],
            'markers.right.y' => ['required', 'numeric', 'min:0'],
            'markers.right.width' => ['required', 'numeric', 'min:1'],
            'markers.right.height' => ['required', 'numeric', 'min:1'],
        ];
    }
}
