<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrayerPaperTextSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'prayer.vertical.font_scale' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'prayer.vertical.line_height' => ['required', 'numeric', 'min:0.5', 'max:3'],
            'prayer.vertical.column_gap_scale' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'prayer.rotated.font_scale' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'incense.vertical.font_scale' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'incense.vertical.line_height' => ['required', 'numeric', 'min:0.5', 'max:3'],
            'incense.vertical.column_gap_scale' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'incense.horizontal.font_scale' => ['required', 'numeric', 'min:0.1', 'max:3'],
            'incense.horizontal.line_height' => ['required', 'numeric', 'min:0.5', 'max:3'],
        ];
    }
}
