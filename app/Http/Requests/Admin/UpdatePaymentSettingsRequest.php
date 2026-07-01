<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'prayer_virtual_accounts' => ['nullable', 'string'],
            'incense_virtual_accounts' => ['nullable', 'string'],
            'combo_virtual_accounts' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function virtualAccountNumbers(string $key): array
    {
        $raw = preg_split('/\r\n|\r|\n/', (string) $this->input($key), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn (string $line): string => preg_replace('/\D+/', '', $line) ?? '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
