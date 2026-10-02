<?php

namespace App\Livewire\Admin\Appointments;

use App\Domain\Appointments\Actions\BookAppointment;
use App\Domain\Appointments\DTOs\Coverage;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Services\Availability;
use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Staff\Models\Staff;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal para agendar. Lo abren la agenda (con profesional y hora) y la
 * ficha del cliente (con el cliente). Todo lo que llega del navegador se
 * vuelve a resolver con LocationScope y se autoriza con la Policy.
 */
class BookingModal extends Component
{
    use InteractsWithToasts;

    public bool $show = false;

    public string $memberId = '';

    public string $memberSearch = '';

    public bool $memberFixed = false;

    public string $locationId = '';

    public string $serviceId = '';

    public string $staffId = '';

    public string $date = '';

    /** "staffId|2026-10-05T14:00:00Z" */
    public string $pickedSlot = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
    }

    #[On('open-booking')]
    public function open(?int $memberId = null, ?int $staffId = null, ?string $date = null, ?string $time = null, ?int $locationId = null): void
    {
        $this->resetValidation();
        $this->reset(['memberId', 'memberSearch', 'memberFixed', 'serviceId', 'staffId', 'pickedSlot', 'notes']);

        $locations = $this->locations;
        if ($locations->isEmpty()) {
            $this->toast('No tiene sedes donde agendar.', 'warning');

            return;
        }

        $preferred = $locationId ?? app(CurrentLocation::class)->id();
        $this->locationId = (string) ($locations->firstWhere('id', $preferred)?->id ?? $locations->first()->id);
        $this->date = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : BusinessDate::today()->toDateString();

        if ($memberId !== null) {
            $member = Member::query()->findOrFail($memberId);
            $this->authorize('view', $member);
            $this->memberId = (string) $member->id;
            $this->memberFixed = true;
        }

        $own = $this->ownStaff();
        if ($own !== null) {
            $this->staffId = (string) $own->id;
        } elseif ($staffId !== null) {
            $this->staffId = (string) $staffId;
        }

        // Desde un hueco de la agenda: el primer servicio que ese profesional presta en la sede.
        if ($staffId !== null && $this->serviceId === '') {
            $first = Service::query()->offeredAt((int) $this->locationId)
                ->whereHas('staff', fn ($q) => $q->where('staff.id', (int) $this->staffId))
                ->orderBy('name')
                ->value('id');
            $this->serviceId = $first === null ? '' : (string) $first;
        }

        if ($time !== null && $this->staffId !== '' && preg_match('/^\d{2}:\d{2}$/', $time)) {
            $location = Location::query()->find((int) $this->locationId);
            $this->pickedSlot = $this->staffId.'|'.CarbonImmutable::parse("{$this->date} {$time}", $location?->timezone ?: 'UTC')->utc()->toIso8601ZuluString();
        }

        $this->show = true;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['locationId', 'serviceId', 'staffId', 'date'], true)) {
            $this->pickedSlot = '';
        }
    }

    public function pickMember(int $memberId): void
    {
        $member = Member::query()->findOrFail($memberId);
        $this->authorize('view', $member);
        $this->memberId = (string) $member->id;
        $this->memberSearch = '';
    }

    public function book(BookAppointment $book): void
    {
        $this->validate([
            'memberId' => ['required', 'integer'],
            'locationId' => ['required', 'integer', Rule::in($this->locations->pluck('id')->all())],
            'serviceId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'pickedSlot' => ['required', 'regex:/^\d+\|[0-9TZ:\-+]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['pickedSlot.required' => 'Elija un horario.'], ['memberId' => 'cliente', 'serviceId' => 'servicio', 'locationId' => 'sede']);

        [$staffId, $start] = explode('|', $this->pickedSlot, 2);

        $member = Member::query()->findOrFail((int) $this->memberId);
        $location = Location::query()->findOrFail((int) $this->locationId);
        $service = Service::query()->with('locations')->findOrFail((int) $this->serviceId);
        $staff = Staff::query()->withoutGlobalScopes()->whereNull('deleted_at')->findOrFail((int) $staffId);

        $this->authorize('create', [Appointment::class, $location, $staff, $member]);

        $appointment = $book->execute($member, $service, $location, $staff, CarbonImmutable::parse($start)->utc(), auth()->user(), notes: $this->notes ?: null);

        $this->show = false;
        $this->dispatch('appointments-changed');
        $this->toast('Cita agendada para '.$appointment->starts_at->setTimezone($location->timezone ?: 'UTC')->format('d/m/Y g:i a').'.');
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->active()->orderBy('name')->get(['id', 'name', 'timezone'])
            ->filter(fn (Location $l) => auth()->user()->can('create', [Appointment::class, $l]))
            ->values();
    }

    public function render(Availability $availability, SessionLedger $ledger): View
    {
        $location = $this->locationId !== '' ? $this->locations->firstWhere('id', (int) $this->locationId) : null;
        $services = $location ? Service::query()->offeredAt($location->id)->with('locations')->orderBy('name')->get() : collect();
        $service = $services->firstWhere('id', (int) $this->serviceId);
        $member = $this->memberId !== '' ? Member::query()->find((int) $this->memberId) : null;

        $staffOptions = $service && $location ? $availability->eligibleStaff($service, $location) : collect();
        $own = $this->ownStaff();
        if ($own !== null) {
            $staffOptions = $staffOptions->where('id', $own->id)->values();
        }

        $slots = collect();
        if ($service && $location && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            $only = $this->staffId !== '' ? $staffOptions->firstWhere('id', (int) $this->staffId) : null;
            if ($this->staffId === '' || $only !== null) {
                $slots = $availability->slots($service, $location, $this->date, $only)
                    ->filter(fn ($s) => $own === null || $s['staff_id'] === $own->id)
                    ->values();
            }
        }

        $coverage = $member && $service && $location
            ? $ledger->coverage($member, $service, $location->id, CarbonImmutable::createFromFormat('!Y-m-d', $this->date ?: BusinessDate::today()->toDateString(), 'UTC'))
            : null;

        return view('livewire.admin.appointments.booking-modal', [
            'member' => $member,
            'matches' => ! $this->memberFixed && mb_strlen(trim($this->memberSearch)) >= 2
                ? Member::query()->search($this->memberSearch)->orderBy('first_name')->limit(6)->get(['id', 'first_name', 'last_name', 'member_number'])
                : collect(),
            'services' => $services,
            'service' => $service,
            'staffOptions' => $staffOptions,
            'freeSlots' => $slots,
            'tz' => $location?->timezone ?: 'UTC',
            'coverage' => $coverage,
            'isInvoice' => $coverage?->type === Coverage::INVOICE,
        ]);
    }

    /**
     * El profesional sin `appointments.view-all` solo agenda en su propia agenda.
     */
    private function ownStaff(): ?Staff
    {
        return auth()->user()->can('appointments.view-all') ? null : auth()->user()->staff;
    }
}
