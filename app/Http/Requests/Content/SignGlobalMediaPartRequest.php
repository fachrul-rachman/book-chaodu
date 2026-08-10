<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class SignGlobalMediaPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['part_number' => ['required', 'integer', 'min:1', 'max:10000']];
    }
}
