<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RecognizeMandarinNameRequest extends FormRequest
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
            'source_image' => [
                'required',
                File::image()->types(['jpg', 'jpeg', 'png'])->max(
                    max(1024, (int) config('phase4.ocr_upload_max_mb') * 1024),
                ),
            ],
        ];
    }
}
