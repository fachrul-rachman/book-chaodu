<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingRequest extends FormRequest
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
            'package_code' => ['nullable', 'string'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'regex:/^\+62[1-9][0-9]{7,14}$/'],
            'customer_email' => ['required', 'email:rfc,dns', 'max:120'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'sender_name' => ['required', 'string', 'max:120'],
            'transferred_amount' => ['required', 'regex:/^[0-9]+$/'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'referral_source' => ['required', Rule::in([
                'TEMAN',
                'KELUARGA',
                'MEDIA_SOSIAL',
                'WEBSITE',
                'AGENT',
            ])],
            'agent_name' => ['nullable', 'string', 'max:120'],
            'vegetarian_quantity' => ['required', 'integer', 'min:0'],
            'non_vegetarian_quantity' => ['required', 'integer', 'min:0'],
            'replace_table_slot_id' => ['nullable', 'integer', 'exists:table_slots,id'],
            'replace_incense_slot_id' => ['nullable', 'integer', 'exists:incense_slots,id'],
            'deceased_names' => ['nullable', 'array', 'max:2'],
            'deceased_names.*.position' => ['required', 'integer', Rule::in([1, 2])],
            'deceased_names.*.indonesian_name' => ['nullable', 'string', 'max:120'],
            'deceased_names.*.mandarin_name' => ['nullable', 'string', 'max:120'],
            'incense_name' => ['nullable', 'array'],
            'incense_name.position' => ['nullable', 'integer', Rule::in([1])],
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
                $booking = $this->route('booking');

                if (! $booking instanceof Booking) {
                    return;
                }

                if (BookingStatus::from((string) $booking->getRawOriginal('status')) !== BookingStatus::Pending) {
                    $validator->errors()->add('booking', 'Booking ini sudah tidak bisa diubah.');

                    return;
                }

                if ($this->filled('package_code')) {
                    $validator->errors()->add('package_code', 'Paket booking yang sudah masuk tidak bisa diubah.');
                }

                if ($this->input('referral_source') === 'AGENT' && blank($this->input('agent_name'))) {
                    $validator->errors()->add('agent_name', 'Nama agent wajib diisi.');
                }

                $packageCode = PackageCode::from($booking->package_code_snapshot);
                $filledDeceasedNames = 0;

                foreach ($this->input('deceased_names', []) as $name) {
                    if (! is_array($name)) {
                        continue;
                    }

                    if (filled($name['indonesian_name'] ?? null) || filled($name['mandarin_name'] ?? null)) {
                        $filledDeceasedNames++;
                    }
                }

                if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
                    if ($filledDeceasedNames < 1 || $filledDeceasedNames > 2) {
                        $validator->errors()->add('deceased_names', 'Isi 1 atau 2 nama untuk paket ini.');
                    }
                }

                if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
                    $incenseName = $this->input('incense_name', []);

                    if (blank($incenseName['indonesian_name'] ?? null) && blank($incenseName['mandarin_name'] ?? null)) {
                        $validator->errors()->add('incense_name', 'Isi nama untuk hio jumbo.');
                    }
                }

                if ($this->filled('replace_table_slot_id')) {
                    if (! in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
                        $validator->errors()->add('replace_table_slot_id', 'Booking ini tidak memakai nomor meja.');
                    }

                    if (! $booking->tableSlots()->exists()) {
                        $validator->errors()->add('replace_table_slot_id', 'Booking ini belum memiliki nomor meja.');
                    }
                }

                if ($this->filled('replace_incense_slot_id')) {
                    if (! in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
                        $validator->errors()->add('replace_incense_slot_id', 'Booking ini tidak memakai nomor hio.');
                    }

                    if (! $booking->incenseSlots()->exists()) {
                        $validator->errors()->add('replace_incense_slot_id', 'Booking ini belum memiliki nomor hio.');
                    }
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

        $incenseName = $this->input('incense_name');

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => preg_replace('/\s+/', '', (string) $this->input('customer_phone')),
            'customer_email' => strtolower(trim((string) $this->input('customer_email'))),
            'sender_name' => trim((string) $this->input('sender_name')),
            'transferred_amount' => preg_replace('/\D+/', '', (string) $this->input('transferred_amount')),
            'agent_name' => $this->trimNullable('agent_name'),
            'deceased_names' => $deceasedNames,
            'incense_name' => is_array($incenseName)
                ? [
                    'position' => 1,
                    'indonesian_name' => $this->trimNullableFromArray($incenseName, 'indonesian_name'),
                    'mandarin_name' => $this->trimNullableFromArray($incenseName, 'mandarin_name'),
                ]
                : null,
        ]);
    }

    private function trimNullable(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
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
