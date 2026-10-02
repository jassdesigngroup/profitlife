<?php

namespace App\Livewire\Admin\Plans;

use App\Domain\Appointments\Models\Service;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Enums\AccessScope;
use App\Domain\Memberships\Enums\DurationUnit;
use App\Domain\Memberships\Enums\SessionPeriod;
use App\Domain\Memberships\Enums\VisitLimitPeriod;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Domain\Settings\Services\Settings;
use App\Livewire\Admin\Concerns\ParsesMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Crear o editar un plan. Cambiar el precio solo afecta a las ventas futuras.
 */
class PlanForm extends Component
{
    use ParsesMoney;

    #[Locked]
    public ?int $planId = null;

    public string $name = '';

    public string $description = '';

    public string $duration_unit = 'month';

    public int|string $duration_count = 1;

    public string $price = '';

    public string $enrollment_fee = '0';

    public int|string $tax_percent = 0;

    public string $access_scope = 'all_locations';

    /** @var list<string> */
    public array $locationIds = [];

    public bool $limit_visits = false;

    public int|string $visit_limit_count = 12;

    public string $visit_limit_period = 'month';

    public int|string $max_freeze_days = 0;

    public bool $auto_renews = false;

    public string $benefits = '';

    public bool $is_active = true;

    public bool $includes_gym_access = true;

    /** @var list<array{service_id: string, sessions: string, period: string}> sesiones incluidas ('' = ilimitadas) */
    public array $sessions = [];

    public int|string $sort_order = 0;

    public function mount(?MembershipPlan $plan = null): void
    {
        if ($plan?->exists) {
            $this->authorize('update', $plan);
            $this->planId = $plan->id;
            $this->name = $plan->name;
            $this->description = (string) $plan->description;
            $this->duration_unit = $plan->duration_unit?->value ?? 'month';
            $this->duration_count = (int) $plan->duration_count;
            $this->price = $this->centsToPesos($plan->price_cents);
            $this->enrollment_fee = $this->centsToPesos($plan->enrollment_fee_cents);
            $this->tax_percent = $plan->tax_rate_bps / 100;
            $this->access_scope = $plan->access_scope->value;
            $this->locationIds = $plan->locations->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->limit_visits = $plan->visit_limit_count !== null;
            $this->visit_limit_count = $plan->visit_limit_count ?? 12;
            $this->visit_limit_period = $plan->visit_limit_period?->value ?? 'month';
            $this->max_freeze_days = (int) $plan->max_freeze_days;
            $this->auto_renews = $plan->auto_renews;
            $this->benefits = implode("\n", $plan->benefits ?? []);
            $this->is_active = $plan->is_active;
            $this->sort_order = $plan->sort_order;
            $this->includes_gym_access = (bool) ($plan->includes_gym_access ?? true);
            $this->sessions = $plan->planServices->map(fn ($s) => [
                'service_id' => (string) $s->service_id,
                'sessions' => $s->sessions_included === null ? '' : (string) $s->sessions_included,
                'period' => $s->period->value,
            ])->all();

            return;
        }

        $this->authorize('create', MembershipPlan::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_unit' => ['required', Rule::enum(DurationUnit::class)],
            'duration_count' => ['required', 'integer', 'min:1', 'max:366'],
            'price' => ['required', 'regex:/^[\d.,\s]+$/'],
            'enrollment_fee' => ['nullable', 'regex:/^[\d.,\s]*$/'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'access_scope' => ['required', Rule::enum(AccessScope::class)],
            'locationIds' => [Rule::requiredIf($this->access_scope === AccessScope::SelectedLocations->value), 'array'],
            'locationIds.*' => ['integer', Rule::exists('locations', 'id')],
            'limit_visits' => ['boolean'],
            'visit_limit_count' => [Rule::requiredIf($this->limit_visits), 'nullable', 'integer', 'min:1', 'max:999'],
            'visit_limit_period' => [Rule::requiredIf($this->limit_visits), 'nullable', Rule::enum(VisitLimitPeriod::class)],
            'max_freeze_days' => ['required', 'integer', 'min:0', 'max:365'],
            'auto_renews' => ['boolean'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'includes_gym_access' => ['boolean'],
            'sessions' => ['array', 'max:20'],
            'sessions.*.service_id' => ['required', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'sessions.*.sessions' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sessions.*.period' => ['required', Rule::enum(SessionPeriod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre', 'duration_count' => 'duración', 'price' => 'precio', 'enrollment_fee' => 'matrícula',
            'tax_percent' => 'IVA', 'locationIds' => 'sedes', 'visit_limit_count' => 'número de ingresos',
            'max_freeze_days' => 'días de congelación', 'sort_order' => 'orden',
            'sessions.*.service_id' => 'servicio', 'sessions.*.sessions' => 'sesiones', 'sessions.*.period' => 'periodo',
        ];
    }

    public function save(Settings $settings): void
    {
        $plan = $this->planId ? MembershipPlan::query()->findOrFail($this->planId) : null;
        $plan ? $this->authorize('update', $plan) : $this->authorize('create', MembershipPlan::class);

        $data = $this->validate();

        if ($this->pesosToCents($data['price']) <= 0) {
            $this->addError('price', 'El precio debe ser mayor que cero.');

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'duration_unit' => $data['duration_unit'],
            'duration_count' => (int) $data['duration_count'],
            // Por ahora se cobra una vez por periodo: la frecuencia de cobro es la duración.
            'billing_unit' => $data['duration_unit'],
            'billing_count' => (int) $data['duration_count'],
            'price_cents' => $this->pesosToCents($data['price']),
            'enrollment_fee_cents' => $this->pesosToCents($data['enrollment_fee']),
            'tax_rate_bps' => (int) round(((float) $data['tax_percent']) * 100),
            'access_scope' => $data['access_scope'],
            'visit_limit_count' => $data['limit_visits'] ? (int) $data['visit_limit_count'] : null,
            'visit_limit_period' => $data['limit_visits'] ? $data['visit_limit_period'] : null,
            'max_freeze_days' => (int) $data['max_freeze_days'] ?: null,
            'auto_renews' => (bool) $data['auto_renews'],
            'benefits' => array_values(array_filter(array_map('trim', explode("\n", (string) $data['benefits'])))),
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
            'includes_gym_access' => (bool) $data['includes_gym_access'],
        ];

        DB::transaction(function () use (&$plan, $attributes, $data, $settings) {
            if ($plan) {
                $plan->update($attributes);
            } else {
                $slug = Str::slug($attributes['name']);
                $attributes['slug'] = MembershipPlan::withTrashed()->where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
                $attributes['currency'] = $settings->currency();
                $plan = MembershipPlan::query()->create($attributes);
            }

            $plan->locations()->sync($data['access_scope'] === AccessScope::SelectedLocations->value ? array_map('intval', $data['locationIds']) : []);

            // Las sesiones nuevas aplican a las ventas y renovaciones siguientes.
            $plan->planServices()->delete();
            foreach ($data['sessions'] ?? [] as $row) {
                $plan->planServices()->create([
                    'service_id' => (int) $row['service_id'],
                    'sessions_included' => $row['sessions'] === '' || $row['sessions'] === null ? null : (int) $row['sessions'],
                    'period' => $row['period'],
                ]);
            }
        });

        session()->flash('success', 'Plan guardado.');
        $this->redirectRoute('admin.plans.index', navigate: true);
    }

    public function addSession(): void
    {
        $this->sessions[] = ['service_id' => '', 'sessions' => '4', 'period' => SessionPeriod::PerTerm->value];
    }

    public function removeSession(int $index): void
    {
        unset($this->sessions[$index]);
        $this->sessions = array_values($this->sessions);
    }

    public function render(): View
    {
        return view('livewire.admin.plans.form', [
            'editing' => $this->planId !== null,
            'units' => DurationUnit::options(),
            'scopes' => AccessScope::options(),
            'periods' => VisitLimitPeriod::options(),
            'sessionPeriods' => SessionPeriod::options(),
            'services' => Service::query()->orderBy('name')->pluck('name', 'id')->all(),
            'locations' => Location::query()->withoutGlobalScopes()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
        ])->title($this->planId ? 'Editar plan' : 'Nuevo plan');
    }
}
