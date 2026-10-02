<?php

namespace App\Livewire\Admin\CheckIns\Concerns;

use App\Domain\CheckIns\Actions\OverrideCheckIn;
use App\Domain\CheckIns\Actions\RegisterCheckIn;
use App\Domain\CheckIns\DTOs\CheckInOutcome;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Support\Locations\CurrentLocation;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Registro manual de ingresos desde recepción y autorización de rechazos.
 * Lo usan la pantalla de asistencia y la ficha del cliente.
 */
trait RegistersCheckIns
{
    public string $deskLocationId = '';

    /** @var array{accepted: bool, duplicate: bool, title: string, reason: ?string, warnings: list<string>, checkInId: int, canOverride: bool}|null */
    public ?array $outcome = null;

    #[Locked]
    public ?int $overrideId = null;

    public bool $showOverride = false;

    public string $overrideReason = '';

    /**
     * Sedes activas del usuario donde puede registrar ingresos.
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function deskLocations(): Collection
    {
        return Location::query()->active()->orderBy('name')->get(['id', 'name'])
            ->filter(fn (Location $l) => auth()->user()->can('create', [CheckIn::class, $l]))
            ->values();
    }

    protected function pickDeskLocation(?Member $member = null): void
    {
        $ids = $this->deskLocations->pluck('id');
        $preferred = app(CurrentLocation::class)->id() ?? $member?->home_location_id;

        if ($this->deskLocationId === '' || ! $ids->contains((int) $this->deskLocationId)) {
            $this->deskLocationId = (string) ($ids->contains($preferred) ? $preferred : ($ids->first() ?? ''));
        }
    }

    protected function registerCheckInFor(Member $member): void
    {
        $this->validate(
            ['deskLocationId' => ['required', 'integer', Rule::in($this->deskLocations->pluck('id')->all())]],
            [], ['deskLocationId' => 'sede'],
        );

        $location = Location::query()->findOrFail((int) $this->deskLocationId);
        $this->authorize('create', [CheckIn::class, $location, $member]);

        $result = app(RegisterCheckIn::class)->execute($location, CheckInMethod::Manual, $member, null, auth()->user());

        $this->outcome = $this->presentOutcome($result);
    }

    public function openOverride(int $checkInId): void
    {
        $checkIn = CheckIn::query()->findOrFail($checkInId);
        $this->authorize('override', $checkIn);

        $this->overrideId = $checkIn->id;
        $this->overrideReason = '';
        $this->resetValidation();
        $this->showOverride = true;
    }

    public function confirmOverride(OverrideCheckIn $override): void
    {
        $checkIn = CheckIn::query()->findOrFail((int) $this->overrideId);
        $this->authorize('override', $checkIn);

        $this->validate(['overrideReason' => ['required', 'string', 'max:200']], [], ['overrideReason' => 'motivo']);

        $override->execute($checkIn, $this->overrideReason, auth()->user());

        $this->showOverride = false;
        $this->outcome = [
            'accepted' => true,
            'duplicate' => false,
            'title' => 'Ingreso autorizado',
            'reason' => null,
            'warnings' => ['Quedó registrado en la auditoría con el motivo indicado.'],
            'checkInId' => $checkIn->id,
            'canOverride' => false,
        ];
        $this->toast('Ingreso autorizado.');
    }

    public function dismissOutcome(): void
    {
        $this->outcome = null;
    }

    /**
     * @return array{accepted: bool, duplicate: bool, title: string, reason: ?string, warnings: list<string>, checkInId: int, canOverride: bool}
     */
    private function presentOutcome(CheckInOutcome $result): array
    {
        $name = $result->member?->full_name ?? 'Cliente';

        return [
            'accepted' => $result->accepted(),
            'duplicate' => $result->isDuplicate(),
            'title' => match (true) {
                $result->accepted() => "Ingreso registrado: {$name}",
                $result->isDuplicate() => "{$name} ya tenía un ingreso reciente",
                default => "Ingreso rechazado: {$name}",
            },
            'reason' => $result->reason()?->label(),
            'warnings' => $result->warnings,
            'checkInId' => $result->checkIn->id,
            'canOverride' => ! $result->accepted() && auth()->user()->can('override', $result->checkIn),
        ];
    }
}
