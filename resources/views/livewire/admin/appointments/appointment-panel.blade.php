<div>
    <x-modal wire:model="show" :title="$appointment ? $appointment->service->name : 'Cita'" max-width="xl">
        @if ($appointment)
            @php $start = $appointment->starts_at->setTimezone($tz); @endphp
            <div class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-display text-2xl font-bold uppercase text-ink-950">{{ ucfirst($start->locale('es')->translatedFormat('l j \d\e F')) }}</p>
                        <p class="text-sm text-steel-700">{{ $start->format('g:i a') }} – {{ $appointment->ends_at->setTimezone($tz)->format('g:i a') }} · {{ $appointment->location->name }}@if ($appointment->room) · {{ $appointment->room->name }}@endif</p>
                    </div>
                    <x-badge :color="$appointment->status->color()" dot>{{ $appointment->status->label() }}</x-badge>
                </div>

                <dl class="grid gap-3 rounded-lg bg-canvas p-4 text-sm ring-1 ring-steel-200 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Cliente</dt>
                        <dd><a href="{{ route('admin.members.show', ['member' => $appointment->member_id, 'tab' => 'appointments']) }}" wire:navigate class="font-semibold text-ink-950 hover:text-brand-700">{{ $appointment->member?->full_name }}</a>
                            <span class="font-mono text-xs text-steel-700">{{ $appointment->member?->member_number }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Profesional</dt>
                        <dd class="font-semibold text-ink-950">{{ $appointment->staff?->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Cobro</dt>
                        <dd class="text-ink-950">
                            @if ($creditsUsed > 0)
                                Sesión del plan
                            @elseif ($invoice)
                                <a href="{{ route('admin.invoices.show', $invoice) }}" target="_blank" class="font-mono text-brand-700 hover:underline">{{ $invoice->number }}</a>
                                · <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
                            @else
                                Incluida en el plan
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Agendó</dt>
                        <dd class="text-ink-950">{{ $appointment->creator?->name ?? '—' }}</dd>
                    </div>
                    @if ($appointment->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Notas</dt>
                            <dd class="whitespace-pre-line text-ink-950">{{ $appointment->notes }}</dd>
                        </div>
                    @endif
                    @if ($appointment->cancellation_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">Motivo de cancelación</dt>
                            <dd class="text-ink-950">{{ $appointment->cancellation_reason }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($mode === 'detail')
                    <div class="flex flex-wrap gap-2">
                        @can('update', $appointment)
                            @if ($appointment->isActive())
                                @if ($started)
                                    <x-button size="sm" icon="check" wire:click="mark('completed')">Atendida</x-button>
                                    <x-button size="sm" variant="secondary" wire:click="mark('no_show')" wire:confirm="¿Registrar inasistencia? La sesión queda descontada.">No asistió</x-button>
                                @endif
                                <x-button size="sm" variant="secondary" wire:click="setMode('reschedule')">Reprogramar</x-button>
                            @endif
                        @endcan
                        @can('cancel', $appointment)
                            <x-button size="sm" variant="ghost" class="text-danger-700" wire:click="setMode('cancel')">Cancelar cita</x-button>
                        @endcan
                        @can('refundCredit', $appointment)
                            <x-button size="sm" variant="ghost" wire:click="setMode('refund')">Devolver sesión</x-button>
                        @endcan
                    </div>
                    <x-field-error name="status" />

                    @if ($clinicalLink)
                        <a href="{{ $clinicalLink }}" wire:navigate class="flex items-center gap-2 rounded-lg bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100">
                            <x-icon name="clipboard" class="size-4" /> Registrar la nota clínica de esta sesión
                        </a>
                    @endif

                    <details class="text-sm">
                        <summary class="cursor-pointer font-semibold text-steel-700">Historial</summary>
                        <ul class="mt-2 space-y-1 text-steel-700">
                            @foreach ($appointment->statusHistories as $h)
                                <li><x-datetime :value="$h->created_at" /> · {{ $h->to_status->label() }}@if ($h->reason) · {{ $h->reason }}@endif @if ($h->changer) · {{ $h->changer->name }}@endif</li>
                            @endforeach
                        </ul>
                    </details>
                @elseif ($mode === 'cancel')
                    <form wire:submit="cancel" class="space-y-3">
                        @if ($late)
                            <x-alert type="warning">Faltan menos de {{ app(\App\Domain\Settings\Services\Settings::class)->cancellationHours() }} horas: la sesión queda descontada y el comprobante se conserva.</x-alert>
                        @else
                            <p class="text-sm text-steel-700">Cancelación a tiempo: se devuelve la sesión del plan y se anula el comprobante si no tiene pagos.</p>
                        @endif
                        <x-input label="Motivo" wire:model="cancelReason" maxlength="200" required />
                        <div class="flex gap-2">
                            <x-button variant="secondary" size="sm" wire:click="setMode('detail')">Volver</x-button>
                            <x-button type="submit" variant="danger" size="sm">Cancelar cita</x-button>
                        </div>
                    </form>
                @elseif ($mode === 'refund')
                    <form wire:submit="refund" class="space-y-3">
                        <p class="text-sm text-steel-700">Se devuelve la sesión descontada al saldo del cliente. Queda en la auditoría.</p>
                        <x-input label="Motivo" wire:model="refundReason" maxlength="200" required />
                        <div class="flex gap-2">
                            <x-button variant="secondary" size="sm" wire:click="setMode('detail')">Volver</x-button>
                            <x-button type="submit" size="sm">Devolver sesión</x-button>
                        </div>
                    </form>
                @elseif ($mode === 'reschedule')
                    <form wire:submit="reschedule" class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-input label="Nueva fecha" type="date" wire:model.live="newDate" />
                            <x-select label="Profesional" wire:model.live="newStaffId" :options="$staffOptions->pluck('full_name', 'id')->all()" />
                        </div>
                        @if ($freeSlots->isEmpty())
                            <p class="text-sm text-steel-700">No hay horarios libres ese día.</p>
                        @else
                            <div class="grid max-h-48 grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-5">
                                @foreach ($freeSlots as $s)
                                    @php $value = $s['staff_id'].'|'.$s['starts_at']->toIso8601ZuluString(); @endphp
                                    <label wire:key="rs-{{ $value }}" @class([
                                        'cursor-pointer rounded-lg px-2 py-2 text-center text-sm ring-1',
                                        'bg-brand-500 font-bold text-white ring-brand-500' => $newSlot === $value,
                                        'bg-surface ring-steel-300 hover:bg-steel-100' => $newSlot !== $value,
                                    ])>
                                        <input type="radio" wire:model.live="newSlot" value="{{ $value }}" class="sr-only">
                                        {{ $s['starts_at']->setTimezone($tz)->format('g:i a') }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <x-field-error name="newSlot" />
                        <x-field-error name="startsAt" />
                        <x-input label="Motivo (opcional)" wire:model="rescheduleReason" maxlength="200" />
                        <div class="flex gap-2">
                            <x-button variant="secondary" size="sm" wire:click="setMode('detail')">Volver</x-button>
                            <x-button type="submit" size="sm">Reprogramar</x-button>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </x-modal>
</div>
