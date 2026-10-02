<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Appointments\Models\Service;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Servicios')]
class ServiceIndex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Service::class);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Service::class);

        return view('livewire.admin.services.index', [
            'services' => Service::query()->withCount(['staff', 'locations'])->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }
}
