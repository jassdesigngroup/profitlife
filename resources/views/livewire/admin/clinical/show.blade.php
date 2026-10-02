<div>
    <a href="{{ route('admin.members.show', ['member' => $member->id, 'tab' => 'physio']) }}" wire:navigate class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline">
        <x-icon name="arrow-left" class="size-4" /> {{ $member->full_name }}
    </a>

    <x-page-header eyebrow="Historia clínica · fisioterapia" :title="$member->full_name" :description="$member->member_number.' · '.($member->age() !== null ? $member->age().' años · ' : '').'abierta el '.$record->opened_at->setTimezone($tz)->format('d/m/Y')">
        <x-slot:actions>
            <x-badge :color="$record->status->color()" dot class="self-center">{{ $record->status->label() }}</x-badge>
            @can('export', $record)
                <x-button variant="secondary" icon="download" :href="route('admin.clinical.pdf', $member)" target="_blank">Exportar PDF</x-button>
            @endcan
            @if ($canWrite)
                <x-button icon="plus" wire:click="newSession">Registrar sesión</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($emergencyUntil)
        <x-alert type="danger" class="mb-4" title="Acceso de emergencia">Solo lectura, registrado en la bitácora. Vence a las {{ \Illuminate\Support\Carbon::parse($emergencyUntil)->setTimezone($tz)->format('g:i a') }}.</x-alert>
    @endif
    @unless ($hasConsent)
        <x-alert type="warning" class="mb-4" title="Sin consentimiento de tratamiento clínico">No se puede firmar la evaluación inicial hasta registrar el consentimiento en la ficha del cliente (pestaña Consentimientos).</x-alert>
    @endunless

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            {{-- Antecedentes --}}
            <x-card title="Antecedentes">
                @can('update', $record)
                    <x-slot:actions><x-button size="sm" variant="secondary" icon="pencil" wire:click="editRecord">Editar</x-button></x-slot:actions>
                @endcan
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    @foreach (['Motivo de consulta' => $record->reason_for_consultation, 'Historia médica' => $record->medical_history, 'Medicamentos' => $record->medications, 'Alergias' => $record->allergies] as $label => $value)
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wider text-steel-700">{{ $label }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-ink-950">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            {{-- Sesiones --}}
            <x-card title="Sesiones y notas" :padding="false">
                @forelse ($sessions as $session)
                    <article wire:key="ses-{{ $session->id }}" class="border-b border-steel-200 px-6 py-5 last:border-0">
                        <header class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-ink-950">{{ $session->session_type->label() }} · {{ $session->performed_at->setTimezone($tz)->format('d/m/Y g:i a') }}</p>
                                <p class="text-sm text-steel-700">{{ $session->staff?->full_name }} · {{ $session->location?->name }}@if ($session->plan) · {{ $session->plan->title }}@endif</p>
                            </div>
                            @if ($session->pain_scale !== null)
                                <span class="rounded-full bg-canvas px-3 py-1 text-sm font-bold text-ink-950 ring-1 ring-steel-200">Dolor {{ $session->pain_scale }}/10</span>
                            @endif
                        </header>

                        @foreach ($session->notes as $note)
                            <div class="mt-4 space-y-3">
                                @foreach ($note->type->sections() as $key => [$title, $hint])
                                    @if (! empty($note->body[$key]))
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-wider text-brand-700">{{ $title }}</p>
                                            <p class="mt-0.5 whitespace-pre-line text-sm text-ink-950">{{ $note->body[$key] }}</p>
                                        </div>
                                    @endif
                                @endforeach

                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    @if ($note->isSigned())
                                        <x-badge color="success" dot>Firmada por {{ $note->signer?->full_name }} el {{ $note->signed_at->setTimezone($tz)->format('d/m/Y g:i a') }}</x-badge>
                                        @can('addAddendum', $note)
                                            <x-button size="sm" variant="ghost" wire:click="openAddendum({{ $note->id }})">Agregar adenda</x-button>
                                        @endcan
                                    @else
                                        <x-badge color="warning" dot>Sin firmar</x-badge>
                                        @can('update', $note)
                                            <x-button size="sm" variant="ghost" wire:click="editNote({{ $note->id }})">Editar</x-button>
                                        @endcan
                                        @can('sign', $note)
                                            <x-button size="sm" variant="secondary" icon="check" wire:click="openSign({{ $note->id }})">Firmar</x-button>
                                        @endcan
                                    @endif
                                </div>

                                @foreach ($note->addenda as $addendum)
                                    <div class="rounded-lg border-l-4 border-brand-500 bg-canvas px-4 py-3">
                                        <p class="text-xs font-bold uppercase tracking-wider text-steel-700">Adenda · {{ $addendum->author?->full_name }} · {{ $addendum->signed_at?->setTimezone($tz)->format('d/m/Y g:i a') }}</p>
                                        <p class="mt-1 whitespace-pre-line text-sm text-ink-950">{{ $addendum->body['text'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        @if ($session->summary_for_member)
                            <p class="mt-3 text-xs text-steel-700"><span class="font-semibold">Resumen para el paciente:</span> {{ $session->summary_for_member }}</p>
                        @endif
                    </article>
                @empty
                    <x-empty-state icon="clipboard" title="Sin sesiones" description="Registre la evaluación inicial para empezar la historia." />
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            {{-- Equipo --}}
            <livewire:admin.clinical.clinical-team :record-id="$record->id" :key="'team-'.$record->id" />

            {{-- Dolor --}}
            @if ($pain->count() >= 2)
                @php
                    $w = 300; $h = 120; $n = $pain->count();
                    $points = $pain->values()->map(fn ($s, $i) => round($i * ($w - 20) / max(1, $n - 1) + 10, 1).','.round($h - 10 - $s->pain_scale * ($h - 20) / 10, 1))->implode(' ');
                @endphp
                <x-card title="Evolución del dolor">
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" role="img" aria-label="Dolor de {{ $pain->first()->pain_scale }} a {{ $pain->last()->pain_scale }} en {{ $n }} sesiones">
                        @foreach ([0, 5, 10] as $level)
                            <line x1="10" x2="{{ $w - 10 }}" y1="{{ $h - 10 - $level * ($h - 20) / 10 }}" y2="{{ $h - 10 - $level * ($h - 20) / 10 }}" stroke="#e4e4e4" stroke-width="1" />
                            <text x="0" y="{{ $h - 7 - $level * ($h - 20) / 10 }}" font-size="9" fill="#5c5c5c">{{ $level }}</text>
                        @endforeach
                        <polyline points="{{ $points }}" fill="none" stroke="#FD540D" stroke-width="2.5" stroke-linejoin="round" />
                    </svg>
                    <p class="mt-2 text-sm text-steel-700">De {{ $pain->first()->pain_scale }}/10 a {{ $pain->last()->pain_scale }}/10 en {{ $n }} sesiones.</p>
                </x-card>
            @endif

            {{-- Planes --}}
            <x-card title="Planes de tratamiento">
                @if ($canWrite)
                    <x-slot:actions><x-button size="sm" variant="secondary" icon="plus" wire:click="editPlan">Nuevo</x-button></x-slot:actions>
                @endif
                @forelse ($plans as $plan)
                    <div wire:key="plan-{{ $plan->id }}" class="border-b border-steel-200 py-3 text-sm last:border-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-ink-950">{{ $plan->title }}</p>
                            <x-badge :color="$plan->status->color()">{{ $plan->status->label() }}</x-badge>
                        </div>
                        <p class="text-steel-700">{{ $plan->sessions_count }}{{ $plan->planned_sessions ? ' de '.$plan->planned_sessions : '' }} sesiones · desde {{ $plan->starts_on->format('d/m/Y') }}</p>
                        @if ($plan->diagnosis)<p class="mt-1 whitespace-pre-line text-ink-950"><span class="font-semibold">Diagnóstico:</span> {{ $plan->diagnosis }}</p>@endif
                        @if ($plan->goals)<p class="mt-1 whitespace-pre-line text-ink-950"><span class="font-semibold">Objetivos:</span> {{ $plan->goals }}</p>@endif
                        @if ($canWrite)<x-button size="sm" variant="ghost" class="mt-1" wire:click="editPlan({{ $plan->id }})">Editar</x-button>@endif
                    </div>
                @empty
                    <p class="text-sm text-steel-700">Sin planes de tratamiento.</p>
                @endforelse
            </x-card>

            {{-- Documentos clínicos --}}
            <x-card title="Documentos clínicos">
                @forelse ($documents as $doc)
                    <a href="{{ route('admin.documents.download', $doc) }}" class="flex items-center gap-2 py-1.5 text-sm text-brand-700 hover:underline"><x-icon name="download" class="size-4" /> {{ $doc->title ?: $doc->original_name }}</a>
                @empty
                    <p class="text-sm text-steel-700">Sin documentos clínicos.</p>
                @endforelse
                <a href="{{ route('admin.members.show', ['member' => $member->id, 'tab' => 'documents']) }}" wire:navigate class="mt-2 inline-block text-xs font-semibold text-steel-700 hover:underline">Subir en Documentos (sensibilidad clínica)</a>
            </x-card>
        </div>
    </div>

    {{-- Antecedentes --}}
    <x-modal wire:model="showRecord" title="Antecedentes" max-width="2xl">
        <form wire:submit="saveRecord" id="record-form" class="space-y-4">
            <x-textarea label="Motivo de consulta" wire:model="reason" rows="2" />
            <x-textarea label="Historia médica (antecedentes, cirugías, enfermedades)" wire:model="history" rows="4" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-textarea label="Medicamentos" wire:model="medications" rows="2" />
                <x-textarea label="Alergias" wire:model="allergies" rows="2" />
            </div>
            <x-select label="Estado" wire:model="recordStatus" :options="$recordStatuses" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="record-form">Guardar</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Plan --}}
    <x-modal wire:model="showPlan" :title="$planId ? 'Editar plan de tratamiento' : 'Nuevo plan de tratamiento'" max-width="2xl">
        <form wire:submit="savePlan" id="plan-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Título" wire:model="planTitle" maxlength="150" required class="sm:col-span-2" placeholder="Rehabilitación de rodilla derecha" />
            <x-textarea label="Diagnóstico fisioterapéutico" wire:model="planDiagnosis" rows="3" class="sm:col-span-2" />
            <x-textarea label="Objetivos" wire:model="planGoals" rows="3" class="sm:col-span-2" />
            <x-input label="Sesiones previstas" type="number" min="1" wire:model="planSessions" />
            <x-select label="Estado" wire:model="planStatus" :options="$planStatuses" />
            <x-input label="Inicio" type="date" wire:model="planStartsOn" required />
            <x-input label="Fin (opcional)" type="date" wire:model="planEndsOn" />
            <x-checkbox wire:model="planVisible" label="Visible para el paciente en el portal" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="plan-form">Guardar plan</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Sesión y nota --}}
    <x-modal wire:model="showSession" :title="$noteId ? 'Editar nota' : 'Registrar sesión'" max-width="2xl">
        <form wire:submit="saveSession" id="session-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select label="Tipo" wire:model.live="sessionType" :options="$sessionTypes" :disabled="$noteId !== null" />
                @if ($noteId === null)
                    <x-input label="Fecha y hora" type="datetime-local" wire:model="performedAt" required />
                @endif
                <x-input label="Dolor (0–10)" type="number" min="0" max="10" wire:model="painScale" />
            </div>
            @if ($noteId === null)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select label="Plan de tratamiento" wire:model="sessionPlanId" :options="$activePlans" placeholder="Sin plan" />
                    <x-select label="Cita" wire:model="sessionAppointmentId" placeholder="Sin cita">
                        @foreach ($appointments as $a)
                            <option value="{{ $a->id }}">{{ $a->service?->name }} · {{ $a->starts_at->setTimezone($tz)->format('d/m g:i a') }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endif
            @foreach ($templateSections as $key => [$title, $hint])
                <x-textarea :label="$title" wire:model="sections.{{ $key }}" rows="3" :hint="$hint" />
            @endforeach
            <x-field-error name="sections" />
            <x-textarea label="Resumen para el paciente (opcional)" wire:model="summaryForMember" rows="2" hint="Lo único que verá el paciente en el portal. Sin términos técnicos." />
            <x-field-error name="performedAt" />
            <x-field-error name="appointmentId" />
            <div class="rounded-lg bg-canvas p-4 ring-1 ring-steel-200">
                <x-input label="Contraseña para firmar ahora (opcional)" type="password" wire:model="signPassword" autocomplete="current-password" hint="Firmada, la nota no se puede modificar; las correcciones van como adenda." />
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button variant="secondary" type="submit" form="session-form">Guardar sin firmar</x-button>
            <x-button icon="check" wire:click="saveSession(true)">Guardar y firmar</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Firmar --}}
    <x-modal wire:model="showSign" title="Firmar nota" max-width="md">
        <form wire:submit="sign" id="sign-form" class="space-y-4">
            <p class="text-sm text-steel-700">Al firmar, la nota queda inmutable. Las correcciones posteriores se hacen con una adenda.</p>
            <x-input label="Su contraseña" type="password" wire:model="signPassword" autocomplete="current-password" required />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="sign-form" icon="check">Firmar</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Adenda --}}
    <x-modal wire:model="showAddendum" title="Agregar adenda" max-width="lg">
        <form wire:submit="addAddendum" id="addendum-form" class="space-y-4">
            <x-textarea label="Adenda" wire:model="addendumText" rows="4" required hint="Aclaración o corrección. La nota original no cambia." />
            <x-input label="Su contraseña (firma)" type="password" wire:model="signPassword" autocomplete="current-password" required />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancelar</x-button>
            <x-button type="submit" form="addendum-form" icon="check">Firmar adenda</x-button>
        </x-slot:footer>
    </x-modal>
</div>
