<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Locations\Models\Location;
use App\Domain\Members\Actions\CreateMember;
use App\Domain\Members\Actions\UpdateMember;
use App\Domain\Members\DTOs\MemberData;
use App\Domain\Members\Enums\Gender;
use App\Domain\Members\Models\Member;
use App\Domain\Shared\Enums\DocumentType;
use App\Support\Locations\CurrentLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MemberForm extends Component
{
    #[Locked]
    public ?int $memberId = null;

    public string $home_location_id = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $document_type = '';

    public string $document_number = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $email = '';

    public string $phone = '';

    public string $address_line = '';

    public string $city = '';

    public string $department = '';

    public string $joined_on = '';

    public function mount(?Member $member = null): void
    {
        if ($member?->exists) {
            $this->authorize('update', $member);
            $this->memberId = $member->id;
            $this->home_location_id = (string) $member->home_location_id;
            $this->first_name = $member->first_name;
            $this->last_name = $member->last_name;
            $this->document_type = $member->document_type?->value ?? '';
            $this->document_number = (string) $member->document_number;
            $this->birth_date = $member->birth_date?->format('Y-m-d') ?? '';
            $this->gender = $member->gender?->value ?? '';
            $this->email = (string) $member->email;
            $this->phone = (string) $member->phone;
            $this->address_line = (string) $member->address_line;
            $this->city = (string) $member->city;
            $this->department = (string) $member->department;
            $this->joined_on = $member->joined_on->format('Y-m-d');

            return;
        }

        $this->authorize('create', Member::class);
        $this->joined_on = now()->toDateString();

        $locations = $this->locations;
        $default = app(CurrentLocation::class)->id() ?? ($locations->count() === 1 ? $locations->first()->id : null);
        $this->home_location_id = (string) ($default ?? '');
        if ($default !== null) {
            $location = $locations->firstWhere('id', $default);
            $this->city = (string) $location?->city;
            $this->department = (string) $location?->department;
        }
    }

    /**
     * Sedes activas del alcance del usuario (LocationScope).
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->active()->orderBy('name')->get(['id', 'name', 'city', 'department']);
    }

    #[Computed]
    public function age(): ?int
    {
        if ($this->birth_date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->birth_date)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->birth_date)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $current = $this->memberId ? Member::query()->find($this->memberId)?->home_location_id : null;

        return [
            'home_location_id' => ['required', 'integer', Rule::in([...$this->locations->pluck('id')->all(), ...array_filter([$current])])],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'document_type' => ['nullable', 'required_with:document_number', Rule::enum(DocumentType::class)],
            'document_number' => ['nullable', 'required_with:document_type', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s()-]{7,20}$/'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'joined_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'home_location_id' => 'sede', 'first_name' => 'nombres', 'last_name' => 'apellidos',
            'document_type' => 'tipo de documento', 'document_number' => 'número de documento',
            'birth_date' => 'fecha de nacimiento', 'gender' => 'género', 'email' => 'correo', 'phone' => 'teléfono',
            'address_line' => 'dirección', 'city' => 'ciudad', 'department' => 'departamento', 'joined_on' => 'fecha de ingreso',
        ];
    }

    public function save(CreateMember $create, UpdateMember $update): void
    {
        $member = $this->memberId ? Member::query()->findOrFail($this->memberId) : null;
        $member ? $this->authorize('update', $member) : $this->authorize('create', Member::class);

        $data = $this->validate();

        $dto = new MemberData(
            homeLocationId: (int) $data['home_location_id'],
            firstName: trim($data['first_name']),
            lastName: trim($data['last_name']),
            documentType: DocumentType::tryFrom((string) $data['document_type']),
            documentNumber: $data['document_number'] ?: null,
            birthDate: $data['birth_date'] ?: null,
            gender: Gender::tryFrom((string) $data['gender']),
            email: $data['email'] ?: null,
            phone: $data['phone'] ?: null,
            addressLine: $data['address_line'] ?: null,
            city: $data['city'] ?: null,
            department: $data['department'] ?: null,
            joinedOn: $data['joined_on'],
        );

        if ($member) {
            $update->execute($member, $dto, auth()->user());
            session()->flash('success', 'Datos del cliente actualizados.');
        } else {
            $member = $create->execute($dto, auth()->user());
            session()->flash('success', "Cliente {$member->member_number} registrado.");
        }

        $this->redirectRoute('admin.members.show', ['member' => $member->id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.members.form', [
            'editing' => $this->memberId !== null,
            'documentTypes' => DocumentType::options(),
            'genders' => Gender::options(),
        ])->title($this->memberId ? 'Editar cliente' : 'Nuevo cliente');
    }
}
