<?php

use App\Domain\Locations\Models\Location;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Services\Settings;

it('lee la marca, moneda y zona horaria de settings con respaldo en config', function () {
    $settings = app(Settings::class);

    expect($settings->brandName())->toBe(config('profitlife.brand_name'))
        ->and($settings->currency())->toBe('COP')
        ->and($settings->displayTimezone())->toBe('America/Bogota');

    $settings->set('general', 'brand_name', 'Mi Centro');

    expect(app(Settings::class)->brandName())->toBe('Mi Centro');
});

it('prioriza el ajuste de la sede sobre el global y no duplica claves', function () {
    $location = Location::factory()->create();
    $settings = app(Settings::class);

    $settings->set('checkin', 'grace_minutes', 10);
    $settings->set('checkin', 'grace_minutes', 15);
    $settings->set('checkin', 'grace_minutes', 5, $location->id);

    expect($settings->get('checkin', 'grace_minutes'))->toBe(15)
        ->and($settings->get('checkin', 'grace_minutes', null, $location->id))->toBe(5)
        ->and(Setting::query()->where('key', 'grace_minutes')->count())->toBe(2);
});

it('muestra la marca configurada en la interfaz', function () {
    app(Settings::class)->set('general', 'brand_name', 'Marca Configurable');

    $this->get('/login')->assertSee('Marca Configurable');
});

it('guarda las fechas en UTC', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(DB::selectOne('SELECT @@session.time_zone AS tz')->tz)->toBe('+00:00');

    $this->travelTo(now()->setTimezone('America/Bogota')->setTime(20, 0));
    $location = Location::factory()->create();

    $raw = DB::table('locations')->where('id', $location->id)->value('created_at');
    expect($raw)->toBe(now()->utc()->format('Y-m-d H:i:s'));
});
