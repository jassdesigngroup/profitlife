<?php

namespace App\Livewire\Admin\Settings;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Locations\Models\Location;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Support\Scopes\LocationScope;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ajustes de operación: días de gracia (general y por sede) y la ventana
 * de ingresos repetidos del check-in.
 */
#[Title('Ajustes')]
class SettingsPage extends Component
{
    use InteractsWithToasts;

    public int|string $graceDays = 5;

    public int|string $duplicateMinutes = 60;

    /** @var array<int|string, string> sede => días ('' = usa el general) */
    public array $locationGrace = [];

    public function mount(Settings $settings): void
    {
        $this->authorize(Permission::SettingsUpdate->value);

        $this->graceDays = $settings->graceDays();
        $this->duplicateMinutes = $settings->checkInDuplicateMinutes();

        foreach ($this->locations() as $location) {
            $own = Setting::query()->where('group', 'memberships')->where('key', 'grace_days')->where('location_id', $location->id)->value('value');
            $this->locationGrace[$location->id] = $own === null ? '' : (string) $own;
        }
    }

    public function save(Settings $settings, AuditLogger $audit): void
    {
        $this->authorize(Permission::SettingsUpdate->value);

        $this->validate([
            'graceDays' => ['required', 'integer', 'min:0', 'max:60'],
            'duplicateMinutes' => ['required', 'integer', 'min:0', 'max:720'],
            'locationGrace' => ['array'],
            'locationGrace.*' => ['nullable', 'integer', 'min:0', 'max:60'],
        ], [], ['graceDays' => 'días de gracia', 'duplicateMinutes' => 'minutos', 'locationGrace.*' => 'días de la sede']);

        $old = ['general' => $settings->graceDays(), 'duplicate_minutes' => $settings->checkInDuplicateMinutes()];
        $settings->set('memberships', 'grace_days', (int) $this->graceDays);
        $settings->set('check_ins', 'duplicate_minutes', (int) $this->duplicateMinutes);

        $validIds = $this->locations()->pluck('id')->all();

        foreach ($this->locationGrace as $locationId => $days) {
            if (! in_array((int) $locationId, $validIds, true)) {
                continue;
            }

            if ($days === '' || $days === null) {
                Setting::query()->where('group', 'memberships')->where('key', 'grace_days')->where('location_id', (int) $locationId)->delete();
            } else {
                $settings->set('memberships', 'grace_days', (int) $days, (int) $locationId);
            }
        }

        $settings->flush();

        $audit->log('settings', AuditEvent::SettingsUpdated, null, auth()->user(), [
            'key' => 'memberships.grace_days, check_ins.duplicate_minutes',
            'old' => $old,
            'attributes' => [
                'general' => (int) $this->graceDays,
                'por_sede' => array_filter($this->locationGrace, fn ($v) => $v !== ''),
                'duplicate_minutes' => (int) $this->duplicateMinutes,
            ],
        ]);

        $this->toast('Ajustes guardados.');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.page', ['locations' => $this->locations()]);
    }

    /**
     * @return Collection<int, Location>
     */
    private function locations()
    {
        return Location::query()->withoutGlobalScope(LocationScope::class)->orderBy('name')->get(['id', 'name']);
    }
}
