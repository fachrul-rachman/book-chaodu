<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ReserveVirtualAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:120'],
            'package_code' => ['required', 'in:PRAYER,INCENSE,COMBO'],
        ];
    }
}
