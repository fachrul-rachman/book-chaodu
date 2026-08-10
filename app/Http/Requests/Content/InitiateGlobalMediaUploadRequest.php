<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InitiateGlobalMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'original_filename' => ['required', 'string', 'max:255', 'regex:/^[^\\\\\/\x00-\x1F]+$/u'],
            'upload_token' => ['required', 'uuid'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp,video/mp4'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.config('gallery.video_max_bytes')],
            'caption' => ['nullable', 'string', 'max:'.config('gallery.caption_max_characters')],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mime = (string) $this->input('mime_type');
            $extension = strtolower((string) pathinfo((string) $this->input('original_filename'), PATHINFO_EXTENSION));
            $allowedExtensions = [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
                'video/mp4' => ['mp4'],
            ];

            if (! in_array($extension, $allowedExtensions[$mime] ?? [], true)) {
                $validator->errors()->add('original_filename', 'Ekstensi file tidak sesuai dengan tipe medianya.');
            }

            $limit = str_starts_with($mime, 'image/')
                ? (int) config('gallery.photo_max_bytes')
                : (int) config('gallery.video_max_bytes');

            if ((int) $this->input('size_bytes') > $limit) {
                $validator->errors()->add('size_bytes', str_starts_with($mime, 'image/')
                    ? 'Ukuran foto maksimal 30 MB.'
                    : 'Ukuran video maksimal 1 GB.');
            }
        }];
    }
}
