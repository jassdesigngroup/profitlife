{{-- Tabla responsive: en pantallas pequeñas se desplaza horizontalmente dentro de la tarjeta. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-steel-200 text-sm">
        @isset($head)
            <thead class="bg-canvas">
                <tr class="text-left text-xs font-bold uppercase tracking-wide text-steel-700">
                    {{ $head }}
                </tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-steel-200 bg-surface">
            {{ $slot }}
        </tbody>
    </table>
</div>
