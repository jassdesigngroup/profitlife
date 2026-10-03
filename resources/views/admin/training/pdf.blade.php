<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $program->name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #141414; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #b83a06; margin: 16px 0 6px; border-bottom: 1px solid #e4e4e4; padding-bottom: 3px; }
        .muted { color: #5c5c5c; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; page-break-inside: avoid; }
        th { text-align: left; font-size: 8.5px; text-transform: uppercase; color: #5c5c5c; border-bottom: 1px solid #e4e4e4; padding: 3px 4px; }
        td { padding: 3px 4px; border-bottom: 1px solid #f2f2f2; vertical-align: top; }
        .ex { font-weight: bold; }
        .ss { color: #fd540d; font-weight: bold; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; color: #5c5c5c; }
    </style>
</head>
<body>
    <div class="footer">{{ $brand }} · {{ $program->name }}{{ $program->member ? ' · '.$program->member->full_name : '' }} · Consulte a su entrenador ante cualquier molestia.</div>
    <p class="muted">{{ $brand }} · {{ $program->type->label() }}</p>
    <h1>{{ $program->name }}</h1>
    <p class="muted">
        @if ($program->member){{ $program->member->full_name }} · @endif
        Prescrito por {{ $program->staff?->full_name }}
        @if ($program->starts_on) · desde {{ $program->starts_on->format('d/m/Y') }}@endif
        @if ($program->ends_on) hasta {{ $program->ends_on->format('d/m/Y') }}@endif
    </p>
    @if ($program->goal)<p><strong>Objetivo:</strong> {{ $program->goal }}</p>@endif

    @foreach ($program->workouts as $workout)
        <h2>{{ $workout->name }}</h2>
        @if ($workout->notes)<p class="muted">{{ $workout->notes }}</p>@endif
        <table>
            <thead><tr><th style="width:34%">Ejercicio</th><th>Series</th><th>Reps</th><th>Peso</th><th>Tiempo</th><th>Distancia</th><th>Descanso</th><th>RPE</th></tr></thead>
            <tbody>
                @foreach ($workout->exercises as $item)
                    @foreach ($item->sets as $i => $set)
                        <tr>
                            @if ($i === 0)
                                <td rowspan="{{ max(1, $item->sets->count()) }}"><span class="ex">{{ $item->exercise?->name }}</span>@if ($item->superset_group) <span class="ss">S{{ $item->superset_group }}</span>@endif @if ($item->notes)<br><span class="muted">{{ $item->notes }}</span>@endif</td>
                            @endif
                            <td>{{ $set->set_number }}</td>
                            <td>{{ $set->reps ?? '—' }}</td>
                            <td>{{ $set->weight_kg !== null ? rtrim(rtrim(number_format($set->weight_kg, 2, ',', '.'), '0'), ',').' kg' : '—' }}</td>
                            <td>{{ $set->duration_seconds ? $set->duration_seconds.' s' : '—' }}</td>
                            <td>{{ $set->distance_meters ? $set->distance_meters.' m' : '—' }}</td>
                            <td>{{ $set->rest_seconds ? $set->rest_seconds.' s' : '—' }}</td>
                            <td>{{ $set->rpe ?? '—' }}</td>
                        </tr>
                    @endforeach
                    @if ($item->sets->isEmpty())
                        <tr><td class="ex">{{ $item->exercise?->name }}</td><td colspan="7" class="muted">{{ $item->notes ?: '—' }}</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endforeach
    <p class="muted">S1, S2…: ejercicios que se hacen seguidos como superserie. RPE: esfuerzo percibido de 1 a 10.</p>
</body>
</html>
