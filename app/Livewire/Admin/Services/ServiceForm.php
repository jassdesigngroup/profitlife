<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Appointments\Enums\ServiceCategory;
use App\Domain\Appointments\Models\Service;
use App\Domain\Locations\Models\Location;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Livewire\Admin\Concerns\ParsesMoney;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Alta y edición de un servicio: datos, profesionales que lo prestan y
 * sedes donde se ofrece (con precio propio opcional).
 */
class ServiceForm extends Component
{
    use ParsesMoney;

    #[Locked]
    public ?int $serviceId = null;

    public string $name = '';

    public string $category = 'physiotherapy';

    public string $description = '';

    public int|string $duration_minutes = 60;

    public int|string $buffer_minutes = 0;

    public string $price = '';

    public int|string $tax_percent = 0;

    public bool $requires_room = false;

    public bool $is_clinical = false;

    public bool $is_bookable_online = false;

    public string $color = '#FD540D';

    public bool $is_active = true;

    /** @var list<string> */
    public array $staffIds = [];

    /** @var array<int|string, array{enabled: bool, price: string}> */
    public array $locationPrices = [];

    public function mount(?Service $service = null): void
    {
        if ($service?->exists) {
            $this->authorize('update', $service);
            $this->serviceId = $service->id;
            $this->fill([
                'name' => $service->name,
                'category' => $service->category->value,
                'description' => (string) $service->description,
                'duration_minutes' => $service->duration_minutes,
                'buffer_minutes' => $service->buffer_minutes,
                'price' => $this->centsToPesos($service->price_cents),
                'tax_percent' => $service->tax_rate_bps / 100,
                'requires_room' => $service->requires_room,
                'is_clinical' => $service->is_clinical,
                'is_bookable_online' => $service->is_bookable_online,
                'color' => $service->color ?: '#FD540D',
                'is_active' => $service->is_active,
            ]);
            $this->staffIds = $service->staff->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->authorize('create', Service::class);
        }

        foreach ($this->locations() as $location) {
            $pivot = $service?->locations->firstWhere('id', $location->id)?->pivot;
            $this->locationPrices[$location->id] = [
                'enabled' => $pivot ? (bool) $pivot->is_active : ! $service?->exists,
                'price' => $pivot?->price_cents === null ? '' : $this->centsToPesos((int) $pivot->price_cents),
            ];
        }
    }

    public function save(Settings $settings): void
    {
        $service = $this->serviceId ? Service::query()->findOrFail($this->serviceId) : null;
        $service ? $this->authorize('update', $service) : $this->authorize('create', Service::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'price' => ['required', 'regex:/^[\d.,\s]+$/'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'requires_room' => ['boolean'],
            'is_clinical' => ['boolean'],
            'is_bookable_online' => ['boolean'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
            'staffIds' => ['array'],
            'staffIds.*' => ['integer', Rule::in($this->staffOptions()->pluck('id')->all())],
            'locationPrices' => ['array'],
            'locationPrices.*.enabled' => ['boolean'],
            'locationPrices.*.price' => ['nullable', 'regex:/^[\d.,\s]*$/'],
        ], [], [
            'name' => 'nombre', 'duration_minutes' => 'duración', 'buffer_minutes' => 'margen', 'price' => 'precio',
            'tax_percent' => 'IVA', 'staffIds' => 'profesionales', 'locationPrices.*.price' => 'precio de la sede',
        ]);

        $attributes = [
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] ?: null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'buffer_minutes' => (int) $data['buffer_minutes'],
            'price_cents' => $this->pesosToCents($data['price']),
            'tax_rate_bps' => (int) round(((float) $data['tax_percent']) * 100),
            'requires_room' => (bool) $data['requires_room'],
            'is_clinical' => (bool) $data['is_clinical'],
            'is_bookable_online' => (bool) $data['is_bookable_online'],
            'color' => strtoupper($data['color']),
            'is_active' => (bool) $data['is_active'],
        ];

        $validLocations = $this->locations()->pluck('id')->all();

        DB::transaction(function () use (&$service, $attributes, $data, $settings, $validLocations) {
            if ($service) {
                $service->update($attributes);
            } else {
                $slug = Str::slug($attributes['name']);
                $attributes['slug'] = Service::withTrashed()->where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
                $attributes['currency'] = $settings->currency();
                $service = Service::query()->create($attributes);
            }

            $service->staff()->sync(array_map('intval', $data['staffIds']));

            $sync = [];
            foreach ($data['locationPrices'] as $locationId => $row) {
                if (! in_array((int) $locationId, $validLocations, true)) {
                    continue;
                }
                $price = trim((string) ($row['price'] ?? ''));
                $sync[(int) $locationId] = [
                    'is_active' => (bool) $row['enabled'],
                    'price_cents' => $price === '' ? null : $this->pesosToCents($price),
                ];
            }
            $service->locations()->sync($sync);
        });

        session()->flash('success', 'Servicio guardado.');
        $this->redirectRoute('admin.services.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.services.form', [
            'editing' => $this->serviceId !== null,
            'categories' => ServiceCategory::options(),
            'staffOptions' => $this->staffOptions(),
            'locations' => $this->locations(),
        ])->title($this->serviceId ? 'Editar servicio' : 'Nuevo servicio');
    }

    /**
     * @return Collection<int, Location>
     */
    private function locations()
    {
        return Location::query()->withoutGlobalScopes()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Profesionales agendables y activos.
     *
     * @return Collection<int, Staff>
     */
    private function staffOptions()
    {
        return Staff::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->where('status', StaffStatus::Active)
            ->where('is_bookable', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'job_title']);
    }
}
