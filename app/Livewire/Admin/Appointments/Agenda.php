<?php

namespace App\Livewire\Admin\Appointments;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Services\Availability;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Agenda por sede: vista diaria con una columna por profesional y vista
 * semanal de un profesional. Quien no tiene appointments.view-all solo ve
 * su propia agenda.
 */
#[Title('Agenda')]
class Agenda extends Component
{
    public const PX_PER_MINUTE = 1.2;

    #[Url(as: 'vista')]
    public string $view = 'day';

    #[Url(as: 'fecha')]
    public string $date = '';

    #[Url(as: 'sede')]
    public string $locationId = '';

    #[Url(as: 'profesional')]
    public string $staffId = '';

    #[Url(as: 'servicio')]
    public string $serviceId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            $this->date = BusinessDate::today()->toDateString();
        }

        if (! in_array($this->view, ['day', 'week'], true)) {
            $this->view = 'day';
        }

        $ids = $this->locations->pluck('id');
        if (! $ids->contains((int) $this->locationId)) {
            $preferred = app(CurrentLocation::class)->id();
            $this->locationId = (string) ($ids->contains($preferred) ? $preferred : ($ids->first() ?? ''));
        }
    }

    #[On('appointments-changed')]
    public function refreshAgenda(): void
    {
        // Solo vuelve a renderizar.
    }

    public function move(int $days): void
    {
        $step = $this->view === 'week' ? 7 * $days : $days;
        $this->date = $this->day()->addDays($step)->toDateString();
    }

    public function today(): void
    {
        $this->date = BusinessDate::today()->toDateString();
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->active()->orderBy('name')->get(['id', 'name', 'timezone']);
    }

    public function render(Availability $availability): View
    {
        $this->authorize('viewAny', Appointment::class);

        $location = $this->locations->firstWhere('id', (int) $this->locationId);
        $user = auth()->user();
        $seesAll = $user->can('appointments.view-all');
        $own = $user->staff;

        $staff = $location ? $this->staffAt($location) : collect();
        if (! $seesAll) {
            $staff = $staff->where('id', $own?->id)->values();
        }
        if ($this->staffId !== '' && $staff->contains('id', (int) $this->staffId)) {
            $staff = $staff->where('id', (int) $this->staffId)->values();
        } elseif ($this->view === 'week') {
            $staff = $staff->take(1);
        }

        $tz = $location?->timezone ?: 'UTC';
        $days = $this->view === 'week'
            ? collect(range(0, 6))->map(fn ($i) => $this->day()->startOfWeek(CarbonInterface::MONDAY)->addDays($i))
            : collect([$this->day()]);

        $from = CarbonImmutable::parse($days->first()->toDateString(), $tz)->startOfDay()->utc();
        $until = CarbonImmutable::parse($days->last()->toDateString(), $tz)->endOfDay()->utc();

        $appointments = $location ? Appointment::query()
            ->where('location_id', $location->id)
            ->whereIn('staff_id', $staff->pluck('id'))
            ->whereNotIn('status', [AppointmentStatus::Rescheduled->value])
            ->when($this->serviceId !== '', fn ($q) => $q->where('service_id', (int) $this->serviceId))
            ->whereBetween('starts_at', [$from, $until])
            ->with(['member:id,first_name,last_name', 'service:id,name,color,buffer_minutes'])
            ->orderBy('starts_at')
            ->get() : collect();

        // Columnas: profesionales del día, o los 7 días del profesional.
        $columns = $this->view === 'week'
            ? $days->map(fn (CarbonImmutable $d) => ['key' => $d->toDateString(), 'label' => ucfirst($d->locale('es')->translatedFormat('D j')), 'date' => $d->toDateString(), 'staff' => $staff->first()])
            : $staff->map(fn (Staff $s) => ['key' => 'st-'.$s->id, 'label' => $s->full_name, 'date' => $days->first()->toDateString(), 'staff' => $s]);

        $columns = $columns->filter(fn ($c) => $c['staff'] !== null)->map(function (array $column) use ($availability, $location, $appointments, $tz) {
            $column['windows'] = $location ? $availability->windows($column['staff'], $location, $column['date']) : [];
            $column['appointments'] = $appointments->filter(fn (Appointment $a) => $a->staff_id === $column['staff']->id
                && $a->starts_at->setTimezone($tz)->toDateString() === $column['date'])->values();

            return $column;
        })->values();

        [$startHour, $endHour] = $this->hourRange($columns, $tz);

        return view('livewire.admin.appointments.agenda', [
            'location' => $location,
            'tz' => $tz,
            'columns' => $columns,
            'staffOptions' => $seesAll && $location ? $this->staffAt($location)->pluck('full_name', 'id')->all() : [],
            'services' => Service::query()->orderBy('name')->pluck('name', 'id')->all(),
            'startHour' => $startHour,
            'endHour' => $endHour,
            'scale' => self::PX_PER_MINUTE,
            'canBook' => $location !== null && $user->can('create', [Appointment::class, $location]),
            'title' => $this->view === 'week'
                ? 'Semana del '.$days->first()->format('d/m').' al '.$days->last()->format('d/m/Y')
                : ucfirst($this->day()->locale('es')->translatedFormat('l j \d\e F \d\e Y')),
            'todayCount' => $appointments->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Pending, AppointmentStatus::Completed])->count(),
        ]);
    }

    private function day(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->date, 'UTC');
    }

    /**
     * Profesionales agendables y activos de la sede.
     *
     * @return Collection<int, Staff>
     */
    private function staffAt(Location $location): Collection
    {
        return Staff::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->where('status', StaffStatus::Active)
            ->where('is_bookable', true)
            ->whereHas('locations', fn ($q) => $q->where('locations.id', $location->id))
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function hourRange(Collection $columns, string $tz): array
    {
        $start = 7;
        $end = 19;

        foreach ($columns as $column) {
            foreach ($column['windows'] as [$from, $until]) {
                $start = min($start, (int) $from->setTimezone($tz)->format('G'));
                $end = max($end, (int) ceil(((int) $until->setTimezone($tz)->format('G') * 60 + (int) $until->setTimezone($tz)->format('i')) / 60));
            }
            foreach ($column['appointments'] as $a) {
                $start = min($start, (int) $a->starts_at->setTimezone($tz)->format('G'));
                $end = max($end, (int) $a->ends_at->setTimezone($tz)->format('G') + 1);
            }
        }

        return [max(0, $start), min(24, $end)];
    }
}
