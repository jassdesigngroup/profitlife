<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historia clínica · {{ $member->member_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #141414; margin: 0; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #b83a06; border-bottom: 1px solid #e4e4e4; padding-bottom: 3px; margin: 18px 0 8px; }
        .muted { color: #5c5c5c; }
        .grid td { vertical-align: top; padding: 2px 10px 2px 0; }
        .session { border: 1px solid #e4e4e4; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; page-break-inside: avoid; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 9px; color: #5c5c5c; margin-top: 5px; }
        .text { white-space: pre-line; }
        .addendum { border-left: 3px solid #fd540d; padding-left: 8px; margin-top: 6px; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8.5px; color: #5c5c5c; }
    </style>
</head>
<body>
    <div class="footer">{{ $brand }} · Historia clínica de {{ $member->full_name }} ({{ $member->member_number }}) · Documento confidencial · Exportado por {{ $exportedBy }} el {{ now($tz)->format('d/m/Y g:i a') }}</div>

    <p class="muted">{{ $brand }} · Historia clínica de fisioterapia</p>
    <h1>{{ $member->full_name }}</h1>
    <table class="grid">
        <tr>
            <td><strong>N.º:</strong> {{ $member->member_number }}</td>
            <td><strong>Documento:</strong> {{ $member->document_type?->value }} {{ $member->document_number }}</td>
            <td><strong>Nacimiento:</strong> {{ $member->birth_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Apertura:</strong> {{ $record->opened_at->setTimezone($tz)->format('d/m/Y') }}</td>
            <td><strong>Responsable:</strong> {{ $record->primaryStaff?->full_name }}</td>
            <td><strong>Estado:</strong> {{ $record->status->label() }} · <strong>Consentimiento clínico:</strong> {{ $hasConsent ? 'vigente' : 'no registrado' }}</td>
        </tr>
    </table>

    <h2>Antecedentes</h2>
    @foreach (['Motivo de consulta' => $record->reason_for_consultation, 'Historia médica' => $record->medical_history, 'Medicamentos' => $record->medications, 'Alergias' => $record->allergies] as $label => $value)
        <p class="label">{{ $label }}</p>
        <p class="text">{{ $value ?: '—' }}</p>
    @endforeach

    <h2>Planes de tratamiento</h2>
    @forelse ($record->plans as $plan)
        <div class="session">
            <strong>{{ $plan->title }}</strong> · {{ $plan->status->label() }} · {{ $plan->starts_on->format('d/m/Y') }}{{ $plan->ends_on ? ' – '.$plan->ends_on->format('d/m/Y') : '' }} · {{ $plan->staff?->full_name }}
            @if ($plan->diagnosis)<p class="label">Diagnóstico</p><p class="text">{{ $plan->diagnosis }}</p>@endif
            @if ($plan->goals)<p class="label">Objetivos</p><p class="text">{{ $plan->goals }}</p>@endif
        </div>
    @empty
        <p class="muted">Sin planes.</p>
    @endforelse

    <h2>Sesiones</h2>
    @forelse ($record->sessions as $session)
        <div class="session">
            <strong>{{ $session->session_type->label() }}</strong> · {{ $session->performed_at->setTimezone($tz)->format('d/m/Y g:i a') }} · {{ $session->staff?->full_name }} · {{ $session->location?->name }}
            @if ($session->pain_scale !== null) · Dolor {{ $session->pain_scale }}/10 @endif
            @foreach ($session->notes as $note)
                @foreach ($note->type->sections() as $key => [$title, $hint])
                    @if (! empty($note->body[$key]))<p class="label">{{ $title }}</p><p class="text">{{ $note->body[$key] }}</p>@endif
                @endforeach
                <p class="muted">{{ $note->isSigned() ? 'Firmada por '.$note->signer?->full_name.' el '.$note->signed_at->setTimezone($tz)->format('d/m/Y g:i a') : 'SIN FIRMAR' }}</p>
                @foreach ($note->addenda as $addendum)
                    <div class="addendum"><p class="label">Adenda · {{ $addendum->author?->full_name }} · {{ $addendum->signed_at?->setTimezone($tz)->format('d/m/Y g:i a') }}</p><p class="text">{{ $addendum->body['text'] ?? '' }}</p></div>
                @endforeach
            @endforeach
        </div>
    @empty
        <p class="muted">Sin sesiones.</p>
    @endforelse
</body>
</html>
