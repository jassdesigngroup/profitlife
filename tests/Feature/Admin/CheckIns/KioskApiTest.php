<?php

use App\Domain\CheckIns\Actions\IssueAccessCode;
use App\Domain\CheckIns\Actions\PairKioskDevice;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Actions\SetCheckinPin;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'America/Bogota'));
    $this->location = Location::factory()->create(['name' => 'Sede Cabecera']);
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->device = KioskDevice::factory()->create(['location_id' => $this->location->id, 'name' => 'Tablet entrada']);
    $this->token = app(PairKioskDevice::class)->execute($this->device, $this->manager);
    $this->member = numberedMember($this->location, ['first_name' => 'Laura', 'last_name' => 'Quintero', 'phone' => '+573001112233']);
    paidSale($this->member, $this->location, $this->manager);
});

/**
 * Cliente con el número real del sistema (prefijo + id), como en producción.
 */
function numberedMember(Location $location, array $attributes = []): Member
{
    $member = memberAt($location, $attributes);
    $member->forceFill(['member_number' => app(MemberNumber::class)->for($member->id)])->save();

    return $member;
}

function kiosk(string $token, array $payload)
{
    return test()->withToken($token)->postJson('/api/kiosk/check-ins', $payload);
}

it('la pantalla del kiosco es pública', function () {
    $this->get('/kiosco')->assertOk()->assertSee('Registra tu');
});

it('exige un token de kiosco válido', function () {
    $this->getJson('/api/kiosk/me')->assertUnauthorized();
    $this->withToken('1|invalido')->getJson('/api/kiosk/me')->assertUnauthorized();

    // Un token de usuario del staff no sirve.
    $userToken = $this->manager->createToken('x')->plainTextToken;
    $this->withToken($userToken)->getJson('/api/kiosk/me')->assertForbidden();
});

it('identifica el kiosco y anota la última conexión', function () {
    $this->withToken($this->token)->getJson('/api/kiosk/me')
        ->assertOk()
        ->assertJson(['device' => 'Tablet entrada', 'location' => 'Sede Cabecera']);

    expect($this->device->fresh()->last_seen_at)->not->toBeNull()
        ->and($this->device->fresh()->last_ip)->toBe('127.0.0.1');
});

it('bloquea kioscos inactivos, de sedes inactivas o con el enlace reemplazado', function () {
    $this->device->update(['is_active' => false]);
    $this->withToken($this->token)->getJson('/api/kiosk/me')->assertForbidden();

    $this->device->update(['is_active' => true]);
    $this->location->update(['is_active' => false]);
    $this->withToken($this->token)->getJson('/api/kiosk/me')->assertForbidden();

    $this->location->update(['is_active' => true]);
    $new = app(PairKioskDevice::class)->execute($this->device, $this->manager);
    app('auth')->forgetGuards();
    $this->withToken($this->token)->getJson('/api/kiosk/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($new)->getJson('/api/kiosk/me')->assertOk();
});

it('registra el ingreso con el QR y responde solo el nombre de pila', function () {
    $credential = app(IssueAccessCode::class)->execute($this->member, $this->manager);

    $response = kiosk($this->token, ['method' => 'qr', 'value' => $credential->token_encrypted])
        ->assertOk()
        ->assertJson(['status' => 'accepted', 'title' => '¡Hola, Laura!']);

    expect($response->getContent())->not->toContain('Quintero')
        ->and($response->getContent())->not->toContain($this->member->document_number);

    $checkIn = CheckIn::query()->sole();
    expect($checkIn->method)->toBe(CheckInMethod::Qr)
        ->and($checkIn->kiosk_device_id)->toBe($this->device->id)
        ->and($checkIn->registered_by)->toBeNull()
        ->and($credential->fresh()->last_used_at)->not->toBeNull();
});

it('un QR reemplazado deja de servir', function () {
    $old = app(IssueAccessCode::class)->execute($this->member, $this->manager);
    app(IssueAccessCode::class)->execute($this->member, $this->manager);

    kiosk($this->token, ['method' => 'qr', 'value' => $old->token_encrypted])
        ->assertOk()->assertJson(['status' => 'rejected']);

    expect(CheckIn::query()->sole()->rejection_reason)->toBe(RejectionReason::NotFound);
});

it('acepta el número de cliente completo o solo los dígitos', function () {
    $digits = (string) $this->member->id;

    kiosk($this->token, ['method' => 'member_number', 'value' => $digits])->assertJson(['status' => 'accepted']);

    $this->travel(2)->hours();
    kiosk($this->token, ['method' => 'member_number', 'value' => strtolower($this->member->member_number)])->assertJson(['status' => 'accepted']);

    kiosk($this->token, ['method' => 'member_number', 'value' => '999999'])->assertJson(['status' => 'rejected']);
});

it('celular y PIN: acepta el correcto y bloquea tras varios intentos fallidos', function () {
    app(SetCheckinPin::class)->execute($this->member, '2580', $this->manager);
    RateLimiter::clear('kiosk-pin:'.hash('sha256', '3001112233'));

    kiosk($this->token, ['method' => 'phone', 'value' => '300 111 2233', 'pin' => '2580'])
        ->assertJson(['status' => 'accepted']);

    $this->travel(2)->hours();
    foreach (range(1, 5) as $i) {
        kiosk($this->token, ['method' => 'phone', 'value' => '3001112233', 'pin' => '0000'])->assertJson(['status' => 'rejected']);
    }

    kiosk($this->token, ['method' => 'phone', 'value' => '3001112233', 'pin' => '2580'])
        ->assertStatus(429)
        ->assertJson(['title' => 'Demasiados intentos']);
});

it('sin PIN no hay ingreso por celular', function () {
    kiosk($this->token, ['method' => 'phone', 'value' => '3001112233', 'pin' => '1234'])
        ->assertJson(['status' => 'rejected']);
});

it('valida los datos recibidos', function () {
    kiosk($this->token, ['method' => 'manual', 'value' => 'x'])->assertJsonValidationErrors('method');
    kiosk($this->token, ['method' => 'phone', 'value' => '3001112233'])->assertJsonValidationErrors('pin');
    kiosk($this->token, ['method' => 'qr', 'value' => str_repeat('a', 300)])->assertJsonValidationErrors('value');
});

it('un ingreso repetido deja pasar sin contar otro', function () {
    kiosk($this->token, ['method' => 'member_number', 'value' => (string) $this->member->id])->assertJson(['status' => 'accepted']);

    kiosk($this->token, ['method' => 'member_number', 'value' => (string) $this->member->id])
        ->assertJson(['status' => 'accepted', 'message' => RejectionReason::Duplicate->kioskMessage()]);

    expect(CheckIn::query()->where('result', CheckInResult::Accepted)->count())->toBe(1);
});

it('avisa al cliente de su pago pendiente sin mostrar el monto', function () {
    $owing = numberedMember($this->location, ['first_name' => 'Pedro']);
    sellTo($owing, planFor(['price_cents' => 15000000]), $this->location, $this->manager);

    $response = kiosk($this->token, ['method' => 'member_number', 'value' => (string) $owing->id])
        ->assertJson(['status' => 'accepted', 'notices' => ['Tienes un pago pendiente. Acércate a recepción.']]);

    expect($response->getContent())->not->toContain('150.000');
});

it('rechaza con el motivo para el cliente', function () {
    $nobody = numberedMember($this->location, ['first_name' => 'Ana']);

    kiosk($this->token, ['method' => 'member_number', 'value' => (string) $nobody->id])
        ->assertJson([
            'status' => 'rejected',
            'title' => 'Ana, no pudimos registrar tu ingreso',
            'message' => RejectionReason::MembershipExpired->kioskMessage(),
        ]);
});
