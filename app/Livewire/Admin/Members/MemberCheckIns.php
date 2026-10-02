<?php

namespace App\Livewire\Admin\Members;

use App\Domain\CheckIns\Actions\IssueAccessCode;
use App\Domain\CheckIns\Actions\RevokeAccessCode;
use App\Domain\CheckIns\Actions\SendAccessCode;
use App\Domain\CheckIns\Enums\CredentialType;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\CheckIns\Services\VisitUsage;
use App\Livewire\Admin\CheckIns\Concerns\RegistersCheckIns;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use App\Support\BusinessDate;
use App\Support\QrCode;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Pestaña "Asistencia" de la ficha: código QR de acceso, registro manual
 * de ingreso, uso del plan e historial de ingresos.
 */
class MemberCheckIns extends Component
{
    use InteractsWithToasts, RegistersCheckIns;
    use ResolvesMember {
        mount as resolveMember;
    }

    public function mount(int $memberId): void
    {
        $this->resolveMember($memberId);
        $this->authorize('viewAny', CheckIn::class);
        $this->pickDeskLocation($this->member());
    }

    public function register(): void
    {
        $this->authorize('viewAny', CheckIn::class);
        $this->registerCheckInFor($this->member());
    }

    public function issueCode(IssueAccessCode $issue): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $issue->execute($member, auth()->user());
        $this->toast('Código de acceso generado. El anterior dejó de funcionar.');
    }

    public function revokeCode(RevokeAccessCode $revoke): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $credential = $this->credential();
        if ($credential !== null) {
            $revoke->execute($credential, auth()->user());
        }

        $this->toast('Código de acceso anulado.', 'warning');
    }

    public function sendCode(SendAccessCode $send): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $credential = $this->credential();
        if ($credential === null) {
            $this->addError('access', 'Primero genere el código.');

            return;
        }

        $send->execute($member, $credential, auth()->user());
        $this->toast('Código enviado a '.$member->email.'.');
    }

    public function render(VisitUsage $visits): View
    {
        $member = $this->member();
        $canManageCode = auth()->user()->can('update', $member);
        $credential = $canManageCode ? $this->credential() : null;

        $membership = $member->currentMembership();
        $usage = $membership?->plan?->visit_limit_count !== null ? $visits->for($membership, BusinessDate::today()) : null;

        return view('livewire.admin.members.check-ins', [
            'member' => $member,
            'canManageCode' => $canManageCode,
            'credential' => $credential,
            'qr' => $credential ? QrCode::svg($credential->token_encrypted, 180) : null,
            'membership' => $membership,
            'usage' => $usage,
            'checkIns' => CheckIn::query()->where('member_id', $member->id)
                ->with(['location:id,name', 'kioskDevice:id,name', 'registeredBy:id,name'])
                ->latest('checked_in_at')->latest('id')
                ->limit(50)->get(),
            'canRegister' => auth()->user()->can('check-ins.create') && $this->deskLocations->isNotEmpty(),
        ]);
    }

    private function credential(): ?MemberAccessCredential
    {
        return MemberAccessCredential::query()
            ->where('member_id', $this->member()->id)
            ->where('type', CredentialType::Qr)
            ->usable()
            ->latest('id')
            ->first();
    }
}
