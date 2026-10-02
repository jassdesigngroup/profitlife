<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Actions\CancelMembership;
use App\Domain\Memberships\Actions\ChangeMembershipStatus;
use App\Domain\Memberships\Actions\FreezeMembership;
use App\Domain\Memberships\Actions\SellMembership;
use App\Domain\Memberships\Actions\SetAutoRenew;
use App\Domain\Memberships\Actions\UnfreezeMembership;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Concerns\ParsesMoney;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MemberMemberships extends Component
{
    use InteractsWithToasts, ParsesMoney, ResolvesMember;

    // Venta
    public bool $showSell = false;

    public string $planId = '';

    public string $locationId = '';

    public string $startsOn = '';

    public string $discount = '';

    public string $discountReason = '';

    public bool $payNow = true;

    public string $payAmount = '';

    public string $payMethod = 'cash';

    public string $payReference = '';

    // Acciones sobre una membresía
    #[Locked]
    public ?int $targetId = null;

    public bool $showFreeze = false;

    public string $resumesOn = '';

    public string $freezeReason = '';

    public bool $showCancel = false;

    public string $cancelReason = '';

    public bool $showStatus = false;

    public string $statusReason = '';

    public function openSell(): void
    {
        $member = $this->member();
        $this->authorize('create', [Membership::class, $member]);

        $this->resetValidation();
        $this->reset(['planId', 'discount', 'discountReason', 'payReference']);
        $this->payNow = true;
        $this->payMethod = PaymentMethod::Cash->value;

        $locations = $this->sellLocations;
        $preferred = app(CurrentLocation::class)->id() ?? $member->home_location_id;
        $this->locationId = (string) ($locations->firstWhere('id', $preferred)?->id ?? $locations->first()?->id ?? '');

        $current = $member->memberships()->current()->orderByDesc('ends_on')->first();
        $this->startsOn = ($current?->ends_on?->addDay() ?? BusinessDate::today())->toDateString();

        $this->showSell = true;
    }

    public function updatedPlanId(): void
    {
        $this->payAmount = $this->centsToPesos($this->quote()['total']);
    }

    public function updatedDiscount(): void
    {
        $this->payAmount = $this->centsToPesos($this->quote()['total']);
    }

    public function sell(SellMembership $sell): void
    {
        $member = $this->member();
        $this->authorize('create', [Membership::class, $member]);

        $this->validate([
            'planId' => ['required', 'integer'],
            'locationId' => ['required', 'integer', Rule::in($this->sellLocations->pluck('id')->all())],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'discount' => ['nullable', 'regex:/^[\d.,\s]*$/'],
            'discountReason' => ['nullable', 'string', 'max:200'],
            'payNow' => ['boolean'],
            'payAmount' => [Rule::requiredIf($this->payNow), 'nullable', 'regex:/^[\d.,\s]*$/'],
            'payMethod' => [Rule::requiredIf($this->payNow), Rule::in(array_keys(PaymentMethod::manualOptions()))],
            'payReference' => ['nullable', 'string', 'max:100'],
        ], [], [
            'planId' => 'plan', 'locationId' => 'sede', 'startsOn' => 'inicio', 'discount' => 'descuento',
            'payAmount' => 'valor pagado', 'payMethod' => 'medio de pago', 'payReference' => 'referencia',
        ]);

        $plan = MembershipPlan::query()->findOrFail((int) $this->planId);
        $location = Location::query()->findOrFail((int) $this->locationId);

        $sell->execute(
            $member,
            $plan,
            $location,
            CarbonImmutable::createFromFormat('!Y-m-d', $this->startsOn, 'UTC'),
            auth()->user(),
            $this->pesosToCents($this->discount),
            $this->discountReason ?: null,
            $this->payNow ? [
                'amount_cents' => $this->pesosToCents($this->payAmount),
                'method' => PaymentMethod::from($this->payMethod),
                'reference' => $this->payReference ?: null,
            ] : null,
        );

        $this->showSell = false;
        $this->dispatch('member-billing-changed');
        $this->toast("Membresía {$plan->name} vendida.");
    }

    public function openFreeze(int $membershipId): void
    {
        $membership = $this->membership($membershipId);
        $this->authorize('freeze', $membership);
        $this->targetId = $membership->id;
        $this->reset(['resumesOn', 'freezeReason']);
        $this->resetValidation();
        $this->showFreeze = true;
    }

    public function freeze(FreezeMembership $freeze): void
    {
        $membership = $this->membership((int) $this->targetId);
        $this->authorize('freeze', $membership);

        $this->validate([
            'resumesOn' => ['nullable', 'date_format:Y-m-d'],
            'freezeReason' => ['nullable', 'string', 'max:200'],
        ], [], ['resumesOn' => 'fecha de reanudación', 'freezeReason' => 'motivo']);

        $freeze->execute(
            $membership,
            $this->resumesOn ? CarbonImmutable::createFromFormat('!Y-m-d', $this->resumesOn, 'UTC') : null,
            $this->freezeReason ?: null,
            auth()->user(),
        );

        $this->showFreeze = false;
        $this->toast('Membresía congelada.');
    }

    public function unfreeze(int $membershipId, UnfreezeMembership $unfreeze): void
    {
        $membership = $this->membership($membershipId);
        $this->authorize('freeze', $membership);

        $unfreeze->execute($membership, auth()->user());
        $this->toast('Membresía reanudada. Su vencimiento se corrió por los días congelados.');
    }

    public function openCancel(int $membershipId): void
    {
        $membership = $this->membership($membershipId);
        $this->authorize('cancel', $membership);
        $this->targetId = $membership->id;
        $this->reset('cancelReason');
        $this->resetValidation();
        $this->showCancel = true;
    }

    public function cancel(CancelMembership $cancel): void
    {
        $membership = $this->membership((int) $this->targetId);
        $this->authorize('cancel', $membership);

        $this->validate(['cancelReason' => ['required', 'string', 'max:255']], [], ['cancelReason' => 'motivo']);

        $cancel->execute($membership, $this->cancelReason, auth()->user());

        $this->showCancel = false;
        $this->dispatch('member-billing-changed');
        $this->toast('Membresía cancelada.', 'warning');
    }

    public function openStatus(int $membershipId): void
    {
        $membership = $this->membership($membershipId);
        $this->authorize('update', $membership);
        $this->targetId = $membership->id;
        $this->reset('statusReason');
        $this->resetValidation();
        $this->showStatus = true;
    }

    public function changeStatus(ChangeMembershipStatus $change): void
    {
        $membership = $this->membership((int) $this->targetId);
        $this->authorize('update', $membership);

        $this->validate(['statusReason' => ['required', 'string', 'max:255']], [], ['statusReason' => 'motivo']);

        $to = $membership->status === MembershipStatus::Active ? MembershipStatus::Suspended : MembershipStatus::Active;
        $change->execute($membership, $to, $this->statusReason, auth()->user());

        $this->showStatus = false;
        $this->toast($to === MembershipStatus::Active ? 'Membresía reactivada.' : 'Membresía suspendida.');
    }

    public function toggleAutoRenew(int $membershipId, SetAutoRenew $set): void
    {
        $membership = $this->membership($membershipId);
        $this->authorize('update', $membership);

        $set->execute($membership, ! $membership->auto_renews);
        $this->toast($membership->auto_renews ? 'Renovación automática activada.' : 'Renovación automática desactivada.');
    }

    /**
     * Sedes del alcance del usuario donde puede vender.
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function sellLocations(): Collection
    {
        return Location::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array{plan: ?MembershipPlan, fee: int, discount: int, total: int, isFirst: bool}
     */
    private function quote(): array
    {
        $plan = $this->planId !== '' ? MembershipPlan::query()->find((int) $this->planId) : null;
        $isFirst = ! $this->member()->memberships()->withoutGlobalScopes()->exists();
        $fee = $plan && $isFirst ? $plan->enrollment_fee_cents : 0;
        $discount = min($this->pesosToCents($this->discount), $plan?->price_cents ?? 0);

        return [
            'plan' => $plan,
            'fee' => $fee,
            'discount' => $discount,
            'total' => $plan ? $plan->price_cents - $discount + $fee : 0,
            'isFirst' => $isFirst,
        ];
    }

    public function render(): View
    {
        $member = $this->member();
        $memberships = $member->memberships()
            ->with(['plan', 'purchaseLocation:id,name', 'freezes', 'statusHistories.changer:id,name'])
            ->orderByDesc('starts_on')
            ->get();

        $locationId = (int) $this->locationId;
        $plans = $locationId > 0
            ? MembershipPlan::query()->availableAt($locationId)->with('locations:id')->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        $quote = $this->quote();

        return view('livewire.admin.members.memberships', [
            'member' => $member,
            'current' => $memberships->first(fn (Membership $m) => $m->status->isCurrent()),
            'memberships' => $memberships,
            'plans' => $plans,
            'quote' => $quote,
            'money' => fn (int $cents) => Money::ofCents($cents)->format(),
            'methods' => PaymentMethod::manualOptions(),
            'canDiscount' => auth()->user()->can(Permission::PaymentsDiscount->value),
            'target' => $this->targetId ? $memberships->firstWhere('id', $this->targetId) : null,
        ]);
    }

    private function membership(int $membershipId): Membership
    {
        return $this->member()->memberships()->findOrFail($membershipId);
    }
}
