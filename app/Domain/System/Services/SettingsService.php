<?php

namespace App\Domain\System\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    public function getSetting(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = DB::table('settings')->where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public function setSetting(string $key, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );

        Cache::forget("setting_{$key}");
    }

    public function getCooperativeSettings(int $cooperativeId): array
    {
        return Cache::remember("cooperative_settings_{$cooperativeId}", 1800, function () use ($cooperativeId) {
            return DB::table('cooperative_settings')
                ->where('cooperative_id', $cooperativeId)
                ->pluck('value', 'key')
                ->toArray();
        });
    }
}
