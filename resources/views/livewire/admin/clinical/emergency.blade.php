<div>
    <a href="{{ route('admin.members.show', ['member' => $member->id, 'tab' => 'physio']) }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> {{ $member->full_name }}
    </a>
    <x-page-header eyebrow="Historia clínica" :title="$member->full_name" />

    <x-card class="max-w-2xl">
        <x-alert type="warning" title="Acceso restringido">
            Usted no hace parte del equipo tratante. Solo puede abrir esta historia con un acceso de emergencia de {{ \App\Domain\Physiotherapy\Services\ClinicalAccess::EMERGENCY_HOURS }} horas.
            El acceso queda registrado con su nombre y el motivo, y se avisa al profesional responsable.
        </x-alert>
        <form wire:submit="grantEmergency" class="mt-5 space-y-4">
            <x-textarea label="Motivo del acceso" wire:model="emergencyReason" rows="3" required hint="Por ejemplo: requerimiento de una autoridad, urgencia del paciente sin su fisioterapeuta disponible." />
            <x-button type="submit" variant="danger" icon="key">Abrir con acceso de emergencia</x-button>
        </form>
    </x-card>
</div>
