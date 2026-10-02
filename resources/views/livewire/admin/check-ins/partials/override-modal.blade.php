<x-modal wire:model="showOverride" title="Autorizar ingreso" max-width="md">
    <form wire:submit="confirmOverride" id="override-form" class="space-y-4">
        <p class="text-sm text-steel-700">Se registrará un ingreso aceptado. El rechazo original se conserva y la autorización queda en la auditoría con su nombre y el motivo.</p>
        <x-input label="Motivo" wire:model="overrideReason" maxlength="200" placeholder="Pagará hoy, cortesía autorizada…" required />
    </form>
    <x-slot:footer>
        <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
        <x-button type="submit" form="override-form" wire:loading.attr="disabled">Autorizar</x-button>
    </x-slot:footer>
</x-modal>
