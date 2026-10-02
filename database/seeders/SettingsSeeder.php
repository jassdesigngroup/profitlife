<?php

namespace Database\Seeders;

use App\Domain\Settings\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * Ajustes globales iniciales tomados de la configuración (.env). Solo crea
 * los que no existen: no sobrescribe lo que se haya cambiado después.
 */
class SettingsSeeder extends Seeder
{
    public function run(Settings $settings): void
    {
        $defaults = [
            'brand_name' => config('profitlife.brand_name'),
            'currency' => config('profitlife.currency'),
            'timezone' => config('profitlife.display_timezone'),
            'locale' => config('profitlife.locale'),
        ];

        foreach ($defaults as $key => $value) {
            if ($settings->get('general', $key) === null) {
                $settings->set('general', $key, $value);
            }
        }
    }
}
