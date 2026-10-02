<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Members\Actions\RemoveEmergencyContact;
use App\Domain\Members\Actions\SaveEmergencyContact;
use App\Domain\Members\Models\EmergencyContact;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MemberContacts extends Component
{
    use InteractsWithToasts, ResolvesMember;

    #[Locked]
    public ?int $contactId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $relationship = '';

    public string $phone = '';

    public string $alt_phone = '';

    public string $email = '';

    public bool $is_primary = false;

    public function create(): void
    {
        $this->authorize('update', $this->member());
        $this->reset(['contactId', 'name', 'relationship', 'phone', 'alt_phone', 'email', 'is_primary']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $contactId): void
    {
        $contact = $this->contact($contactId);
        $this->authorize('update', $this->member());

        $this->contactId = $contact->id;
        $this->name = $contact->name;
        $this->relationship = (string) $contact->relationship;
        $this->phone = $contact->phone;
        $this->alt_phone = (string) $contact->alt_phone;
        $this->email = (string) $contact->email;
        $this->is_primary = $contact->is_primary;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(SaveEmergencyContact $save): void
    {
        $member = $this->member();
        $this->authorize('update', $member);
        $contact = $this->contactId ? $this->contact($this->contactId) : null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s()-]{7,20}$/'],
            'alt_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s()-]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'is_primary' => ['boolean'],
        ], [], ['name' => 'nombre', 'relationship' => 'parentesco', 'phone' => 'teléfono', 'alt_phone' => 'teléfono alterno', 'email' => 'correo']);

        $save->execute($member, [
            'name' => $data['name'],
            'relationship' => $data['relationship'] ?: null,
            'phone' => $data['phone'],
            'alt_phone' => $data['alt_phone'] ?: null,
            'email' => $data['email'] ?: null,
            'is_primary' => (bool) $data['is_primary'],
        ], $contact, auth()->user());

        $this->showForm = false;
        $this->toast('Contacto de emergencia guardado.');
    }

    public function remove(int $contactId, RemoveEmergencyContact $remove): void
    {
        $contact = $this->contact($contactId);
        $this->authorize('update', $this->member());

        $remove->execute($contact, auth()->user());
        $this->toast('Contacto eliminado.');
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.admin.members.contacts', [
            'member' => $member,
            'contacts' => $member->emergencyContacts()->get(),
        ]);
    }

    private function contact(int $contactId): EmergencyContact
    {
        // Solo contactos de este cliente (ya validado por LocationScope).
        return $this->member()->emergencyContacts()->findOrFail($contactId);
    }
}
