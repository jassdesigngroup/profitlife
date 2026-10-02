<?php

namespace App\Livewire\Admin;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Enums\MembershipStatus as PlanMembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Inicio del panel. Los indicadores de módulos futuros (citas) son
 * tarjetas de marcador hasta sus fases.
 */
#[Title('Inicio')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize(Permission::DashboardView->value);
    }

    public function render(CurrentLocation $current): View
    {
        $user = auth()->user();
        $locationId = $current->id();

        $stats = [
            'members' => $user->can(Permission::MembersView->value)
                ? Member::query()->where('status', MemberStatus::Active)->inLocation($locationId)->count()
                : null,
            'locations' => $user->can(Permission::LocationsView->value)
                ? Location::query()->active()->inLocation($locationId)->count()
                : null,
            'rooms' => $user->can(Permission::RoomsView->value)
                ? Room::query()->where('is_active', true)->inLocation($locationId)->count()
                : null,
            'staff' => $user->can(Permission::StaffView->value)
                ? Staff::query()->where('status', StaffStatus::Active)->inLocation($locationId)->count()
                : null,
        ];

        $tz = app(Settings::class)->displayTimezone();
        $monthStart = now($tz)->startOfMonth()->utc();

        $today = now($tz);

        $kpis = [
            'checkins' => $user->can(Permission::CheckInsView->value)
                ? CheckIn::query()->inLocation($locationId)->accepted()
                    ->whereBetween('checked_in_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
                    ->count()
                : null,
            'memberships' => $user->can(Permission::MembershipsView->value)
                ? Membership::query()->where('status', PlanMembershipStatus::Active)
                    ->when($locationId, fn ($q) => $q->whereHas('member', fn ($m) => $m->inLocation($locationId)))
                    ->count()
                : null,
            'income' => $user->can(Permission::PaymentsView->value)
                ? Money::ofCents((int) Payment::query()->inLocation($locationId)->where('status', PaymentStatus::Paid)->where('paid_at', '>=', $monthStart)->sum('amount_cents'))->format()
                : null,
            'overdue' => $user->can(Permission::PaymentsView->value)
                ? Invoice::query()->inLocation($locationId)->open()->whereDate('due_on', '<', BusinessDate::today())->count()
                : null,
        ];

        /** @var Collection<int, Activity> $activity */
        $activity = $user->can(Permission::AuditView->value)
            ? Activity::query()->with('causer')->latest('id')->limit(6)->get()
            : collect();

        return view('livewire.admin.dashboard', [
            'user' => $user,
            'currentLocation' => $current->location(),
            'stats' => $stats,
            'kpis' => $kpis,
            'activity' => $activity,
        ]);
    }
}
