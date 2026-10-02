<?php

namespace App\Livewire\Admin\Staff;

use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Models\Location;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Actions\RemoveTimeOff;
use App\Domain\Staff\Actions\SaveStaffSchedules;
use App\Domain\Staff\Actions\SaveTimeOff;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffSchedule;
use App\Domain\Staff\Models\StaffTimeOff;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Disponibilidad semanal del profesional por sede y sus ausencias. Solo se
 * editan las franjas de las sedes a las que tiene acceso quien edita.
 */
class StaffAvailability extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $staffId;

    /** @var list<array{location_id: string, day_of_week: string, starts_at: string, ends_at: string}> */
    public array $schedules = [];

    public string $offFrom = '';

    public string $offUntil = '';

    public string $offReason = '';

    public function mount(Staff $staff): void
    {
        $this->authorize('update', $staff);
        $this->staffId = $staff->id;
        $this->loadSchedules();
    }

    public function addRow(): void
    {
        $this->schedules[] = [
            'location_id' => (string) ($this->editableLocations()->first()?->id ?? ''),
            'day_of_week' => '1',
            'starts_at' => '06:00',
            'ends_at' => '12:00',
        ];
    }

    /**
     * Copia las franjas del lunes a los demás días hábiles.
     */
    public function copyMonday(): void
    {
        $monday = collect($this->schedules)->where('day_of_week', '1')->values();
        $others = collect($this->schedules)->reject(fn ($r) => in_array($r['day_of_week'], ['2', '3', '4', '5'], true));

        foreach (['2', '3', '4', '5'] as $day) {
            foreach ($monday as $row) {
                $others->push(['day_of_week' => $day] + $row);
            }
        }

        $this->schedules = $others->sortBy([['day_of_week', 'asc'], ['starts_at', 'asc']])->values()->all();
    }

    public function removeRow(int $index): void
    {
        unset($this->schedules[$index]);
        $this->schedules = array_values($this->schedules);
    }

    public function saveSchedules(SaveStaffSchedules $save): void
    {
        $staff = $this->staff();
        $this->authorize('update', $staff);

        $editable = $this->editableLocations()->pluck('id')->all();

        $this->validate([
            'schedules' => ['array', 'max:60'],
            'schedules.*.location_id' => ['required', 'integer', Rule::in($editable)],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i'],
        ], [], ['schedules.*.location_id' => 'sede', 'schedules.*.starts_at' => 'desde', 'schedules.*.ends_at' => 'hasta']);

        $rows = array_map(fn (array $r) => [
            'location_id' => (int) $r['location_id'],
            'day_of_week' => (int) $r['day_of_week'],
            'starts_at' => $r['starts_at'].':00',
            'ends_at' => $r['ends_at'].':00',
        ], $this->schedules);

        // Las franjas en sedes que no edita se conservan y cuentan para los cruces.
        $kept = StaffSchedule::query()->where('staff_id', $staff->id)->whereNotIn('location_id', $editable)->get()
            ->map(fn (StaffSchedule $s) => ['location_id' => $s->location_id, 'day_of_week' => $s->day_of_week->value, 'starts_at' => $s->starts_at, 'ends_at' => $s->ends_at]);

        $save->execute($staff, $rows, $editable, auth()->user(), $kept->all());

        $this->loadSchedules();
        $this->toast('Disponibilidad guardada.');
    }

    public function addTimeOff(SaveTimeOff $save, Settings $settings): void
    {
        $staff = $this->staff();
        $this->authorize('update', $staff);

        $this->validate([
            'offFrom' => ['required', 'date_format:Y-m-d\TH:i'],
            'offUntil' => ['required', 'date_format:Y-m-d\TH:i'],
            'offReason' => ['nullable', 'string', 'max:150'],
        ], [], ['offFrom' => 'desde', 'offUntil' => 'hasta', 'offReason' => 'motivo']);

        $tz = $settings->displayTimezone();
        $affected = $save->execute(
            $staff,
            CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->offFrom, $tz)->utc(),
            CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->offUntil, $tz)->utc(),
            $this->offReason ?: null,
            auth()->user(),
        );

        $this->reset(['offFrom', 'offUntil', 'offReason']);
        $affected > 0
            ? $this->toast("Ausencia registrada. Hay {$affected} citas en ese periodo: reprográmelas desde la agenda.", 'warning')
            : $this->toast('Ausencia registrada.');
    }

    public function removeTimeOff(int $id, RemoveTimeOff $remove): void
    {
        $staff = $this->staff();
        $this->authorize('update', $staff);

        $off = StaffTimeOff::query()->where('staff_id', $staff->id)->findOrFail($id);
        $remove->execute($staff, $off, auth()->user());
        $this->toast('Ausencia eliminada.');
    }

    public function render(): View
    {
        $staff = $this->staff();
        $this->authorize('update', $staff);

        $editable = $this->editableLocations()->pluck('id')->all();

        return view('livewire.admin.staff.availability', [
            'staff' => $staff,
            'locations' => $this->editableLocations()->pluck('name', 'id')->all(),
            'days' => DayOfWeek::options(),
            'readOnly' => StaffSchedule::query()->where('staff_id', $staff->id)->whereNotIn('location_id', $editable)->with('location:id,name')->get(),
            'timeOff' => StaffTimeOff::query()->where('staff_id', $staff->id)->where('ends_at', '>=', now())->orderBy('starts_at')->get(),
        ])->title('Disponibilidad · '.$staff->full_name);
    }

    private function loadSchedules(): void
    {
        $editable = $this->editableLocations()->pluck('id')->all();

        $this->schedules = StaffSchedule::query()->where('staff_id', $this->staffId)->whereIn('location_id', $editable)
            ->orderBy('day_of_week')->orderBy('starts_at')->get()
            ->map(fn (StaffSchedule $s) => [
                'location_id' => (string) $s->location_id,
                'day_of_week' => (string) $s->day_of_week->value,
                'starts_at' => substr($s->starts_at, 0, 5),
                'ends_at' => substr($s->ends_at, 0, 5),
            ])->all();
    }

    /**
     * Sedes del profesional a las que tiene acceso quien edita.
     *
     * @return Collection<int, Location>
     */
    private function editableLocations(): Collection
    {
        $staffLocations = $this->staff()->locationIds();

        return Location::query()->whereIn('id', $staffLocations)->orderBy('name')->get(['id', 'name']);
    }

    private function staff(): Staff
    {
        return Staff::query()->findOrFail($this->staffId);
    }
}
