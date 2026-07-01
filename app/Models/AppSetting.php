<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    public $timestamps = true;

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string|null>
     */
    public static function getMany(array $keys): array
    {
        $values = static::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->all();

        $settings = [];

        foreach ($keys as $key) {
            $settings[$key] = $values[$key] ?? null;
        }

        return $settings;
    }

    /**
     * @param  array<string, string|null>  $settings
     */
    public static function putMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
