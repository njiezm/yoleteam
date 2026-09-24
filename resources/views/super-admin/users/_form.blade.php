<form id="user-form" method="POST" action="{{ $action }}" class="max-w-3xl space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold p-3 flex items-center gap-2"><x-icon name="alert" class="w-4 h-4" />Certains champs sont à corriger.</div>
    @endif

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-4">Compte</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="Nom *" name="name"><x-input name="name" :value="$user->name" required maxlength="255" autocomplete="off" /></x-field>
            <x-field label="Adresse e-mail *" name="email"><x-input name="email" type="email" :value="$user->email" required maxlength="255" autocomplete="off" /></x-field>
            <x-field label="Téléphone" name="phone"><x-input name="phone" type="tel" :value="$user->phone" maxlength="30" /></x-field>
            <x-field label="Association *" name="association_id">
                <x-select name="association_id" :options="$associations" :value="$user->association_id" placeholder="Choisir une association" required />
            </x-field>
            <x-field label="Rôle *" name="role" hint="Super admin : accès à toutes les associations et à tous les comptes.">
                <x-select name="role" :options="\App\Enums\UserRole::options()" :value="$user->role" required />
            </x-field>
            <x-field :label="$user->exists ? 'Nouveau mot de passe' : 'Mot de passe *'" name="password" :hint="$user->exists ? 'Laisser vide pour conserver le mot de passe actuel.' : '8 caractères minimum.'">
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(! $user->exists) @class(['input', 'input-error' => $errors->has('password')])>
            </x-field>
        </div>
    </section>

    <button class="btn-primary hidden lg:inline-flex">{{ $user->exists ? 'Enregistrer' : 'Créer le compte' }}</button>
</form>
