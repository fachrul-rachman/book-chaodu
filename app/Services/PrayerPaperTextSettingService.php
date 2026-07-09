<?php

namespace App\Services;

use App\Models\AppSetting;

class PrayerPaperTextSettingService
{
    /**
     * @var array<string, string|null>|null
     */
    private ?array $cachedSettings = null;

    /**
     * @return array<string, array<string, array<string, float>>>
     */
    public function values(): array
    {
        $defaults = config('prayer_paper_text');
        $settings = $this->rawSettings();
        $values = [];

        foreach ($this->settingMap() as $key => $path) {
            $default = (float) config('prayer_paper_text.'.$path);
            $stored = $settings[$key];
            data_set(
                $values,
                $path,
                is_numeric($stored) ? (float) $stored : $default,
            );
        }

        return array_replace_recursive(is_array($defaults) ? $defaults : [], $values);
    }

    public function value(string $group, string $style, string $key, float $default): float
    {
        $settingKey = $this->settingKey($group, $style, $key);
        $stored = $this->rawSettings()[$settingKey] ?? null;

        return is_numeric($stored) ? (float) $stored : $default;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(array $validated): void
    {
        $payload = [];

        foreach ($this->settingMap() as $settingKey => $path) {
            $value = data_get($validated, $path);
            $payload[$settingKey] = $value === null ? null : (string) $value;
        }

        AppSetting::putMany($payload);
        $this->cachedSettings = null;
    }

    /**
     * @return array<string, string>
     */
    private function settingMap(): array
    {
        return [
            'prayer_text_prayer_vertical_font_scale' => 'prayer.vertical.font_scale',
            'prayer_text_prayer_vertical_line_height' => 'prayer.vertical.line_height',
            'prayer_text_prayer_vertical_column_gap_scale' => 'prayer.vertical.column_gap_scale',
            'prayer_text_prayer_rotated_font_scale' => 'prayer.rotated.font_scale',
            'prayer_text_incense_vertical_font_scale' => 'incense.vertical.font_scale',
            'prayer_text_incense_vertical_line_height' => 'incense.vertical.line_height',
            'prayer_text_incense_vertical_column_gap_scale' => 'incense.vertical.column_gap_scale',
            'prayer_text_incense_horizontal_font_scale' => 'incense.horizontal.font_scale',
            'prayer_text_incense_horizontal_line_height' => 'incense.horizontal.line_height',
        ];
    }

    private function settingKey(string $group, string $style, string $key): string
    {
        return sprintf('prayer_text_%s_%s_%s', $group, $style, $key);
    }

    /**
     * @return array<string, string|null>
     */
    private function rawSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        return $this->cachedSettings = AppSetting::getMany(array_keys($this->settingMap()));
    }
}
