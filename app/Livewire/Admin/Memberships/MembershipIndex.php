<?php

namespace App\Livewire\Admin\Memberships;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seguimiento de membresías: por vencer, en mora, congeladas, etc.
 */
#[Title('Membresías')]
class MembershipIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'current';

    #[Url]
    public string $status = '';

    #[Url]
    public string $plan = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Membership::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['view', 'status', 'plan', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(CurrentLocation $current): View
    {
        $this->authorize('viewAny', Membership::class);

        $filters = validator(
            ['view' => $this->view, 'status' => $this->status, 'plan' => $this->plan],
            [
                'view' => ['required', Rule::in(['current', 'expiring', 'overdue', 'all'])],
                'status' => ['nullable', Rule::enum(MembershipStatus::class)],
                'plan' => ['nullable', 'integer'],
            ],
        )->valid();

        $today = BusinessDate::today();
        $locationId = $current->id();

        $memberships = Membership::query()
            ->with(['member:id,first_name,last_name,member_number,phone', 'plan:id,name', 'purchaseLocation:id,name'])
            ->when($locationId, fn (Builder $q) => $q->whereHas('member', fn (Builder $m) => $m->inLocation($locationId)))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('member', fn (Builder $m) => $m->search($this->search)))
            ->when(($filters['plan'] ?? '') !== '', fn (Builder $q) => $q->where('membership_plan_id', $filters['plan']))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(($filters['view'] ?? 'current') === 'current' && ($filters['status'] ?? '') === '', fn (Builder $q) => $q->current())
            ->when(($filters['view'] ?? '') === 'expiring', fn (Builder $q) => $q
                ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Suspended])
                ->whereDate('ends_on', '>=', $today)->whereDate('ends_on', '<=', $today->addDays(7)))
            ->when(($filters['view'] ?? '') === 'overdue', fn (Builder $q) => $q
                ->current()
                ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('invoice_items')
                    ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                    ->whereColumn('invoice_items.billable_id', 'memberships.id')
                    ->where('invoice_items.billable_type', 'membership')
                    ->whereIn('invoices.status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
                    ->whereNull('invoices.deleted_at')
                    ->whereDate('invoices.due_on', '<', $today)))
            ->orderBy('ends_on')
            ->paginate(25);

        return view('livewire.admin.memberships.index', [
            'memberships' => $memberships,
            'plans' => MembershipPlan::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => MembershipStatus::options(),
        ]);
    }
}
