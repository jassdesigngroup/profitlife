<?php

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\CheckIns\Actions\OverrideCheckIn;
use App\Domain\CheckIns\Actions\RegisterCheckIn;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\CheckIns\CheckInIndex;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'America/Bogota'));
    $this->location = Location::factory()->create();
    $this->manager = staffUser(RoleName::LocationManager, [$this->location]);
    $this->reception = staffUser(RoleName::Reception, [$this->location]);
    $this->member = memberAt($this->location);
    $this->rejected = registerCheckIn($this->member, $this->location, $this->reception)->checkIn;
});

it('el gerente autoriza un rechazo con motivo y queda auditado', function () {
    Livewire::actingAs($this->manager)->test(CheckInIndex::class)
        ->call('openOverride', $this->rejected->id)
        ->assertSet('showOverride', true)
        ->set('overrideReason', 'Pagará hoy en caja')
        ->call('confirmOverride')
        ->assertHasNoErrors()
        ->assertSet('outcome.accepted', true);

    $accepted = CheckIn::query()->where('result', CheckInResult::Accepted)->sole();
    expect($accepted->member_id)->toBe($this->member->id)
        ->and($accepted->method)->toBe(CheckInMethod::Manual)
        ->and($accepted->registered_by)->toBe($this->manager->id)
        ->and($this->rejected->fresh()->result)->toBe(CheckInResult::Rejected);

    $log = Activity::query()->where('event', AuditEvent::CheckInOverridden->value)->sole();
    expect($log->causer_id)->toBe($this->manager->id)
        ->and($log->properties['reason'])->toBe('Pagará hoy en caja')
        ->and($log->properties['rejected_check_in_id'])->toBe($this->rejected->id);
});

it('exige el motivo', function () {
    Livewire::actingAs($this->manager)->test(CheckInIndex::class)
        ->call('openOverride', $this->rejected->id)
        ->call('confirmOverride')
        ->assertHasErrors(['overrideReason' => 'required']);
});

it('recepción no puede autorizar rechazos', function () {
    Livewire::actingAs($this->reception)->test(CheckInIndex::class)
        ->call('openOverride', $this->rejected->id)
        ->assertForbidden();
});

it('no autoriza intentos sin identificar, de otro día ni de otra sede', function () {
    $unknown = app(RegisterCheckIn::class)->execute($this->location, CheckInMethod::MemberNumber, null)->checkIn;
    expect($this->manager->can('override', $unknown))->toBeFalse();

    $elsewhere = Location::factory()->create();
    $foreign = registerCheckIn(memberAt($elsewhere), $elsewhere)->checkIn;
    expect($this->manager->can('override', $foreign))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00', 'America/Bogota'));
    expect(fn () => app(OverrideCheckIn::class)->execute($this->rejected, 'Tarde', $this->manager))
        ->toThrow(ValidationException::class, 'Solo se pueden autorizar rechazos del día.');
});

it('no autoriza dos veces el mismo ingreso', function () {
    app(OverrideCheckIn::class)->execute($this->rejected, 'Primera', $this->manager);

    expect(fn () => app(OverrideCheckIn::class)->execute($this->rejected, 'Segunda', $this->manager))
        ->toThrow(ValidationException::class, 'ya tiene un ingreso registrado');
});
