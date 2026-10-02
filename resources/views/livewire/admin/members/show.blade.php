<div>
    <a href="{{ route('admin.members.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> Clientes
    </a>

    {{-- Cabecera --}}
    <section class="relative mb-6 overflow-hidden rounded-2xl bg-ink-950 p-6 sm:p-8">
        <div class="pointer-events-none absolute -right-16 -top-20 size-72 rounded-full bg-brand-500/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-5">
                <div class="relative">
                    <div class="flex size-20 items-center justify-center overflow-hidden rounded-2xl bg-white/10 font-display text-3xl font-bold text-white ring-2 ring-brand-500">
                        @if ($member->photo_path)
                            <img src="{{ route('admin.members.photo', $member) }}?v={{ md5($member->photo_path) }}" alt="Foto de {{ $member->full_name }}" class="size-full object-cover">
                        @else
                            {{ $member->initials() }}
                        @endif
                    </div>
                    @can('update', $member)
                        <label class="absolute -bottom-2 -right-2 flex size-8 cursor-pointer items-center justify-center rounded-full bg-brand-500 text-white shadow-lg hover:bg-brand-600" title="Cambiar foto">
                            <span class="sr-only">Cambiar foto</span>
                            <x-icon name="pencil" class="size-4" />
                            <input type="file" accept="image/jpeg,image/png,image/webp" wire:model="photo" class="sr-only">
                        </label>
                    @endcan
                </div>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-semibold tracking-wider text-brand-500">{{ $member->member_number }}</p>
                    <h1 class="font-display text-3xl font-bold uppercase tracking-wide text-white sm:text-4xl">{{ $member->full_name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-steel-300">
                        <x-badge :color="$member->status->color()" dot>{{ $member->status->label() }}</x-badge>
                        <span>{{ $member->homeLocation?->name }}</span>
                        @if ($member->age() !== null)<span>· {{ $member->age() }} años</span>@endif
                        @if ($member->isMinor())<x-badge color="warning">Menor de edad</x-badge>@endif
                        @if ($member->user?->staff)
                            <x-badge color="info">También es staff</x-badge>
                        @elseif ($member->user_id)
                            <x-badge color="info">Con cuenta de acceso</x-badge>
                        @endif
                    </div>
                    <x-field-error name="photo" class="text-red-300" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('update', $member)
                    <x-button variant="secondary" icon="pencil" :href="route('admin.members.edit', $member)" wire:navigate>Editar</x-button>
                    <x-button variant="secondary" wire:click="openStatus">Cambiar estado</x-button>
                    <x-button variant="secondary" icon="key" wire:click="openPin">{{ $member->hasCheckinPin() ? 'Cambiar PIN' : 'Asignar PIN' }}</x-button>
                @endcan
                @can('delete', $member)
                    <x-button variant="ghost" icon="trash" class="text-red-300 hover:bg-white/10" wire:click="delete"
                        wire:confirm="¿Eliminar a {{ $member->full_name }}? Su historial se conserva para auditoría.">Eliminar</x-button>
                @endcan
            </div>
        </div>
    </section>

    @if ($member->isMinor())
        <x-alert type="warning" class="mb-6">Cliente menor de edad: los consentimientos deben firmarlos su representante legal (acudiente).</x-alert>
    @endif

    <nav class="mb-5 flex gap-1 overflow-x-auto border-b border-steel-200" aria-label="Pestañas">
        @php
            $tabs = ['summary' => ['Resumen', 'user']];
            if (auth()->user()->can('memberships.view')) { $tabs['memberships'] = ['Membresías', 'heart']; }
            if (auth()->user()->can('appointments.view')) { $tabs['appointments'] = ['Citas', 'calendar']; }
            if (auth()->user()->can('payments.view')) { $tabs['billing'] = ['Pagos', 'chart']; }
            if (auth()->user()->can('check-ins.view')) { $tabs['checkins'] = ['Asistencia', 'enter']; }
            $tabs += ['notes' => ['Notas', 'clipboard'], 'documents' => ['Documentos', 'squares'], 'consents' => ['Consentimientos', 'shield']];
        @endphp
        @foreach ($tabs as $key => [$label, $icon])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                '-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold',
                'border-brand-500 text-ink-950' => $tab === $key,
                'border-transparent text-steel-700 hover:text-ink-950' => $tab !== $key,
            ]) @if ($tab === $key) aria-current="page" @endif>
                <x-icon :name="$icon" @class(['size-4', 'text-brand-700' => $tab === $key]) /> {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($tab === 'summary')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Datos del cliente" class="lg:col-span-2">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Documento' => trim(($member->document_type?->label() ?? '').' '.($member->document_number ?? '')) ?: null,
                        'Fecha de nacimiento' => $member->birth_date?->format('d/m/Y'),
                        'Género' => $member->gender?->label(),
                        'Teléfono' => $member->phone,
                        'Correo' => $member->email,
                        'Dirección' => collect([$member->address_line, $member->city, $member->department])->filter()->implode(', ') ?: null,
                        'Fecha de ingreso' => $member->joined_on->format('d/m/Y'),
                        'Registrado por' => $member->creator?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">{{ $label }}</dt>
                            <dd class="mt-1 break-words text-ink-950">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
            <x-card title="Check-in por teléfono">
                <div class="flex items-center gap-3">
                    <span @class([
                        'flex size-10 items-center justify-center rounded-full',
                        'bg-success-50 text-success-700' => $member->hasCheckinPin(),
                        'bg-steel-100 text-steel-700' => ! $member->hasCheckinPin(),
                    ])><x-icon name="key" /></span>
                    <div>
                        <p class="font-semibold text-ink-950">{{ $member->hasCheckinPin() ? 'PIN asignado' : 'Sin PIN' }}</p>
                        <p class="text-sm text-steel-700">{{ $member->hasCheckinPin() ? 'Puede ingresar con su teléfono y PIN.' : 'Solo podrá ingresar con QR o número de cliente.' }}</p>
                    </div>
                </div>
                @can('update', $member)
                    @if ($member->hasCheckinPin())
                        <x-button variant="link" size="sm" class="mt-4 text-danger-700!" wire:click="clearPin" wire:confirm="¿Quitar el PIN de check-in?">Quitar PIN</x-button>
                    @endif
                @endcan
                @if ($member->photo_path)
                    @can('update', $member)
                        <div class="mt-4 border-t border-steel-200 pt-4">
                            <x-button variant="link" size="sm" wire:click="removePhoto" wire:confirm="¿Eliminar la foto?">Eliminar foto</x-button>
                        </div>
                    @endcan
                @endif
            </x-card>
            <div class="lg:col-span-3">
                <livewire:admin.members.member-contacts :member-id="$member->id" :key="'contacts-'.$member->id" />
            </div>
        </div>
    @elseif ($tab === 'memberships' && auth()->user()->can('memberships.view'))
        <livewire:admin.members.member-memberships :member-id="$member->id" :key="'memberships-'.$member->id" />
    @elseif ($tab === 'appointments' && auth()->user()->can('appointments.view'))
        <livewire:admin.members.member-appointments :member-id="$member->id" :key="'appointments-'.$member->id" />
    @elseif ($tab === 'billing' && auth()->user()->can('payments.view'))
        <livewire:admin.members.member-billing :member-id="$member->id" :key="'billing-'.$member->id" />
    @elseif ($tab === 'checkins' && auth()->user()->can('check-ins.view'))
        <livewire:admin.members.member-check-ins :member-id="$member->id" :key="'checkins-'.$member->id" />
    @elseif ($tab === 'notes')
        <livewire:admin.members.member-notes :member-id="$member->id" :key="'notes-'.$member->id" />
    @elseif ($tab === 'documents')
        <livewire:admin.members.member-documents :member-id="$member->id" :key="'documents-'.$member->id" />
    @else
        <livewire:admin.members.member-consents :member-id="$member->id" :key="'consents-'.$member->id" />
    @endif

    <x-modal wire:model="showPin" title="PIN de check-in" max-width="sm">
        <form wire:submit="savePin" id="pin-form" class="space-y-4">
            <p class="text-sm text-steel-700">Pida al cliente que escriba un PIN de 4 dígitos. No se puede volver a consultar: solo cambiar.</p>
            <x-input label="PIN" type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="pin" required />
            <x-input label="Repita el PIN" type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="pin_confirmation" required />
            <p class="text-xs text-steel-700">No se aceptan dígitos repetidos (1111), secuencias (1234), el final del teléfono ni la fecha de nacimiento.</p>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="pin-form">Guardar PIN</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showStatus" title="Estado del cliente" max-width="sm">
        <form wire:submit="saveStatus" id="status-form" class="space-y-4">
            <x-select label="Estado" wire:model="newStatus" :options="$statuses" required />
            <p class="text-xs text-steel-700">Un cliente bloqueado o inactivo no podrá registrar su ingreso al centro.</p>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="status-form">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
