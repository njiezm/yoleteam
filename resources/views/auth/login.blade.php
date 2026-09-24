<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B2545">
    <title>Connexion — YoleTeam</title>
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
            <h1 class="text-2xl font-extrabold tracking-tight">Connexion</h1>
            <p class="muted text-sm mt-1">Espace réservé au bureau et aux patrons.</p>
            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
                @csrf
                <x-field label="Adresse e-mail" name="email">
                    <x-input name="email" type="email" autocomplete="username" required autofocus placeholder="vous@exemple.fr" />
                </x-field>
                <x-field label="Mot de passe" name="password">
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="input">
                </x-field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" checked class="w-4 h-4 accent-navy-900">Rester connecté</label>
                <button class="btn-primary w-full h-12">Se connecter</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
