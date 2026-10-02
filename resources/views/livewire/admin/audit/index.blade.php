<div>
    <x-page-header eyebrow="Administración" title="Auditoría" description="Registro de solo lectura de accesos y cambios. Las horas se muestran en la zona horaria local." />

    <x-card :padding="false">
        <div class="grid gap-3 border-b border-steel-200 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5">
            <x-select wire:model.live="log" :options="$logs" placeholder="Todos los módulos" aria-label="Módulo" />
            <x-select wire:model.live="event" :options="$events" placeholder="Todos los eventos" aria-label="Evento" />
            <x-input wire:model.live.debounce.400ms="causer" placeholder="Usuario" aria-label="Usuario" />
            <x-input type="date" wire:model.live="from" aria-label="Desde" />
            <x-input type="date" wire:model.live="to" aria-label="Hasta" />
        </div>

        @if ($activities->isEmpty())
            <x-empty-state icon="clipboard" title="Sin registros" description="No hay eventos con esos filtros." />
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Fecha</th>
                    <th scope="col" class="px-4 py-3">Evento</th>
                    <th scope="col" class="px-4 py-3">Usuario</th>
                    <th scope="col" class="px-4 py-3">Sobre</th>
                    <th scope="col" class="px-4 py-3">IP</th>
                    <th scope="col" class="px-6 py-3"><span class="sr-only">Detalle</span></th>
                </x-slot:head>
                @foreach ($activities as $activity)
                    @php
                        $event = \App\Domain\Audit\Enums\AuditEvent::tryFrom((string) $activity->event);
                        $props = $activity->properties->except('ip');
                        $subject = $activity->subject;
                        $subjectLabel = match (true) {
                            $subject instanceof \App\Domain\Identity\Models\User => $subject->name,
                            $subject instanceof \App\Domain\Staff\Models\Staff => $subject->full_name,
                            $subject instanceof \App\Domain\Locations\Models\Location => $subject->name,
                            $subject instanceof \App\Domain\Locations\Models\Room => 'Sala '.$subject->name,
                            $subject instanceof \Spatie\Permission\Models\Role => 'Rol '.(\App\Domain\Identity\Enums\RoleName::tryFrom($subject->name)?->label() ?? $subject->name),
                            $subject !== null => class_basename($subject).' #'.$subject->getKey(),
                            default => '—',
                        };
                    @endphp
                    <tr wire:key="activity-{{ $activity->id }}" class="align-top hover:bg-canvas">
                        <td class="whitespace-nowrap px-6 py-3 text-steel-700"><x-datetime :value="$activity->created_at" format="d/m/Y H:i:s" /></td>
                        <td class="px-4 py-3">
                            <x-badge :color="$event?->color() ?? 'neutral'">{{ $event?->label() ?? $activity->event }}</x-badge>
                            <p class="mt-1 text-xs text-steel-700">{{ \App\Domain\Audit\Enums\AuditEvent::logNameLabel($activity->log_name) }}</p>
                        </td>
                        <td class="px-4 py-3 font-medium text-ink-950">{{ $activity->causer?->name ?? 'Sistema / anónimo' }}</td>
                        <td class="px-4 py-3 text-steel-700">{{ $subjectLabel }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-steel-700">{{ $activity->properties->get('ip', '—') }}</td>
                        <td class="px-6 py-3 text-right">
                            @if ($props->isNotEmpty())
                                <x-button variant="ghost" size="sm" wire:click="toggle({{ $activity->id }})">{{ $expanded === $activity->id ? 'Ocultar' : 'Detalle' }}</x-button>
                            @endif
                        </td>
                    </tr>
                    @if ($expanded === $activity->id)
                        <tr wire:key="activity-detail-{{ $activity->id }}">
                            <td colspan="6" class="bg-canvas px-6 py-4">
                                <pre class="max-h-72 overflow-auto rounded-lg bg-ink-950 p-4 text-xs leading-relaxed text-steel-100">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </x-table>
            @if ($activities->hasPages())
                <div class="border-t border-steel-200 px-6 py-3">{{ $activities->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
