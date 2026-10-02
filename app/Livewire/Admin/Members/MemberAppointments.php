<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Appointments\Actions\AdjustSessionCredits;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Appointments\Models\SessionCredit;
use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Memberships\Models\Membership;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pestaña "Citas" de la ficha: próximas y pasadas, saldo de sesiones del
 * plan y ajuste manual del saldo (gerencia).
 */
class MemberAppointments extends Component
{
    use InteractsWithToasts;
    use ResolvesMember {
        mount as resolveMember;
    }

    public bool $showAdjust = false;

    public string $adjustMembershipId = '';

    public string $adjustServiceId = '';

    public int|string $adjustDelta = 1;

    public string $adjustReason = '';

    public function mount(int $memberId): void
    {
        $this->resolveMember($memberId);
        $this->authorize('viewAny', Appointment::class);
    }

    #[On('appointments-changed')]
    public function refreshAppointments(): void
    {
        // Solo vuelve a renderizar.
    }

    public function openAdjust(): void
    {
        $this->authorize(Permission::SessionCreditsAdjust->value);
        $this->authorize('view', $this->member());
        $this->reset(['adjustMembershipId', 'adjustServiceId', 'adjustReason']);
        $this->adjustDelta = 1;
        $this->resetValidation();
        $this->showAdjust = true;
    }

    public function adjust(AdjustSessionCredits $adjust): void
    {
        $member = $this->member();
        $this->authorize(Permission::SessionCreditsAdjust->value);
        $this->authorize('view', $member);

        $this->validate([
            'adjustMembershipId' => ['required', 'integer'],
            'adjustServiceId' => ['required', 'integer', Rule::exists('services', 'id')],
            'adjustDelta' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
            'adjustReason' => ['required', 'string', 'max:200'],
        ], [], ['adjustMembershipId' => 'membresía', 'adjustServiceId' => 'servicio', 'adjustDelta' => 'cantidad', 'adjustReason' => 'motivo']);

        $membership = $member->memberships()->current()->findOrFail((int) $this->adjustMembershipId);
        $service = Service::query()->findOrFail((int) $this->adjustServiceId);

        $adjust->execute($membership, $service, (int) $this->adjustDelta, $this->adjustReason, auth()->user());

        $this->showAdjust = false;
        $this->toast('Saldo de sesiones ajustado.');
    }

    public function render(SessionLedger $ledger): View
    {
        $member = $this->member();
        $user = auth()->user();

        $appointments = Appointment::query()->where('member_id', $member->id)
            ->visibleTo($user)
            ->with(['service:id,name,color', 'staff:id,first_name,last_name', 'location:id,name,timezone'])
            ->latest('starts_at')
            ->limit(60)
            ->get();

        $balances = $ledger->balances($member);
        $services = Service::query()->withTrashed()->whereIn('id', $balances->pluck('service_id'))->pluck('name', 'id');
        $memberships = Membership::query()->where('member_id', $member->id)->current()->with('plan:id,name')->get();

        return view('livewire.admin.members.appointments', [
            'member' => $member,
            'upcoming' => $appointments->filter(fn ($a) => $a->isActive() && $a->ends_at->isFuture())->sortBy('starts_at')->values(),
            'past' => $appointments->reject(fn ($a) => $a->isActive() && $a->ends_at->isFuture())->values(),
            'balances' => $balances->map(fn ($b) => (object) [
                'service' => $services[$b->service_id] ?? 'Servicio',
                'plan' => $memberships->firstWhere('id', $b->membership_id)?->plan?->name,
                'balance' => $b->balance,
            ]),
            'memberships' => $memberships->mapWithKeys(fn ($m) => [$m->id => $m->plan?->name.' ('.$m->starts_on->format('d/m/Y').')'])->all(),
            'serviceOptions' => Service::query()->orderBy('name')->pluck('name', 'id')->all(),
            'canAdjust' => $user->can(Permission::SessionCreditsAdjust->value),
            'canBook' => $user->can(Permission::AppointmentsCreate->value),
            'recentCredits' => SessionCredit::query()->where('member_id', $member->id)->with('service:id,name', 'creator:id,name')->latest('id')->limit(10)->get(),
        ]);
    }
}
