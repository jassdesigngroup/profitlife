<?php

namespace App\Livewire\Admin;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Support\Locations\CurrentLocation;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Inicio del panel. Los indicadores operativos (check-ins, membresías,
 * citas, ingresos) son tarjetas de marcador hasta sus fases.
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

        /** @var Collection<int, Activity> $activity */
        $activity = $user->can(Permission::AuditView->value)
            ? Activity::query()->with('causer')->latest('id')->limit(6)->get()
            : collect();

        return view('livewire.admin.dashboard', [
            'user' => $user,
            'currentLocation' => $current->location(),
            'stats' => $stats,
            'activity' => $activity,
        ]);
    }
}
