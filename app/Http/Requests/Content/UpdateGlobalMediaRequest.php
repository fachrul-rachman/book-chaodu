<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['caption' => ['nullable', 'string', 'max:'.config('gallery.caption_max_characters')]];
    }
}
