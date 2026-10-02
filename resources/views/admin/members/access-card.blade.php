@php $brand = app(\App\Domain\Settings\Services\Settings::class)->brandName(); @endphp
<!DOCTYPE html>
<html lang="es" class="bg-canvas">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Código de acceso · {{ $member->member_number }} · {{ $brand }}</title>
    @fonts
    @vite(['resources/css/app.css'])
    <style>@media print { .no-print { display: none !important } body { background: #fff } .card { box-shadow: none !important } }</style>
</head>
<body class="py-8 text-ink-950">
    <div class="no-print mx-auto mb-4 flex max-w-sm justify-end gap-2 px-4">
        <button type="button" id="download" class="rounded-lg bg-surface px-4 py-2 text-sm font-bold ring-1 ring-steel-300 hover:bg-steel-100">Descargar imagen</button>
        <button type="button" onclick="window.print()" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-white hover:bg-brand-600">Imprimir</button>
    </div>
    <main class="card mx-auto max-w-sm overflow-hidden rounded-2xl bg-surface text-center shadow-xl ring-1 ring-steel-200">
        <div class="bg-ink-950 px-6 py-5"><x-brand dark /></div>
        <div class="px-6 py-6">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-700">Código de acceso</p>
            <p class="mt-1 font-display text-3xl font-bold uppercase">{{ $member->full_name }}</p>
            <div id="qr" class="mx-auto mt-5 w-fit">{!! $qr !!}</div>
            <p class="mt-4 font-display text-2xl font-bold tracking-widest">{{ $member->member_number }}</p>
            <p class="mt-3 text-xs text-steel-700">Muéstralo en el kiosco de la entrada. Es personal e intransferible.</p>
        </div>
    </main>
    <script>
        // Convierte el SVG en PNG en el navegador, para enviarlo por WhatsApp.
        document.getElementById('download').addEventListener('click', () => {
            const svg = document.querySelector('#qr svg');
            const size = 600;
            const image = new Image();
            image.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = size; canvas.height = size;
                const context = canvas.getContext('2d');
                context.fillStyle = '#fff';
                context.fillRect(0, 0, size, size);
                context.drawImage(image, 0, 0, size, size);
                const link = document.createElement('a');
                link.download = @js('codigo-acceso-'.$member->member_number.'.png');
                link.href = canvas.toDataURL('image/png');
                link.click();
            };
            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
        });
    </script>
</body>
</html>
