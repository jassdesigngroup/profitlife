<?php

namespace App\Livewire\Admin\Roles;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Roles y permisos')]
class RoleIndex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['permissions', 'users'])->get()
            ->sortBy(fn (Role $r) => array_search(RoleName::tryFrom($r->name), RoleName::cases(), true))
            ->values();

        return view('livewire.admin.roles.index', [
            'roles' => $roles,
            'totalPermissions' => count(Permission::cases()),
        ]);
    }
}
