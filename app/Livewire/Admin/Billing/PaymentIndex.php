<?php

namespace App\Livewire\Admin\Billing;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Settings\Services\Settings;
use App\Support\BusinessDate;
use App\Support\Locations\CurrentLocation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Caja: pagos recibidos en un rango de fechas (locales) con totales por medio.
 */
#[Title('Pagos')]
class PaymentIndex extends Component
{
    use WithPagination;

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    #[Url]
    public string $method = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);
        $today = BusinessDate::today()->toDateString();
        $this->from = $this->from ?: $today;
        $this->to = $this->to ?: $today;
    }

    public function updating(string $property): void
    {
        $this->resetPage();
    }

    public function render(CurrentLocation $current, Settings $settings): View
    {
        $this->authorize('viewAny', Payment::class);

        $valid = validator(
            ['from' => $this->from, 'to' => $this->to, 'method' => $this->method],
            ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d'], 'method' => ['nullable', Rule::enum(PaymentMethod::class)]],
        )->valid();

        $tz = $settings->displayTimezone();
        $from = CarbonImmutable::parse($valid['from'] ?? BusinessDate::today()->toDateString(), $tz)->startOfDay()->utc();
        $to = CarbonImmutable::parse($valid['to'] ?? BusinessDate::today()->toDateString(), $tz)->endOfDay()->utc();

        $base = Payment::query()
            ->inLocation($current->id())
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->when(($valid['method'] ?? '') !== '', fn ($q) => $q->where('method', $valid['method']));

        $totals = (clone $base)->selectRaw('method, SUM(amount_cents) as total, COUNT(*) as count')->groupBy('method')->get();

        return view('livewire.admin.billing.payments', [
            'payments' => (clone $base)->with(['member:id,first_name,last_name,member_number', 'invoice:id,number', 'receiver:id,name', 'location:id,name'])
                ->latest('paid_at')->paginate(30),
            'totals' => $totals,
            'grandTotal' => Money::ofCents((int) $totals->sum('total')),
            'methods' => PaymentMethod::options(),
        ]);
    }
}
