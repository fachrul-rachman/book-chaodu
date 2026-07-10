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
            'virtual_account_mode' => ['required', 'in:FIXED,POOL'],
            'virtual_account_hold_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'prayer_virtual_account' => ['nullable', 'string', 'max:50'],
            'incense_virtual_account' => ['nullable', 'string', 'max:50'],
            'combo_virtual_account' => ['nullable', 'string', 'max:50'],
            'prayer_virtual_accounts' => ['nullable', 'string'],
            'incense_virtual_accounts' => ['nullable', 'string'],
            'combo_virtual_accounts' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'prayer_virtual_account' => preg_replace('/\D+/', '', (string) $this->input('prayer_virtual_account')) ?: null,
            'incense_virtual_account' => preg_replace('/\D+/', '', (string) $this->input('incense_virtual_account')) ?: null,
            'combo_virtual_account' => preg_replace('/\D+/', '', (string) $this->input('combo_virtual_account')) ?: null,
            'prayer_virtual_accounts' => $this->normalizeList((string) $this->input('prayer_virtual_accounts')),
            'incense_virtual_accounts' => $this->normalizeList((string) $this->input('incense_virtual_accounts')),
            'combo_virtual_accounts' => $this->normalizeList((string) $this->input('combo_virtual_accounts')),
        ]);
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

    private function normalizeList(string $value): ?string
    {
        $normalized = collect(
            preg_split('/\r\n|\r|\n/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        )
            ->map(fn (string $line): string => preg_replace('/\D+/', '', $line) ?? '')
            ->filter()
            ->unique()
            ->implode("\n");

        return $normalized !== '' ? $normalized : null;
    }
}
