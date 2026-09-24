<x-layouts.guest title="Connexion">
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
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" checked class="w-4 h-4 accent-navy-900">Rester connecté</label>
            <a class="font-semibold text-navy-700" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
        </div>
        <button class="btn-primary w-full h-12">Se connecter</button>
    </form>
</x-layouts.guest>
