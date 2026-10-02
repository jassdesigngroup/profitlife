<?php

namespace App\Livewire\Admin\Roles;

use App\Domain\Identity\Actions\UpdateRolePermissions;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Matriz de permisos de un rol. Todos con `roles.view` la consultan; solo
 * Super Admin la modifica (RolePolicy::updatePermissions).
 */
class RoleEdit extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $roleId;

    /** @var list<string> */
    public array $permissions = [];

    public function mount(Role $role): void
    {
        $this->authorize('view', $role);
        $this->roleId = $role->id;
        $this->permissions = $role->permissions()->pluck('name')->all();
    }

    public function save(UpdateRolePermissions $update): void
    {
        $role = Role::query()->findOrFail($this->roleId);
        $this->authorize('updatePermissions', $role);

        $this->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ]);

        $update->execute($role, $this->permissions, auth()->user());

        $this->toast('Permisos actualizados.');
    }

    public function render(): View
    {
        $role = Role::query()->findOrFail($this->roleId);
        $roleName = RoleName::tryFrom($role->name);

        return view('livewire.admin.roles.edit', [
            'role' => $role,
            'label' => $roleName?->label() ?? $role->name,
            'groups' => Permission::grouped(),
            'canEdit' => auth()->user()->can('updatePermissions', $role),
        ])->title('Rol: '.($roleName?->label() ?? $role->name));
    }
}
