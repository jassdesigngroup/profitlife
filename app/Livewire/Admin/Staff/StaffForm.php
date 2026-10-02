<?php

namespace App\Livewire\Admin\Staff;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Staff\Actions\CreateStaffMember;
use App\Domain\Staff\Actions\UpdateStaffMember;
use App\Domain\Staff\DTOs\StaffData;
use App\Domain\Staff\Models\Staff;
use App\Support\Scopes\LocationScope;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StaffForm extends Component
{
    #[Locked]
    public ?int $staffId = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $document_type = '';

    public string $document_number = '';

    public string $phone = '';

    public string $job_title = '';

    public string $professional_license = '';

    public string $calendar_color = '#FD540D';

    public bool $is_bookable = false;

    public string $hired_on = '';

    /** @var list<string> */
    public array $roles = [];

    /** @var list<int|string> */
    public array $locationIds = [];

    public string $primaryLocationId = '';

    public function mount(?Staff $staff = null): void
    {
        if ($staff?->exists) {
            $this->authorize('update', $staff);
            $this->staffId = $staff->id;
            $staff->load(['user', 'locations' => fn ($q) => $q->withoutGlobalScope(LocationScope::class)]);

            $this->first_name = $staff->first_name;
            $this->last_name = $staff->last_name;
            $this->email = $staff->user->email;
            $this->document_type = $staff->document_type?->value ?? '';
            $this->document_number = (string) $staff->document_number;
            $this->phone = (string) $staff->phone;
            $this->job_title = (string) $staff->job_title;
            $this->professional_license = (string) $staff->professional_license;
            $this->calendar_color = $staff->calendar_color ?? '#FD540D';
            $this->is_bookable = $staff->is_bookable;
            $this->hired_on = $staff->hired_on?->format('Y-m-d') ?? '';
            $this->roles = $staff->user->getRoleNames()->intersect(array_map(fn ($r) => $r->value, RoleName::staffRoles()))->values()->all();
            $this->locationIds = $staff->locations->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->primaryLocationId = (string) ($staff->locations->firstWhere('pivot.is_primary', true)?->id ?? '');

            return;
        }

        $this->authorize('create', Staff::class);

        $mine = $this->assignableLocations;
        if ($mine->count() === 1) {
            $this->locationIds = [(string) $mine->first()->id];
            $this->primaryLocationId = (string) $mine->first()->id;
        }
    }

    /**
     * Sedes que el usuario puede asignar (LocationScope ya las limita).
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function assignableLocations(): Collection
    {
        return Location::query()->orderBy('name')->get(['id', 'name', 'is_active']);
    }

    /**
     * @return list<RoleName>
     */
    #[Computed]
    public function assignableRoles(): array
    {
        return RoleName::assignableBy(auth()->user());
    }

    #[Computed]
    public function canManageRoles(): bool
    {
        $user = auth()->user();

        if ($this->staffId === null) {
            return $user->can(Permission::UsersManageRoles->value);
        }

        return $user->can('manageRoles', $this->staff());
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $assignableRoles = array_map(fn (RoleName $r) => $r->value, $this->assignableRoles);
        $assignableLocations = $this->assignableLocations->pluck('id')->all();
        $existingLocations = $this->staffId ? $this->staff()->locationIds() : [];

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => array_filter([
                'required', 'email', 'max:190',
                $this->staffId ? Rule::unique('users', 'email')->ignore($this->staff()->user_id) : null,
            ]),
            'document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'document_number' => ['nullable', 'required_with:document_type', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s()-]+$/'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'professional_license' => ['nullable', 'string', 'max:50'],
            'calendar_color' => ['nullable', 'hex_color', 'size:7'],
            'is_bookable' => ['boolean'],
            'hired_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'roles' => $this->canManageRoles ? ['required', 'array', 'min:1'] : ['array'],
            'roles.*' => ['string', Rule::in($assignableRoles)],
            'locationIds' => ['required', 'array', 'min:1'],
            'locationIds.*' => ['integer', Rule::in([...$assignableLocations, ...$existingLocations])],
            'primaryLocationId' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'first_name' => 'nombres', 'last_name' => 'apellidos', 'email' => 'correo', 'document_type' => 'tipo de documento',
            'document_number' => 'número de documento', 'phone' => 'teléfono', 'job_title' => 'cargo',
            'professional_license' => 'tarjeta profesional', 'calendar_color' => 'color', 'hired_on' => 'fecha de ingreso',
            'roles' => 'roles', 'roles.*' => 'rol', 'locationIds' => 'sedes', 'locationIds.*' => 'sede',
        ];
    }

    public function save(CreateStaffMember $create, UpdateStaffMember $update): void
    {
        $staff = $this->staffId ? $this->staff() : null;
        $staff ? $this->authorize('update', $staff) : $this->authorize('create', Staff::class);

        $data = $this->validate();

        $dto = new StaffData(
            firstName: trim($data['first_name']),
            lastName: trim($data['last_name']),
            email: $data['email'],
            documentType: DocumentType::tryFrom((string) $data['document_type']),
            documentNumber: $data['document_number'] ?: null,
            phone: $data['phone'] ?: null,
            jobTitle: $data['job_title'] ?: null,
            professionalLicense: $data['professional_license'] ?: null,
            calendarColor: $data['calendar_color'] ? strtoupper($data['calendar_color']) : null,
            isBookable: (bool) $data['is_bookable'],
            hiredOn: $data['hired_on'] ?: null,
            roles: array_map(fn ($r) => RoleName::from($r), $data['roles'] ?? []),
            locationIds: array_map('intval', $data['locationIds']),
            primaryLocationId: $data['primaryLocationId'] ? (int) $data['primaryLocationId'] : null,
        );

        if ($staff) {
            $update->execute($staff, $dto, auth()->user(), syncRoles: $this->canManageRoles);
            session()->flash('success', 'Datos del staff actualizados.');
        } else {
            $created = $create->execute($dto, auth()->user());
            session()->flash('success', $created->user->hasAcceptedInvitation()
                ? "{$created->full_name} se añadió al staff con su cuenta existente."
                : "Invitación enviada a {$created->user->email}.");
        }

        $this->redirectRoute('admin.staff.index', navigate: true);
    }

    public function render(): View
    {
        $outside = [];

        if ($this->staffId) {
            $assignable = $this->assignableLocations->pluck('id')->all();
            $outside = $this->staff()->locations()->withoutGlobalScope(LocationScope::class)
                ->whereNotIn('locations.id', $assignable)->pluck('name')->all();
        }

        return view('livewire.admin.staff.form', [
            'editing' => $this->staffId !== null,
            'documentTypes' => DocumentType::options(),
            'outsideLocations' => $outside,
        ])->title($this->staffId ? 'Editar staff' : 'Nuevo staff');
    }

    private function staff(): Staff
    {
        return Staff::query()->with('user')->findOrFail($this->staffId);
    }
}
