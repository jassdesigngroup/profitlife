<?php

namespace App\Livewire\Admin\Members\Concerns;

use App\Domain\Members\Models\Member;
use Livewire\Attributes\Locked;

/**
 * Pestañas de la ficha del cliente: el id va bloqueado y el cliente se
 * vuelve a cargar en cada petición con LocationScope.
 */
trait ResolvesMember
{
    #[Locked]
    public int $memberId;

    public function mount(int $memberId): void
    {
        $this->memberId = $memberId;
        $this->authorize('view', $this->member());
    }

    protected function member(): Member
    {
        return Member::query()->findOrFail($this->memberId);
    }
}
