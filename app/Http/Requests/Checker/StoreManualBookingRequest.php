<?php

namespace App\Http\Requests\Checker;

use App\Enums\PackageCode;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $packageCode = $this->input('package_code');
        $requiresTable = in_array($packageCode, [PackageCode::Prayer->value, PackageCode::Combo->value], true);
        $requiresIncense = in_array($packageCode, [PackageCode::Incense->value, PackageCode::Combo->value], true);

        return [
            'idempotency_key' => ['required', 'string', 'max:120'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone_local' => ['required', 'regex:/^[1-9][0-9]{7,14}$/'],
            'customer_phone' => ['required', 'regex:/^\+62[1-9][0-9]{7,14}$/'],
            'customer_email' => ['required', 'email:rfc', 'max:120'],
            'package_code' => ['required', Rule::enum(PackageCode::class)],
            'table_slot_id' => [Rule::requiredIf($requiresTable), Rule::prohibitedIf(! $requiresTable), 'nullable', 'integer', 'exists:table_slots,id'],
            'incense_slot_id' => [Rule::requiredIf($requiresIncense), Rule::prohibitedIf(! $requiresIncense), 'nullable', 'integer', 'exists:incense_slots,id'],
            'deceased_names' => [Rule::requiredIf($requiresTable), 'nullable', 'array', 'max:2'],
            'deceased_names.*.position' => ['required', 'integer', 'in:1,2'],
            'deceased_names.*.indonesian_name' => ['nullable', 'string', 'max:120'],
            'deceased_names.*.mandarin_name' => ['nullable', 'string', 'max:120'],
            'incense_name' => [Rule::requiredIf($requiresIncense), 'nullable', 'array'],
            'incense_name.position' => ['required_with:incense_name', 'integer', 'in:1'],
            'incense_name.indonesian_name' => ['nullable', 'string', 'max:120'],
            'incense_name.mandarin_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $packageCode = $this->enum('package_code', PackageCode::class);

            if (! $packageCode) {
                return;
            }

            if (! Package::query()->where('code', $packageCode)->where('is_active', true)->exists()) {
                $validator->errors()->add('package_code', 'Paket yang dipilih sedang tidak aktif.');

                return;
            }

            if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
                $deceasedNames = $this->input('deceased_names', []);
                $filledCount = is_array($deceasedNames)
                    ? count(array_filter(
                        $deceasedNames,
                        fn (mixed $name): bool => is_array($name)
                            && (filled($name['indonesian_name'] ?? null) || filled($name['mandarin_name'] ?? null)),
                    ))
                    : 0;

                if ($filledCount < 1 || $filledCount > 2) {
                    $validator->errors()->add('deceased_names', 'Isi 1 atau 2 nama untuk kertas doa.');
                }
            }

            if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
                $name = $this->input('incense_name', []);

                if (blank($name['indonesian_name'] ?? null) && blank($name['mandarin_name'] ?? null)) {
                    $validator->errors()->add('incense_name', 'Isi nama untuk kertas hio.');
                }
            }

            $this->validateCharacters($validator);
        }];
    }

    protected function prepareForValidation(): void
    {
        $localPhone = preg_replace('/\D+/', '', (string) $this->input('customer_phone_local'));
        $deceasedNames = [];

        foreach ($this->input('deceased_names', []) as $name) {
            if (! is_array($name)) {
                continue;
            }

            $deceasedNames[] = [
                'position' => (int) ($name['position'] ?? 0),
                'indonesian_name' => $this->nullableText($name['indonesian_name'] ?? null),
                'mandarin_name' => $this->nullableText($name['mandarin_name'] ?? null),
            ];
        }

        $incenseName = $this->input('incense_name');

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone_local' => $localPhone,
            'customer_phone' => $localPhone !== '' ? '+62'.$localPhone : null,
            'customer_email' => strtolower(trim((string) $this->input('customer_email'))),
            'deceased_names' => $deceasedNames,
            'incense_name' => is_array($incenseName) ? [
                'position' => 1,
                'indonesian_name' => $this->nullableText($incenseName['indonesian_name'] ?? null),
                'mandarin_name' => $this->nullableText($incenseName['mandarin_name'] ?? null),
            ] : null,
        ]);
    }

    private function validateCharacters(Validator $validator): void
    {
        $deceasedNames = $this->input('deceased_names', []);
        $names = is_array($deceasedNames) ? $deceasedNames : [];
        $incenseName = $this->input('incense_name');

        if (is_array($incenseName)) {
            $names[] = $incenseName;
        }

        foreach ($names as $index => $name) {
            if (! is_array($name)) {
                continue;
            }

            $indonesian = (string) ($name['indonesian_name'] ?? '');
            $mandarin = (string) ($name['mandarin_name'] ?? '');

            if (preg_match('/\p{Han}/u', $indonesian) === 1) {
                $validator->errors()->add('names.'.$index, 'Aksara China harus diisi pada kolom Nama Mandarin.');
            }

            if ($mandarin !== '' && preg_match('/^[\p{Han}\s]+$/u', $mandarin) !== 1) {
                $validator->errors()->add('names.'.$index, 'Nama Mandarin hanya boleh memakai aksara China dan baris baru.');
            }
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = preg_replace("/\r\n?/", "\n", trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }
}
