<x-layouts.guest title="Mot de passe oublié">
    <h1 class="text-2xl font-extrabold tracking-tight">Mot de passe oublié</h1>
    <p class="muted text-sm mt-1">Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4">
        @csrf
        <x-field label="Adresse e-mail" name="email">
            <x-input name="email" type="email" autocomplete="username" required autofocus placeholder="vous@exemple.fr" />
        </x-field>
        <button class="btn-primary w-full h-12">Envoyer le lien</button>
    </form>
    <p class="text-sm text-center mt-6"><a class="font-semibold text-navy-700" href="{{ route('login') }}">Retour à la connexion</a></p>
</x-layouts.guest>
