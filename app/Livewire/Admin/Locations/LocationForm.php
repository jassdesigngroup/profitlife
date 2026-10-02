<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\Locations\Actions\CreateLocation;
use App\Domain\Locations\Actions\UpdateLocation;
use App\Domain\Locations\DTOs\LocationData;
use App\Domain\Locations\Models\Location;
use DateTimeZone;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LocationForm extends Component
{
    #[Locked]
    public ?int $locationId = null;

    public string $name = '';

    public string $slug = '';

    public string $code = '';

    public string $address_line = '';

    public string $city = '';

    public string $department = '';

    public string $phone = '';

    public string $email = '';

    public string $timezone = '';

    public function mount(?Location $location = null): void
    {
        if ($location?->exists) {
            $this->authorize('update', $location);
            $this->locationId = $location->id;
            $this->fill($location->only(['name', 'slug', 'code', 'address_line', 'city', 'department', 'timezone']));
            $this->phone = (string) $location->phone;
            $this->email = (string) $location->email;

            return;
        }

        $this->authorize('create', Location::class);
        $this->timezone = (string) config('profitlife.display_timezone');
    }

    public function updatedName(string $value): void
    {
        if ($this->locationId === null) {
            $this->slug = Str::slug($value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $unique = fn (string $column) => Rule::unique('locations', $column)->ignore($this->locationId);

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash:ascii', $unique('slug')],
            'code' => ['required', 'string', 'max:10', 'alpha_num:ascii', $unique('code')],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s()-]+$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre', 'slug' => 'identificador', 'code' => 'código', 'address_line' => 'dirección',
            'city' => 'ciudad', 'department' => 'departamento', 'phone' => 'teléfono', 'email' => 'correo',
            'timezone' => 'zona horaria',
        ];
    }

    public function save(CreateLocation $create, UpdateLocation $update): void
    {
        $location = $this->locationId !== null ? Location::query()->findOrFail($this->locationId) : null;
        $location ? $this->authorize('update', $location) : $this->authorize('create', Location::class);

        $data = $this->validate();

        $dto = new LocationData(
            name: $data['name'],
            slug: Str::lower($data['slug']),
            code: $data['code'],
            addressLine: $data['address_line'],
            city: $data['city'],
            department: $data['department'],
            phone: $data['phone'] ?: null,
            email: $data['email'] ?: null,
            timezone: $data['timezone'],
        );

        $location = $location ? $update->execute($location, $dto) : $create->execute($dto);

        session()->flash('success', $this->locationId ? 'Sede actualizada.' : 'Sede creada.');

        $this->redirectRoute('admin.locations.show', ['location' => $location->id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.locations.form', [
            'editing' => $this->locationId !== null,
            'timezones' => collect(DateTimeZone::listIdentifiers(DateTimeZone::AMERICA))->mapWithKeys(fn ($tz) => [$tz => $tz])->all(),
        ])->title($this->locationId ? 'Editar sede' : 'Nueva sede');
    }
}
