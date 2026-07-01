<?php

namespace App\Http\Requests\Public;

use App\Enums\PackageCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'package_code' => ['required', Rule::enum(PackageCode::class)],
        ];
    }
}
