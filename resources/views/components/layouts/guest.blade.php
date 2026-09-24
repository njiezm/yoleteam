@props(['title'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B2545">
    <link rel="manifest" href="/manifest.webmanifest?v=2">
    <link rel="icon" href="/icons/icon.svg?v=2" type="image/svg+xml">
    <title>{{ $title }} — YoleTeam</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-navy-950">
<div class="min-h-screen grid lg:grid-cols-2">
    <div class="relative hidden lg:flex flex-col justify-between bg-navy-900 text-white p-12 overflow-hidden">
        <x-logo />
        <div class="relative max-w-md">
            <p class="chip bg-sun-400 text-navy-950 mb-4">Saison {{ now()->year }}</p>
            <h2 class="text-4xl font-extrabold tracking-tight leading-tight">L’appel et le plan d’équipage,<br>même sans réseau au bord de l’eau.</h2>
            <p class="mt-4 text-navy-200">Présences du jour, positions sur les bwa dressés, régates : tout votre équipage dans la poche du patron.</p>
        </div>
        <p class="relative text-xs text-navy-300">© {{ now()->year }} YoleTeam</p>
    </div>
    <div class="flex flex-col justify-center px-6 py-10 sm:px-12 bg-white lg:bg-sea-50">
        <div class="w-full max-w-sm mx-auto">
            <div class="lg:hidden mb-10 flex justify-center"><div class="bg-navy-900 rounded-2xl p-3 pr-5"><x-logo /></div></div>
            @if (session('status'))
                <div class="mb-6 rounded-xl bg-emerald-50 text-emerald-800 text-sm p-3 flex gap-2"><x-icon name="check" class="w-4 h-4 mt-0.5" />{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
