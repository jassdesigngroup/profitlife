<?php

namespace App\Livewire\Admin\Audit;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Consulta de la auditoría. Solo lectura: el componente no expone ningún
 * método que modifique registros.
 */
#[Title('Auditoría')]
class AuditIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $log = '';

    #[Url]
    public string $event = '';

    #[Url(as: 'usuario')]
    public string $causer = '';

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    public ?int $expanded = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Activity::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['log', 'event', 'causer', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->authorize('viewAny', Activity::class);
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render(): View
    {
        $this->authorize('viewAny', Activity::class);

        $filters = validator(
            ['from' => $this->from, 'to' => $this->to],
            ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']],
        )->valid();

        $tz = app(Settings::class)->displayTimezone();
        $term = trim($this->causer);

        $activities = Activity::query()
            ->with(['causer', 'subject' => fn ($q) => $q->withoutGlobalScopes()])
            ->when($this->log !== '', fn (Builder $q) => $q->where('log_name', $this->log))
            ->when($this->event !== '', fn (Builder $q) => $q->where('event', $this->event))
            ->when($term !== '', fn (Builder $q) => $q->whereHasMorph('causer', [User::class], fn (Builder $u) => $u->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))))
            ->when(($filters['from'] ?? '') !== '', fn (Builder $q) => $q->where('created_at', '>=', CarbonImmutable::parse($filters['from'], $tz)->startOfDay()->utc()))
            ->when(($filters['to'] ?? '') !== '', fn (Builder $q) => $q->where('created_at', '<=', CarbonImmutable::parse($filters['to'], $tz)->endOfDay()->utc()))
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.audit.index', [
            'activities' => $activities,
            'logs' => collect(['auth', 'users', 'staff', 'locations', 'roles', 'settings'])->mapWithKeys(fn ($l) => [$l => AuditEvent::logNameLabel($l)])->all(),
            'events' => AuditEvent::options(),
        ]);
    }
}
