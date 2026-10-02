<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Members\Actions\AddMemberNote;
use App\Domain\Members\Actions\DeleteMemberNote;
use App\Domain\Members\Actions\ToggleNotePin;
use App\Domain\Members\Actions\UpdateMemberNote;
use App\Domain\Members\Models\MemberNote;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Notas administrativas. La información clínica va en el módulo de fisioterapia.
 */
class MemberNotes extends Component
{
    use InteractsWithToasts, ResolvesMember;

    public string $body = '';

    public bool $pinned = false;

    #[Locked]
    public ?int $editingId = null;

    public string $editingBody = '';

    public function add(AddMemberNote $add): void
    {
        $member = $this->member();
        $this->authorize('update', $member);

        $this->validate(['body' => ['required', 'string', 'max:5000'], 'pinned' => ['boolean']], [], ['body' => 'nota']);

        $add->execute($member, trim($this->body), $this->pinned, auth()->user());

        $this->reset(['body', 'pinned']);
        $this->toast('Nota añadida.');
    }

    public function edit(int $noteId): void
    {
        $note = $this->note($noteId);
        $this->authorize('update', $note);

        $this->editingId = $note->id;
        $this->editingBody = $note->body;
    }

    public function saveEdit(UpdateMemberNote $update): void
    {
        $note = $this->note((int) $this->editingId);
        $this->authorize('update', $note);

        $this->validate(['editingBody' => ['required', 'string', 'max:5000']], [], ['editingBody' => 'nota']);

        $update->execute($note, trim($this->editingBody), auth()->user());

        $this->reset(['editingId', 'editingBody']);
        $this->toast('Nota actualizada.');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editingBody']);
    }

    public function togglePin(int $noteId, ToggleNotePin $toggle): void
    {
        $note = $this->note($noteId);
        $this->authorize('pin', $note);

        $toggle->execute($note);
    }

    public function delete(int $noteId, DeleteMemberNote $delete): void
    {
        $note = $this->note($noteId);
        $this->authorize('delete', $note);

        $delete->execute($note, auth()->user());
        $this->toast('Nota eliminada.');
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.admin.members.notes', [
            'member' => $member,
            'notes' => $member->notes()->with('author:id,name')->get(),
        ]);
    }

    private function note(int $noteId): MemberNote
    {
        return $this->member()->notes()->findOrFail($noteId);
    }
}
