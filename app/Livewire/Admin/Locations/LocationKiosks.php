<?php

namespace App\Livewire\Admin\Locations;

use App\Domain\CheckIns\Actions\PairKioskDevice;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\QrCode;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kioscos de check-in de la sede: alta, vinculación con enlace de un solo
 * uso a la vista, desactivación y desvinculación.
 */
class LocationKiosks extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $locationId;

    #[Locked]
    public ?int $deviceId = null;

    public string $name = '';

    public bool $isActive = true;

    public bool $showForm = false;

    public bool $showPair = false;

    /** Enlace con el token; solo existe mientras el modal está abierto. */
    #[Locked]
    public string $pairingUrl = '';

    public function mount(int $locationId): void
    {
        $this->locationId = $locationId;
        $location = $this->location();
        $this->authorize('view', $location);
        $this->authorize('viewAny', [KioskDevice::class, $location]);
    }

    public function create(): void
    {
        $this->authorize('create', [KioskDevice::class, $this->location()]);
        $this->reset(['deviceId', 'name', 'isActive']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $deviceId): void
    {
        $device = $this->device($deviceId);
        $this->authorize('update', $device);

        $this->deviceId = $device->id;
        $this->name = $device->name;
        $this->isActive = $device->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'isActive' => ['boolean'],
        ], [], ['name' => 'nombre']);

        if ($this->deviceId === null) {
            $location = $this->location();
            $this->authorize('create', [KioskDevice::class, $location]);
            KioskDevice::query()->create(['location_id' => $location->id, 'name' => $data['name'], 'is_active' => $data['isActive']]);
            $this->toast('Kiosco creado. Ahora genere su enlace de vinculación.');
        } else {
            $device = $this->device($this->deviceId);
            $this->authorize('update', $device);
            $device->update(['name' => $data['name'], 'is_active' => $data['isActive']]);
            $this->toast('Kiosco actualizado.');
        }

        $this->showForm = false;
    }

    public function pair(int $deviceId, PairKioskDevice $pair): void
    {
        $device = $this->device($deviceId);
        $this->authorize('update', $device);

        $token = $pair->execute($device, auth()->user());

        $this->pairingUrl = route('kiosk').'#vincular='.rawurlencode($token);
        $this->showPair = true;
    }

    public function closePair(): void
    {
        $this->pairingUrl = '';
        $this->showPair = false;
    }

    public function unpair(int $deviceId): void
    {
        $device = $this->device($deviceId);
        $this->authorize('update', $device);

        $device->tokens()->delete();
        $this->toast('Kiosco desvinculado: el dispositivo ya no puede registrar ingresos.', 'warning');
    }

    public function delete(int $deviceId): void
    {
        $device = $this->device($deviceId);
        $this->authorize('update', $device);

        $device->tokens()->delete();
        $device->delete();
        $this->toast('Kiosco eliminado.', 'warning');
    }

    public function render(): View
    {
        $location = $this->location();

        return view('livewire.admin.locations.kiosks', [
            'location' => $location,
            'devices' => KioskDevice::query()->where('location_id', $location->id)->withCount('tokens')->orderBy('name')->get(),
            'pairingQr' => $this->pairingUrl !== '' ? QrCode::svg($this->pairingUrl, 220) : null,
        ]);
    }

    private function location(): Location
    {
        return Location::query()->findOrFail($this->locationId);
    }

    private function device(int $deviceId): KioskDevice
    {
        return KioskDevice::query()->where('location_id', $this->locationId)->findOrFail($deviceId);
    }
}
