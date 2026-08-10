<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class ReorderGlobalMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'media_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'media_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
