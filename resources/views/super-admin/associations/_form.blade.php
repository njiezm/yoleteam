@php
    $currentColor = strtoupper((string) old('primary_color', $association->primary_color));
@endphp
<form id="association-form" method="POST" action="{{ $action }}" class="max-w-3xl space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold p-3 flex items-center gap-2"><x-icon name="alert" class="w-4 h-4" />Certains champs sont à corriger.</div>
    @endif

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-4">Association</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="Nom *" name="name"><x-input name="name" :value="$association->name" required maxlength="255" autocomplete="off" /></x-field>
            <x-field label="Commune" name="city"><x-input name="city" :value="$association->city" maxlength="255" /></x-field>
            <x-field label="Identifiant" name="slug" hint="Généré à partir du nom s’il est laissé vide." class="sm:col-span-2">
                <x-input name="slug" :value="$association->slug" maxlength="255" placeholder="yole-nou" autocomplete="off" />
            </x-field>
            <x-field label="Couleur principale" name="primary_color" class="sm:col-span-2">
                <div class="flex flex-wrap gap-2">
                    @foreach (collect($colors)->push($currentColor)->unique() as $color)
                        <label class="cursor-pointer">
                            <input type="radio" name="primary_color" value="{{ $color }}" class="peer sr-only" @checked($currentColor === $color)>
                            <span class="block w-10 h-10 rounded-xl peer-checked:ring-4 peer-checked:ring-sun-400/60 peer-focus-visible:outline-2 peer-focus-visible:outline-navy-900" style="background: {{ $color }}" title="{{ $color }}"></span>
                        </label>
                    @endforeach
                </div>
            </x-field>
        </div>
    </section>

    @unless ($association->exists)
        <section class="card p-5 lg:p-6">
            <h3 class="font-bold mb-1">Premier compte bureau</h3>
            <p class="text-xs muted mb-4">Facultatif : crée un administrateur rattaché à cette association.</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-field label="Nom" name="admin_name"><x-input name="admin_name" maxlength="255" autocomplete="off" /></x-field>
                <x-field label="Adresse e-mail" name="admin_email"><x-input name="admin_email" type="email" maxlength="255" autocomplete="off" /></x-field>
                <x-field label="Mot de passe" name="admin_password" hint="8 caractères minimum.">
                    <input id="admin_password" name="admin_password" type="password" minlength="8" autocomplete="new-password" @class(['input', 'input-error' => $errors->has('admin_password')])>
                </x-field>
            </div>
        </section>
    @endunless

    <button class="btn-primary hidden lg:inline-flex">{{ $association->exists ? 'Enregistrer' : 'Créer l’association' }}</button>
</form>
