<div>
    <x-page-header eyebrow="Administración" title="Ajustes" />

    <form wire:submit="save" class="max-w-3xl space-y-6">
        <x-card title="Días de gracia para pagar" description="Días después de emitido el comprobante (o del inicio, en las renovaciones) antes de suspender la membresía por falta de pago. 0 = se suspende al día siguiente.">
            <div class="space-y-5">
                <x-input label="Valor general (días)" type="number" min="0" max="60" wire:model="graceDays" class="sm:w-48" required />
                <div>
                    <p class="mb-2 text-sm font-semibold text-ink-950">Por sede (opcional)</p>
                    <div class="divide-y divide-steel-200 rounded-lg ring-1 ring-steel-200">
                        @foreach ($locations as $location)
                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                <label for="grace-{{ $location->id }}" class="text-sm text-ink-950">{{ $location->name }}</label>
                                <input id="grace-{{ $location->id }}" type="number" min="0" max="60" wire:model="locationGrace.{{ $location->id }}" placeholder="General"
                                    class="w-28 rounded-lg border-0 bg-surface px-3 py-2 text-sm ring-1 ring-inset ring-steel-300 placeholder:text-steel-500 focus:ring-2 focus:ring-brand-500">
                            </div>
                            <x-field-error name="locationGrace.{{ $location->id }}" class="px-4 pb-2" />
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-steel-700">Déjelo vacío para usar el valor general.</p>
                </div>
            </div>
        </x-card>
        <x-card title="Check-in: ingresos repetidos" description="Si el cliente vuelve a marcar dentro de estos minutos, se le deja pasar pero no se registra como un ingreso nuevo. 0 = desactivado.">
            <x-input label="Minutos" type="number" min="0" max="720" wire:model="duplicateMinutes" class="sm:w-48" required />
        </x-card>
        <div class="flex justify-end">
            <x-button type="submit" icon="check">Guardar ajustes</x-button>
        </div>
    </form>
</div>
