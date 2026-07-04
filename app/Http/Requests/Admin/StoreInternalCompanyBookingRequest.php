<?php

namespace App\Http\Requests\Admin;

use App\Enums\PackageCode;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInternalCompanyBookingRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'regex:/^\+62[1-9][0-9]{7,14}$/'],
            'customer_email' => ['required', 'email:rfc,dns', 'max:120'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'vegetarian_quantity' => ['required', 'integer', 'min:0'],
            'non_vegetarian_quantity' => ['required', 'integer', 'min:0'],
            'deceased_names' => ['required', 'array', 'max:2'],
            'deceased_names.*.position' => ['required', 'integer', 'in:1,2'],
            'deceased_names.*.indonesian_name' => ['nullable', 'string', 'max:120'],
            'deceased_names.*.mandarin_name' => ['nullable', 'string', 'max:120'],
            'incense_name' => ['required', 'array'],
            'incense_name.position' => ['required', 'integer', 'in:1'],
            'incense_name.indonesian_name' => ['nullable', 'string', 'max:120'],
            'incense_name.mandarin_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $package = Package::query()
                    ->where('code', PackageCode::Combo)
                    ->first();

                if (! $package) {
                    $validator->errors()->add('booking', 'Paket internal belum tersedia.');

                    return;
                }

                $filledDeceasedNames = collect($this->input('deceased_names', []))
                    ->filter(fn (array $name): bool => filled($name['indonesian_name'] ?? null) || filled($name['mandarin_name'] ?? null))
                    ->count();

                if ($filledDeceasedNames < 1 || $filledDeceasedNames > 2) {
                    $validator->errors()->add('deceased_names', 'Isi 1 atau 2 nama untuk sembahyang.');
                }

                $incenseName = $this->input('incense_name', []);

                if (blank($incenseName['indonesian_name'] ?? null) && blank($incenseName['mandarin_name'] ?? null)) {
                    $validator->errors()->add('incense_name', 'Isi nama untuk hio.');
                }

                $mealTotal = (int) $this->input('vegetarian_quantity', 0)
                    + (int) $this->input('non_vegetarian_quantity', 0);

                if ($mealTotal > $package->meal_quota) {
                    $validator->errors()->add('vegetarian_quantity', "Total makanan maksimal {$package->meal_quota} porsi.");
                    $validator->errors()->add('non_vegetarian_quantity', "Total makanan maksimal {$package->meal_quota} porsi.");
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $deceasedNames = [];

        foreach ($this->input('deceased_names', []) as $name) {
            if (! is_array($name)) {
                continue;
            }

            $deceasedNames[] = [
                'position' => (int) ($name['position'] ?? 0),
                'indonesian_name' => $this->trimNullableFromArray($name, 'indonesian_name'),
                'mandarin_name' => $this->trimNullableFromArray($name, 'mandarin_name'),
            ];
        }

        $incenseName = $this->input('incense_name', []);

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => preg_replace('/\s+/', '', (string) $this->input('customer_phone')),
            'customer_email' => strtolower(trim((string) $this->input('customer_email'))),
            'deceased_names' => $deceasedNames,
            'incense_name' => [
                'position' => 1,
                'indonesian_name' => $this->trimNullableFromArray($incenseName, 'indonesian_name'),
                'mandarin_name' => $this->trimNullableFromArray($incenseName, 'mandarin_name'),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function trimNullableFromArray(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
