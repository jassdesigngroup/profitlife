<?php

namespace App\Domain\Appointments\Services;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Locations\Enums\RoomType;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Models\Room;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffSchedule;
use App\Domain\Staff\Models\StaffTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Disponibilidad de los profesionales: franjas semanales por sede, menos
 * ausencias, cierres de la sede y citas activas (con su margen). Las
 * franjas son horas locales de la sede; el resultado va en UTC.
 */
class Availability
{
    public const STEP_MINUTES = 15;

    /**
     * Profesionales activos y agendables que prestan el servicio en la sede.
     *
     * @return Collection<int, Staff>
     */
    public function eligibleStaff(Service $service, Location $location): Collection
    {
        return Staff::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('status', StaffStatus::Active)
            ->where('is_bookable', true)
            ->whereHas('services', fn ($q) => $q->where('services.id', $service->id))
            ->whereHas('locations', fn ($q) => $q->where('locations.id', $location->id))
            ->orderBy('first_name')
            ->get();
    }

    public function isEligible(Staff $staff, Service $service, Location $location): bool
    {
        return $this->eligibleStaff($service, $location)->contains('id', $staff->id);
    }

    /**
     * Ventanas de atención de un profesional en una sede y fecha local.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function windows(Staff $staff, Location $location, string $date): array
    {
        $closed = LocationClosure::query()->affecting($location->id)->whereDate('closed_on', $date)->exists();
        if ($closed) {
            return [];
        }

        $tz = $location->timezone ?: 'UTC';
        $windows = StaffSchedule::query()
            ->where('staff_id', $staff->id)
            ->where('location_id', $location->id)
            ->onDate($date)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (StaffSchedule $s) => [
                CarbonImmutable::parse("{$date} {$s->starts_at}", $tz)->utc(),
                CarbonImmutable::parse("{$date} {$s->ends_at}", $tz)->utc(),
            ])
            ->all();

        $dayStart = CarbonImmutable::parse($date, $tz)->startOfDay()->utc();
        $dayEnd = CarbonImmutable::parse($date, $tz)->endOfDay()->utc();

        $absences = StaffTimeOff::query()
            ->where('staff_id', $staff->id)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get();

        foreach ($absences as $absence) {
            $windows = $this->subtract($windows, $absence->starts_at->toImmutable(), $absence->ends_at->toImmutable());
        }

        return array_values($windows);
    }

    /**
     * Motivo por el que no se puede agendar, o null si está libre.
     */
    public function problem(Staff $staff, Service $service, Location $location, CarbonImmutable $startsAt, ?int $ignoreAppointmentId = null): ?string
    {
        if (! $this->isEligible($staff, $service, $location)) {
            return "{$staff->full_name} no presta este servicio en la sede.";
        }

        $endsAt = $startsAt->addMinutes($service->duration_minutes);
        $date = $startsAt->setTimezone($location->timezone ?: 'UTC')->toDateString();

        $fits = collect($this->windows($staff, $location, $date))
            ->contains(fn (array $w) => $startsAt->greaterThanOrEqualTo($w[0]) && $endsAt->lessThanOrEqualTo($w[1]));

        if (! $fits) {
            return "El horario está fuera de la disponibilidad de {$staff->full_name}.";
        }

        if ($this->overlaps($staff, $startsAt, $startsAt->addMinutes($service->blockMinutes()), $ignoreAppointmentId)) {
            return "{$staff->full_name} ya tiene una cita en ese horario.";
        }

        return null;
    }

    /**
     * Horarios libres del servicio en una fecha local, por profesional.
     *
     * @return Collection<int, array{staff_id: int, staff_name: string, starts_at: CarbonImmutable}>
     */
    public function slots(Service $service, Location $location, string $date, ?Staff $only = null, ?int $ignoreAppointmentId = null): Collection
    {
        $now = Date::now()->toImmutable();
        $staff = $only !== null
            ? collect($this->isEligible($only, $service, $location) ? [$only] : [])
            : $this->eligibleStaff($service, $location);

        $slots = collect();

        foreach ($staff as $person) {
            $busy = $this->busyBlocks($person, ...$this->dayBounds($location, $date), ignoreId: $ignoreAppointmentId);

            foreach ($this->windows($person, $location, $date) as [$from, $until]) {
                for ($t = $from; $t->addMinutes($service->duration_minutes)->lessThanOrEqualTo($until); $t = $t->addMinutes(self::STEP_MINUTES)) {
                    if ($t->lessThan($now)) {
                        continue;
                    }

                    $blockEnd = $t->addMinutes($service->blockMinutes());
                    $clash = $busy->contains(fn (array $b) => $t->lessThan($b[1]) && $blockEnd->greaterThan($b[0]));

                    if (! $clash) {
                        $slots->push(['staff_id' => $person->id, 'staff_name' => $person->full_name, 'starts_at' => $t]);
                    }
                }
            }
        }

        return $slots->sortBy(fn ($s) => $s['starts_at']->getTimestamp())->values();
    }

    public function overlaps(Staff $staff, CarbonImmutable $start, CarbonImmutable $blockEnd, ?int $ignoreId = null): bool
    {
        return $this->busyBlocks($staff, $start->subDay(), $blockEnd->addDay(), ignoreId: $ignoreId)
            ->contains(fn (array $b) => $start->lessThan($b[1]) && $blockEnd->greaterThan($b[0]));
    }

    /**
     * Un consultorio activo de la sede libre en el bloque (el pedido, si se
     * indica). Las salas grupales y zonas no se asignan a citas individuales.
     */
    public function freeRoom(Location $location, CarbonImmutable $start, CarbonImmutable $blockEnd, ?int $roomId = null, ?int $ignoreId = null): ?Room
    {
        $rooms = Room::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->where('type', RoomType::ConsultingRoom)
            ->when($roomId !== null, fn ($q) => $q->where('id', $roomId))
            ->orderBy('name')
            ->get();

        return $rooms->first(function (Room $room) use ($start, $blockEnd, $ignoreId) {
            return ! Appointment::query()->withoutGlobalScopes()
                ->where('room_id', $room->id)
                ->active()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('starts_at', '<', $blockEnd)
                ->where('starts_at', '>', $start->subDay())
                ->with('service')
                ->get()
                ->contains(fn (Appointment $a) => $start->lessThan($a->blockEndsAt()) && $blockEnd->greaterThan($a->starts_at));
        });
    }

    /**
     * @return Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function busyBlocks(Staff $staff, CarbonImmutable $from, CarbonImmutable $until, ?int $ignoreId = null): Collection
    {
        return Appointment::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('staff_id', $staff->id)
            ->active()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $until)
            ->where('ends_at', '>', $from->subHours(6))
            ->with('service')
            ->get()
            ->map(fn (Appointment $a) => [$a->starts_at->toImmutable(), $a->blockEndsAt()->toImmutable()]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dayBounds(Location $location, string $date): array
    {
        $tz = $location->timezone ?: 'UTC';

        return [CarbonImmutable::parse($date, $tz)->startOfDay()->utc(), CarbonImmutable::parse($date, $tz)->endOfDay()->utc()];
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtract(array $windows, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $result = [];

        foreach ($windows as [$start, $end]) {
            if ($until->lessThanOrEqualTo($start) || $from->greaterThanOrEqualTo($end)) {
                $result[] = [$start, $end];

                continue;
            }
            if ($from->greaterThan($start)) {
                $result[] = [$start, $from];
            }
            if ($until->lessThan($end)) {
                $result[] = [$until, $end];
            }
        }

        return $result;
    }
}
