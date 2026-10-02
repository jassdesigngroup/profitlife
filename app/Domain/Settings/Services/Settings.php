<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lectura y escritura de ajustes. Un ajuste de sede prevalece sobre el
 * global; si no existe ninguno se usa el valor por defecto recibido.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    public function get(string $group, string $key, mixed $default = null, ?int $locationId = null): mixed
    {
        $all = $this->all();

        if ($locationId !== null && array_key_exists("{$locationId}:{$group}.{$key}", $all)) {
            return $all["{$locationId}:{$group}.{$key}"];
        }

        return $all["global:{$group}.{$key}"] ?? $default;
    }

    public function set(string $group, string $key, mixed $value, ?int $locationId = null): Setting
    {
        $setting = DB::transaction(function () use ($group, $key, $value, $locationId) {
            $setting = Setting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first() ?? new Setting(['group' => $group, 'key' => $key, 'location_id' => $locationId]);

            $setting->value = $value;
            $setting->save();

            return $setting;
        });

        $this->flush();

        return $setting;
    }

    public function brandName(): string
    {
        return (string) $this->get('general', 'brand_name', config('profitlife.brand_name'));
    }

    public function currency(): string
    {
        return (string) $this->get('general', 'currency', config('profitlife.currency'));
    }

    public function displayTimezone(): string
    {
        return (string) $this->get('general', 'timezone', config('profitlife.display_timezone'));
    }

    public function memberNumberPrefix(): string
    {
        return (string) $this->get('members', 'number_prefix', config('profitlife.members.number_prefix'));
    }

    /**
     * Días de gracia para pagar una membresía antes de suspenderla. El ajuste
     * de la sede prevalece sobre el global.
     */
    public function graceDays(?int $locationId = null): int
    {
        return max(0, (int) $this->get('memberships', 'grace_days', config('profitlife.memberships.grace_days'), $locationId));
    }

    /**
     * Minutos en los que un nuevo ingreso del mismo cliente se considera repetido.
     */
    public function checkInDuplicateMinutes(): int
    {
        return max(0, (int) $this->get('check_ins', 'duplicate_minutes', config('profitlife.check_ins.duplicate_minutes')));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
                ($s->location_id ?? 'global').":{$s->group}.{$s->key}" => $s->value,
            ])->all();
        });
    }
}
