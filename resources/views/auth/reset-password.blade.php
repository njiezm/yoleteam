<x-layouts.guest title="Nouveau mot de passe">
    <h1 class="text-2xl font-extrabold tracking-tight">Nouveau mot de passe</h1>
    <p class="muted text-sm mt-1">Choisissez un mot de passe d’au moins 8 caractères.</p>
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field label="Adresse e-mail" name="email">
            <x-input name="email" type="email" :value="$email" autocomplete="username" required />
        </x-field>
        <x-field label="Nouveau mot de passe" name="password">
            <x-password-input name="password" autocomplete="new-password" required />
        </x-field>
        <x-field label="Confirmation" name="password_confirmation">
            <x-password-input name="password_confirmation" autocomplete="new-password" required />
        </x-field>
        <button class="btn-primary w-full h-12">Enregistrer le mot de passe</button>
    </form>
</x-layouts.guest>
