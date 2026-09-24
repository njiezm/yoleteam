<x-layouts.app title="Mon profil" :crumb="$user->role->label().' · '.$user->association->name">
    <div class="max-w-3xl space-y-5">
        <form method="POST" action="{{ route('profile.update') }}" class="card p-5 lg:p-6">
            @csrf
            @method('PUT')
            <h3 class="font-bold mb-4">Informations</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Nom *" name="name" class="sm:col-span-2">
                    <x-input name="name" :value="$user->name" required autocomplete="name" />
                </x-field>
                <x-field label="Adresse e-mail *" name="email" hint="Sert à se connecter et à recevoir le lien « mot de passe oublié ».">
                    <x-input name="email" type="email" :value="$user->email" required autocomplete="email" />
                </x-field>
                <x-field label="Téléphone" name="phone">
                    <x-input name="phone" type="tel" :value="$user->phone" autocomplete="tel" />
                </x-field>
            </div>
            <div class="mt-5 flex justify-end"><button class="btn-primary">Enregistrer</button></div>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card p-5 lg:p-6">
            @csrf
            @method('PUT')
            <h3 class="font-bold mb-4">Mot de passe</h3>
            @php($bag = $errors->getBag('password'))
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach (['current_password' => 'Mot de passe actuel', 'password' => 'Nouveau mot de passe', 'password_confirmation' => 'Confirmation'] as $field => $label)
                    <div>
                        <label class="label" for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="password" required
                               autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                               @class(['input', 'input-error' => $bag->has($field)])>
                        @if ($bag->has($field))
                            <p class="text-xs text-red-600 font-semibold mt-1.5">{{ $bag->first($field) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="text-xs muted mt-3">Au moins 8 caractères.</p>
            <div class="mt-5 flex justify-end"><button class="btn-primary">Changer le mot de passe</button></div>
        </form>

        <form method="POST" action="{{ route('logout') }}" data-logout>
            @csrf
            <button class="btn-ghost w-full text-red-600"><x-icon name="logout" class="w-4 h-4" />Se déconnecter</button>
        </form>
    </div>
</x-layouts.app>
