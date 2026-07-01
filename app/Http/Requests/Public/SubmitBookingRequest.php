<?php

namespace App\Http\Requests\Public;

use App\Enums\PackageCode;
use App\Models\AppSetting;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SubmitBookingRequest extends FormRequest
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
        $uploadMaxKb = $this->uploadMaxKb();
        $ocrUploadMaxKb = max(1024, (int) config('phase4.ocr_upload_max_mb') * 1024);

        return [
            'idempotency_key' => ['required', 'string', 'max:120'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone_local' => ['required', 'regex:/^[1-9][0-9]{7,14}$/'],
            'customer_phone' => ['required', 'regex:/^\+62[1-9][0-9]{7,14}$/'],
            'customer_email' => ['required', 'email:rfc,dns', 'max:120'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'package_code' => ['required', Rule::enum(PackageCode::class)],
            'deceased_names' => ['nullable', 'array', 'max:2'],
            'deceased_names.*.indonesian_name' => ['nullable', 'string', 'max:120'],
            'deceased_names.*.mandarin_name' => ['nullable', 'string', 'max:120'],
            'deceased_names.*.source_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png'])->max($ocrUploadMaxKb)],
            'incense_name' => ['nullable', 'array'],
            'incense_name.indonesian_name' => ['nullable', 'string', 'max:120'],
            'incense_name.mandarin_name' => ['nullable', 'string', 'max:120'],
            'incense_name.source_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png'])->max($ocrUploadMaxKb)],
            'vegetarian_quantity' => ['required', 'integer', 'min:0'],
            'non_vegetarian_quantity' => ['required', 'integer', 'min:0'],
            'sender_name' => ['required', 'string', 'max:120'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'proof' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'pdf'])->max($uploadMaxKb),
            ],
            'referral_source' => ['required', Rule::in([
                'TEMAN',
                'KELUARGA',
                'MEDIA_SOSIAL',
                'WEBSITE',
                'AGENT',
            ])],
            'agent_name' => ['nullable', 'string', 'max:120'],
            'confirmation_checked' => ['accepted'],
            'captcha_token' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $packageCode = $this->enum('package_code', PackageCode::class);

                if (! $packageCode) {
                    return;
                }

                $package = Package::query()
                    ->where('code', $packageCode)
                    ->where('is_active', true)
                    ->first();

                if (! $package) {
                    $validator->errors()->add('package_code', 'Paket yang dipilih sudah tidak tersedia.');

                    return;
                }

                if ($this->input('referral_source') === 'AGENT' && blank($this->input('agent_name'))) {
                    $validator->errors()->add('agent_name', 'Nama agent wajib diisi.');
                }

                $this->validateNames($validator, $packageCode);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $localPhone = preg_replace('/\D+/', '', (string) $this->input('customer_phone_local'));
        $deceasedFiles = $this->file('deceased_names', []);
        $incenseSourceImage = $this->file('incense_name.source_image');
        $normalizedDeceasedNames = [];

        foreach ($this->input('deceased_names', []) as $index => $name) {
            if (! is_array($name)) {
                continue;
            }

            $normalizedDeceasedNames[] = [
                'indonesian_name' => $this->trimNullableFromArray($name, 'indonesian_name'),
                'mandarin_name' => $this->trimNullableFromArray($name, 'mandarin_name'),
                'source_image' => $deceasedFiles[$index]['source_image'] ?? null,
            ];
        }

        $this->merge([
            'customer_name' => $this->trimString('customer_name'),
            'customer_phone_local' => $localPhone,
            'customer_phone' => $localPhone !== '' ? '+62'.$localPhone : null,
            'customer_email' => strtolower(trim((string) $this->input('customer_email'))),
            'sender_name' => $this->trimString('sender_name'),
            'agent_name' => $this->trimNullable('agent_name'),
            'deceased_names' => $normalizedDeceasedNames,
            'incense_name' => [
                'indonesian_name' => $this->trimNullableFromArray($this->input('incense_name', []), 'indonesian_name'),
                'mandarin_name' => $this->trimNullableFromArray($this->input('incense_name', []), 'mandarin_name'),
                'source_image' => $incenseSourceImage,
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_phone_local.regex' => 'Nomor telepon harus dimulai dengan angka 1 sampai 9 dan panjangnya benar.',
            'proof.max' => 'Ukuran bukti transfer melebihi batas.',
            'confirmation_checked.accepted' => 'Silakan centang konfirmasi sebelum kirim.',
        ];
    }

    private function validateNames(Validator $validator, PackageCode $packageCode): void
    {
        $deceasedNames = array_values($this->input('deceased_names', []));
        $incenseName = $this->input('incense_name', []);

        if (in_array($packageCode, [PackageCode::Prayer, PackageCode::Combo], true)) {
            $filledRows = collect($deceasedNames)
                ->filter(fn (array $name): bool => filled($name['indonesian_name'] ?? null) || filled($name['mandarin_name'] ?? null))
                ->values();

            if ($filledRows->count() < 1 || $filledRows->count() > 2) {
                $validator->errors()->add('deceased_names', 'Isi 1 atau 2 nama untuk paket ini.');
            }
        }

        if (in_array($packageCode, [PackageCode::Incense, PackageCode::Combo], true)) {
            if (blank($incenseName['indonesian_name'] ?? null) && blank($incenseName['mandarin_name'] ?? null)) {
                $validator->errors()->add('incense_name', 'Isi nama untuk hio jumbo.');
            }
        }
    }

    private function uploadMaxKb(): int
    {
        $settings = AppSetting::getMany(['upload_max_mb']);

        return max(1024, (int) ($settings['upload_max_mb'] ?? config('phase3.upload_max_mb')) * 1024);
    }

    private function trimString(string $key): string
    {
        return trim((string) $this->input($key));
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
