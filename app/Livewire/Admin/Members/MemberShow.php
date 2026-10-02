<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Members\Actions\ChangeMemberStatus;
use App\Domain\Members\Actions\ClearCheckinPin;
use App\Domain\Members\Actions\DeleteMember;
use App\Domain\Members\Actions\SetCheckinPin;
use App\Domain\Members\Actions\UpdateMemberPhoto;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\Member;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Ficha del cliente. Las pestañas son componentes que se autorizan por sí mismos.
 */
class MemberShow extends Component
{
    use InteractsWithToasts, WithFileUploads;

    #[Locked]
    public int $memberId;

    #[Url]
    public string $tab = 'summary';

    public bool $showPin = false;

    public string $pin = '';

    public string $pin_confirmation = '';

    public bool $showStatus = false;

    public string $newStatus = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(Member $member): void
    {
        $this->authorize('view', $member);
        $this->memberId = $member->id;

        if (! in_array($this->tab, ['summary', 'memberships', 'billing', 'checkins', 'notes', 'documents', 'consents'], true)) {
            $this->tab = 'summary';
        }
    }

    public function openPin(): void
    {
        $this->authorize('update', $this->member());
        $this->reset(['pin', 'pin_confirmation']);
        $this->resetValidation();
        $this->showPin = true;
    }

    public function savePin(SetCheckinPin $setPin): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $this->validate([
            'pin' => ['required', 'digits:4', 'confirmed'],
        ], [], ['pin' => 'PIN']);

        $setPin->execute($member, $this->pin, auth()->user());

        $this->reset(['pin', 'pin_confirmation', 'showPin']);
        $this->toast('PIN de check-in guardado.');
    }

    public function clearPin(ClearCheckinPin $clear): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $clear->execute($member, auth()->user());
        $this->toast('PIN eliminado. El cliente ya no podrá hacer check-in por teléfono.', 'warning');
    }

    public function openStatus(): void
    {
        $member = $this->member();
        $this->authorize('update', $member);
        $this->newStatus = $member->status->value;
        $this->showStatus = true;
    }

    public function saveStatus(ChangeMemberStatus $change): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $this->validate(['newStatus' => ['required', Rule::enum(MemberStatus::class)]]);

        $change->execute($member, MemberStatus::from($this->newStatus));

        $this->showStatus = false;
        $this->toast('Estado actualizado.');
    }

    public function updatedPhoto(UpdateMemberPhoto $update): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $this->validate(['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']], [], ['photo' => 'foto']);

        $update->execute($member, $this->photo, auth()->user());

        $this->photo = null;
        $this->toast('Foto actualizada.');
    }

    public function removePhoto(UpdateMemberPhoto $update): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $update->execute($member, null, auth()->user());
        $this->toast('Foto eliminada.');
    }

    public function delete(DeleteMember $delete): void
    {
        $member = $this->member();
        $this->authorize('delete', $member);

        $delete->execute($member);

        session()->flash('success', "Cliente {$member->member_number} eliminado.");
        $this->redirectRoute('admin.members.index', navigate: true);
    }

    public function render(): View
    {
        $member = $this->member()->load(['homeLocation:id,name', 'creator:id,name', 'user.staff']);
        $this->authorize('view', $member);

        return view('livewire.admin.members.show', [
            'member' => $member,
            'statuses' => MemberStatus::options(),
        ])->title($member->full_name);
    }

    private function member(): Member
    {
        return Member::query()->findOrFail($this->memberId);
    }
}
