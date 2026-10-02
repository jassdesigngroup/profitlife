<?php

namespace App\Livewire\Admin\CheckIns;

use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Members\Models\Member;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\CheckIns\Concerns\RegistersCheckIns;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Asistencia del día por sede (aceptados y rechazados) y registro manual
 * de ingresos desde recepción.
 */
#[Title('Asistencia')]
class CheckInIndex extends Component
{
    use InteractsWithToasts, RegistersCheckIns, WithPagination;

    #[Url(as: 'fecha')]
    public string $date = '';

    #[Url(as: 'resultado')]
    public string $result = '';

    #[Url(as: 'medio')]
    public string $method = '';

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CheckIn::class);
        $this->date = $this->date ?: BusinessDate::today()->toDateString();
        $this->pickDeskLocation();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['date', 'result', 'method'], true)) {
            $this->resetPage();
        }
    }

    public function checkIn(int $memberId): void
    {
        $this->authorize('viewAny', CheckIn::class);
        $member = Member::query()->findOrFail($memberId);

        $this->registerCheckInFor($member);
        $this->search = '';
    }

    public function render(CurrentLocation $current, Settings $settings): View
    {
        $this->authorize('viewAny', CheckIn::class);

        $valid = validator(
            ['date' => $this->date, 'result' => $this->result, 'method' => $this->method],
            [
                'date' => ['required', 'date_format:Y-m-d'],
                'result' => ['nullable', Rule::enum(CheckInResult::class)],
                'method' => ['nullable', Rule::enum(CheckInMethod::class)],
            ],
        )->valid();

        $tz = $settings->displayTimezone();
        $day = CarbonImmutable::parse($valid['date'] ?? BusinessDate::today()->toDateString(), $tz);

        $base = CheckIn::query()
            ->inLocation($current->id())
            ->whereBetween('checked_in_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()]);

        $stats = (clone $base)->selectRaw('result, COUNT(*) as total, COUNT(DISTINCT member_id) as members')->groupBy('result')->get()->keyBy(fn ($r) => $r->result->value);

        $checkIns = (clone $base)
            ->when(($valid['result'] ?? '') !== '', fn ($q) => $q->where('result', $valid['result']))
            ->when(($valid['method'] ?? '') !== '', fn ($q) => $q->where('method', $valid['method']))
            ->with(['member:id,first_name,last_name,member_number', 'location:id,name', 'kioskDevice:id,name', 'registeredBy:id,name', 'membership.plan:id,name'])
            ->latest('checked_in_at')
            ->latest('id')
            ->paginate(30);

        $canRegister = auth()->user()->can('check-ins.create') && $this->deskLocations->isNotEmpty();

        return view('livewire.admin.check-ins.index', [
            'checkIns' => $checkIns,
            'accepted' => (int) ($stats['accepted']->total ?? 0),
            'uniqueMembers' => (int) ($stats['accepted']->members ?? 0),
            'rejected' => (int) ($stats['rejected']->total ?? 0),
            'results' => CheckInResult::options(),
            'methods' => CheckInMethod::options(),
            'canRegister' => $canRegister,
            'matches' => $canRegister && mb_strlen(trim($this->search)) >= 2
                ? Member::query()->search($this->search)->orderBy('first_name')->limit(8)->get(['id', 'first_name', 'last_name', 'member_number', 'status'])
                : collect(),
        ]);
    }
}
