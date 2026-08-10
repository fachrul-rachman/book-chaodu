<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class CompleteGlobalMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parts' => ['present', 'array', 'max:10000'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
        ];
    }
}
