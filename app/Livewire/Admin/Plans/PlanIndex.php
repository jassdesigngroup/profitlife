<?php

namespace App\Livewire\Admin\Plans;

use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Planes')]
class PlanIndex extends Component
{
    use InteractsWithToasts;

    public function mount(): void
    {
        $this->authorize('viewAny', MembershipPlan::class);
    }

    public function toggle(int $planId): void
    {
        $plan = MembershipPlan::query()->findOrFail($planId);
        $this->authorize('update', $plan);

        $plan->update(['is_active' => ! $plan->is_active]);
        $this->toast($plan->is_active ? "{$plan->name} disponible para la venta." : "{$plan->name} ya no se vende.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', MembershipPlan::class);

        return view('livewire.admin.plans.index', [
            'plans' => MembershipPlan::query()
                ->with('locations:id,name')
                ->withCount(['memberships as current_count' => fn ($q) => $q->withoutGlobalScopes()->whereIn('status', MembershipStatus::currentValues())])
                ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')
                ->get(),
        ]);
    }
}
