@php
    $hasOld = session()->hasOldInput();
    $selectedRoles = array_map('intval', $hasOld ? (array) old('roles', []) : $member->crewRoles->pluck('id')->all());
    $preferredRoles = array_map('intval', $hasOld ? (array) old('preferred', []) : $member->crewRoles->filter(fn ($role) => $role->pivot->is_preferred)->pluck('id')->all());
    $gender = (string) old('gender', $member->gender?->value);
    $level = (string) old('level', $member->level?->value);
    $isActive = $hasOld ? (bool) old('is_active') : (bool) $member->is_active;
@endphp
<form id="member-form" method="POST" action="{{ $action }}" class="max-w-4xl space-y-5" data-offline-form="{{ $method === 'PUT' ? 'Membre modifié : '.$member->full_name : 'Nouveau membre' }}" data-offline-redirect="{{ route('members.index') }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold p-3 flex items-center gap-2"><x-icon name="alert" class="w-4 h-4" />Certains champs sont à corriger.</div>
    @endif

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-4">Identité</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="Prénom *" name="first_name"><x-input name="first_name" :value="$member->first_name" required maxlength="100" autocomplete="off" /></x-field>
            <x-field label="Nom *" name="last_name"><x-input name="last_name" :value="$member->last_name" required maxlength="100" autocomplete="off" /></x-field>
            <x-field label="Surnom" name="nickname"><x-input name="nickname" :value="$member->nickname" maxlength="50" /></x-field>
            <x-field label="Date de naissance" name="birth_date"><x-input name="birth_date" type="date" :value="$member->birth_date?->format('Y-m-d')" /></x-field>
            <div class="sm:col-span-2">
                <p class="label">Genre</p>
                <div class="seg">
                    @foreach (\App\Enums\Gender::options() as $value => $label)
                        <label><input type="radio" name="gender" value="{{ $value }}" class="sr-only" @checked($gender === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('gender')
                    <p class="text-xs text-red-600 font-semibold mt-1.5">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-4">Contact</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="Téléphone" name="phone"><x-input name="phone" type="tel" :value="$member->phone" maxlength="30" placeholder="0696 12 34 56" /></x-field>
            <x-field label="E-mail" name="email"><x-input name="email" type="email" :value="$member->email" placeholder="prenom.nom@exemple.fr" /></x-field>
        </div>
    </section>

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-1">Gabarit</h3>
        <p class="text-xs muted mb-4">Utilisé uniquement pour l’indicateur d’équilibre du plan d’équipage.</p>
        <div class="grid grid-cols-2 gap-4">
            <x-field label="Poids (kg)" name="weight_kg"><x-input name="weight_kg" type="number" :value="$member->weight_kg !== null ? (float) $member->weight_kg : null" min="30" max="150" step="0.1" inputmode="decimal" /></x-field>
            <x-field label="Taille (cm)" name="height_cm"><x-input name="height_cm" type="number" :value="$member->height_cm" min="100" max="220" step="1" inputmode="numeric" /></x-field>
        </div>
    </section>

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-4">Profil yoleur</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <p class="label">Niveau *</p>
                <div class="seg flex-wrap">
                    @foreach (\App\Enums\MemberLevel::options() as $value => $label)
                        <label><input type="radio" name="level" value="{{ $value }}" class="sr-only" @checked($level === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('level')
                    <p class="text-xs text-red-600 font-semibold mt-1.5">{{ $message }}</p>
                @enderror
            </div>
            <x-field label="Catégorie" name="category">
                <x-select name="category" :options="\App\Enums\MemberCategory::options()" :value="$member->category" placeholder="—" />
            </x-field>
        </div>

        <p class="label mt-5">Postes maîtrisés <span class="font-normal muted">— l’étoile indique le poste préféré</span></p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach ($crewRoles as $crewRole)
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 has-[.role-check:checked]:border-navy-900 has-[.role-check:checked]:bg-navy-50">
                    <label class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
                        <input type="checkbox" name="roles[]" value="{{ $crewRole->id }}" class="role-check w-4 h-4 accent-navy-900" @checked(in_array($crewRole->id, $selectedRoles, true))>
                        <span class="w-3 h-3 rounded-full shrink-0" style="background: {{ $crewRole->color }}"></span>
                        <span class="font-semibold text-sm flex-1">{{ $crewRole->label }}</span>
                    </label>
                    <label class="cursor-pointer" title="Poste préféré">
                        <input type="checkbox" name="preferred[]" value="{{ $crewRole->id }}" class="peer sr-only" @checked(in_array($crewRole->id, $preferredRoles, true))>
                        <span class="sr-only">Poste préféré : {{ $crewRole->label }}</span>
                        <x-icon name="star" class="w-4 h-4 text-slate-300 peer-checked:text-sun-500 peer-checked:fill-current" />
                    </label>
                </div>
            @endforeach
        </div>
        @error('roles')
            <p class="text-xs text-red-600 font-semibold mt-1.5">{{ $message }}</p>
        @enderror
        @error('roles.*')
            <p class="text-xs text-red-600 font-semibold mt-1.5">{{ $message }}</p>
        @enderror
    </section>

    <section class="card p-5 lg:p-6">
        <h3 class="font-bold mb-4">Notes & statut</h3>
        <x-field label="Notes" name="notes" class="[&>label]:sr-only">
            <textarea id="notes" name="notes" maxlength="5000" @class(['input h-28 py-3', 'input-error' => $errors->has('notes')]) placeholder="Disponibilités, remarques du patron…">{{ old('notes', $member->notes) }}</textarea>
        </x-field>
        <label class="flex items-center justify-between gap-3 mt-4 p-3 rounded-xl bg-slate-50 cursor-pointer">
            <div>
                <p class="font-semibold text-sm">Membre actif</p>
                <p class="text-xs muted">Les membres inactifs n’apparaissent plus dans l’appel.</p>
            </div>
            <input type="checkbox" name="is_active" value="1" class="peer sr-only" @checked($isActive)>
            <span class="w-11 h-6 shrink-0 rounded-full bg-slate-300 relative transition peer-checked:bg-emerald-500 peer-checked:[&>i]:translate-x-5 peer-focus-visible:ring-4 peer-focus-visible:ring-sun-400/30"><i class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition"></i></span>
        </label>
    </section>
</form>
