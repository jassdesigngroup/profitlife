<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Registro manual --}}
        <x-card title="Ingreso de hoy" description="Registra el ingreso desde recepción con las mismas reglas del kiosco.">
            @if ($canRegister)
                <div class="flex flex-wrap items-end gap-3">
                    @if ($this->deskLocations->count() > 1)
                        <div class="w-56"><x-select label="Sede" wire:model="deskLocationId" :options="$this->deskLocations->pluck('name', 'id')->all()" /></div>
                    @endif
                    <x-button icon="enter" wire:click="register" wire:loading.attr="disabled">Registrar ingreso</x-button>
                </div>
                <x-field-error name="deskLocationId" />
                @if ($outcome)
                    <div class="mt-4">@include('livewire.admin.check-ins.partials.outcome')</div>
                @endif
            @else
                <p class="text-sm text-steel-700">No tiene permiso para registrar ingresos.</p>
            @endif

            @if ($usage)
                <div class="mt-5 rounded-lg bg-canvas p-4 ring-1 ring-steel-200">
                    <p class="text-xs font-bold uppercase tracking-wider text-steel-700">{{ $membership->plan->visitLimitLabel() }}</p>
                    <p class="mt-1 text-sm text-ink-950">
                        <span class="font-display text-3xl font-bold">{{ $usage['used'] }}</span> de {{ $usage['limit'] }} usados
                        del {{ $usage['from']->format('d/m/Y') }} al {{ $usage['to']->format('d/m/Y') }}.
                    </p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-steel-200">
                        <div class="h-full bg-brand-500" style="width: {{ min(100, $usage['limit'] > 0 ? round($usage['used'] * 100 / $usage['limit']) : 100) }}%"></div>
                    </div>
                </div>
            @endif
        </x-card>

        {{-- Código de acceso --}}
        <x-card title="Código QR de acceso" description="El cliente lo muestra en el kiosco. También puede entrar con su número o con celular + PIN.">
            @if (! $canManageCode)
                <p class="text-sm text-steel-700">Solo quien puede editar al cliente ve y gestiona su código.</p>
            @elseif ($credential)
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <div class="w-fit shrink-0 rounded-lg bg-white p-2 ring-1 ring-steel-200">{!! $qr !!}</div>
                    <div class="space-y-2 text-sm text-steel-700">
                        <p>Generado <x-datetime :value="$credential->created_at" />.@if ($credential->last_used_at) Último uso <x-datetime :value="$credential->last_used_at" />.@endif</p>
                        <div class="flex flex-wrap gap-2">
                            <x-button size="sm" variant="secondary" icon="printer" :href="route('admin.members.access-card', $member)" target="_blank">Imprimir o descargar</x-button>
                            <x-button size="sm" variant="secondary" icon="envelope" wire:click="sendCode" :disabled="blank($member->email)">Enviar por correo</x-button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-button size="sm" variant="ghost" wire:click="issueCode" wire:confirm="¿Generar un código nuevo? El actual dejará de funcionar.">Generar uno nuevo</x-button>
                            <x-button size="sm" variant="ghost" class="text-danger-700" wire:click="revokeCode" wire:confirm="¿Anular el código? El cliente no podrá usarlo.">Anular</x-button>
                        </div>
                        @if (blank($member->email))<p class="text-xs">Sin correo registrado: descargue la imagen y envíela por WhatsApp.</p>@endif
                    </div>
                </div>
            @else
                <x-empty-state icon="qr-code" title="Sin código de acceso" description="Genere el código para imprimirlo o enviarlo al cliente." />
                <div class="flex justify-center"><x-button icon="qr-code" wire:click="issueCode">Generar código</x-button></div>
            @endif
            <x-field-error name="access" />
            <p class="mt-4 text-xs text-steel-700">PIN para celular: {{ $member->hasCheckinPin() ? 'configurado' : 'sin configurar (se asigna desde el resumen)' }}.</p>
        </x-card>
    </div>

    <x-card title="Historial de ingresos" description="Últimos 50 intentos en sus sedes." :padding="false">
        @if ($checkIns->isEmpty())
            <p class="px-6 py-5 text-sm text-steel-700">Todavía no tiene ingresos registrados.</p>
        @else
            <x-table>
                <x-slot:head>
                    <th scope="col" class="px-6 py-3">Fecha</th>
                    <th scope="col" class="px-4 py-3">Sede</th>
                    <th scope="col" class="px-4 py-3">Resultado</th>
                    <th scope="col" class="px-4 py-3">Medio</th>
                    <th scope="col" class="px-6 py-3">Origen</th>
                </x-slot:head>
                @foreach ($checkIns as $checkIn)
                    <tr wire:key="mci-{{ $checkIn->id }}">
                        <td class="whitespace-nowrap px-6 py-3 text-ink-950"><x-datetime :value="$checkIn->checked_in_at" /></td>
                        <td class="px-4 py-3 text-steel-700">{{ $checkIn->location?->name }}</td>
                        <td class="px-4 py-3">
                            <x-badge :color="$checkIn->result->color()" dot>{{ $checkIn->result->label() }}</x-badge>
                            @if ($checkIn->rejection_reason)<p class="mt-1 text-xs text-steel-700">{{ $checkIn->rejection_reason->label() }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-steel-700">{{ $checkIn->method->label() }}</td>
                        <td class="px-6 py-3 text-steel-700">{{ $checkIn->kioskDevice?->name ?? $checkIn->registeredBy?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    @include('livewire.admin.check-ins.partials.override-modal')
</div>
