<div>
    <x-card title="Contactos de emergencia" :padding="false">
        @can('update', $member)
            <x-slot:actions><x-button size="sm" icon="plus" wire:click="create">Añadir contacto</x-button></x-slot:actions>
        @endcan
        @if ($contacts->isEmpty())
            <x-empty-state icon="heart" title="Sin contactos de emergencia" description="Registre al menos una persona a quien llamar en caso de emergencia." />
        @else
            <ul class="divide-y divide-steel-200">
                @foreach ($contacts as $contact)
                    <li wire:key="contact-{{ $contact->id }}" class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink-950">{{ $contact->name }}
                                @if ($contact->relationship)<span class="font-normal text-steel-700">· {{ $contact->relationship }}</span>@endif
                                @if ($contact->is_primary)<x-badge color="brand" class="ml-1">Principal</x-badge>@endif
                            </p>
                            <p class="text-sm text-steel-700">{{ $contact->phone }}@if ($contact->alt_phone) · {{ $contact->alt_phone }}@endif @if ($contact->email) · {{ $contact->email }}@endif</p>
                        </div>
                        @can('update', $member)
                            <div class="flex gap-1">
                                <x-button variant="ghost" size="sm" wire:click="edit({{ $contact->id }})">Editar</x-button>
                                <x-button variant="ghost" size="sm" class="text-danger-700" wire:click="remove({{ $contact->id }})" wire:confirm="¿Eliminar a {{ $contact->name }}?">Eliminar</x-button>
                            </div>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$contactId ? 'Editar contacto' : 'Nuevo contacto de emergencia'" max-width="md">
        <form wire:submit="save" id="contact-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Nombre" wire:model="name" required class="sm:col-span-2" />
            <x-input label="Parentesco" wire:model="relationship" placeholder="Madre, pareja…" />
            <x-input label="Teléfono" type="tel" wire:model="phone" required />
            <x-input label="Teléfono alterno" type="tel" wire:model="alt_phone" />
            <x-input label="Correo" type="email" wire:model="email" />
            <x-checkbox wire:model="is_primary" label="Contacto principal" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="contact-form">Guardar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
