@php
    $userErrors = $errors->getBag('user');
    $isUserForm = old('_form') === 'user';
    $oldAssociation = fn (string $key, mixed $default = null) => $isUserForm ? $default : old($key, $default);
    $oldUser = fn (string $key, mixed $default = null) => $isUserForm ? old($key, $default) : $default;
    $currentColor = strtoupper((string) $oldAssociation('primary_color', $association->primary_color));
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
@endphp
<x-layouts.app title="Paramètres" crumb="Administration" :back="route('more')">
    <x-slot:actions>
        <button form="association-form" class="btn-primary btn-sm">Enregistrer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="association-form" class="btn-primary w-full">Enregistrer l’association</button>
    </x-slot:sticky>

    <div class="flex gap-1.5 overflow-x-auto scrollbar-none mb-5">
        @foreach (['association' => 'Association', 'utilisateurs' => 'Utilisateurs', 'postes' => 'Postes & rôles'] as $anchor => $label)
            <a href="#{{ $anchor }}" class="chip h-9 px-4 whitespace-nowrap {{ $loop->first ? 'bg-navy-900 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="card p-5 lg:p-6 self-start scroll-mt-24" id="association">
            <h3 class="font-bold mb-4">Association</h3>
            <form method="POST" action="{{ route('settings.update') }}" id="association-form">
                @csrf
                @method('PUT')
                <div class="flex items-center gap-4 mb-5">
                    <span class="w-16 h-16 rounded-2xl text-sun-400 grid place-items-center" style="background: {{ $association->primary_color }}"><x-icon name="boat" class="w-8 h-8" /></span>
                    <div>
                        <p class="font-bold">{{ $association->name }}</p>
                        <p class="text-xs muted">{{ $association->city ?: 'Commune non renseignée' }}</p>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-field label="Nom *" name="name">
                        <input id="name" name="name" value="{{ $oldAssociation('name', $association->name) }}" required maxlength="255" @class(['input', 'input-error' => $errors->has('name')])>
                    </x-field>
                    <x-field label="Commune" name="city">
                        <input id="city" name="city" value="{{ $oldAssociation('city', $association->city) }}" maxlength="255" @class(['input', 'input-error' => $errors->has('city')])>
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
                <button class="btn-primary btn-sm mt-5 hidden lg:inline-flex">Enregistrer l’association</button>
            </form>
        </section>

        <section class="card overflow-hidden self-start scroll-mt-24" id="utilisateurs">
            <div class="p-5 lg:p-6 pb-3 flex items-center justify-between">
                <h3 class="font-bold">Utilisateurs</h3>
                <a href="#nouvel-utilisateur" class="btn-ghost btn-sm"><x-icon name="plus" class="w-4 h-4" />Ajouter</a>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <div class="px-5 lg:px-6 py-3 flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-navy-100 text-navy-800 grid place-items-center font-bold text-sm shrink-0">{{ $initials($user->name) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">{{ $user->name }}@if ($user->is(auth()->user())) <span class="muted font-normal">(vous)</span>@endif</p>
                            <p class="text-xs muted truncate">{{ $user->email }}</p>
                        </div>
                        @if ($user->isSuperAdmin())
                            <span class="chip shrink-0 bg-sun-400 text-navy-950">Super admin</span>
                        @else
                            <span class="chip shrink-0 {{ $user->isAdmin() ? 'bg-navy-900 text-white' : 'bg-sun-100 text-amber-800' }}">{{ $user->isAdmin() ? 'Admin / bureau' : 'Patron' }}</span>
                        @endif
                        @unless ($user->is(auth()->user()) || ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()))
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer le compte de {{ $user->name }} ?">
                                @csrf
                                @method('DELETE')
                                <button class="w-9 h-9 grid place-items-center rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 cursor-pointer" title="Supprimer" aria-label="Supprimer {{ $user->name }}"><x-icon name="trash" class="w-4 h-4" /></button>
                            </form>
                        @endunless
                    </div>
                @endforeach
            </div>
            <div class="px-5 lg:px-6 py-3 text-[12px] muted grid sm:grid-cols-2 gap-3 border-t border-slate-100">
                <p><b class="text-navy-900">Admin / bureau</b> — membres, yoles, régates, utilisateurs, paramètres.</p>
                <p><b class="text-navy-900">Patron</b> — appel, plans d’équipage, sorties, consultation des membres.</p>
            </div>
            <form method="POST" action="{{ route('users.store') }}" class="p-5 lg:p-6 border-t border-slate-100 bg-slate-50/60 scroll-mt-24" id="nouvel-utilisateur">
                @csrf
                <input type="hidden" name="_form" value="user">
                <p class="font-bold mb-3">Ajouter un utilisateur</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="user-name">Nom *</label>
                        <input id="user-name" name="name" value="{{ $oldUser('name') }}" required maxlength="255" autocomplete="off" @class(['input', 'input-error' => $userErrors->has('name')])>
                        @include('boats._error', ['bag' => $userErrors, 'key' => 'name'])
                    </div>
                    <div>
                        <label class="label" for="user-email">Adresse e-mail *</label>
                        <input id="user-email" name="email" type="email" value="{{ $oldUser('email') }}" required maxlength="255" autocomplete="off" @class(['input', 'input-error' => $userErrors->has('email')])>
                        @include('boats._error', ['bag' => $userErrors, 'key' => 'email'])
                    </div>
                    <div>
                        <label class="label" for="user-role">Rôle *</label>
                        @php
                            $role = (string) $oldUser('role', \App\Enums\UserRole::Patron->value);
                        @endphp
                        <select id="user-role" name="role" @class(['input', 'input-error' => $userErrors->has('role')])>
                            <option value="{{ \App\Enums\UserRole::Admin->value }}" @selected($role === \App\Enums\UserRole::Admin->value)>Admin / bureau</option>
                            <option value="{{ \App\Enums\UserRole::Patron->value }}" @selected($role === \App\Enums\UserRole::Patron->value)>Patron</option>
                        </select>
                        @include('boats._error', ['bag' => $userErrors, 'key' => 'role'])
                    </div>
                    <div>
                        <label class="label" for="user-password">Mot de passe *</label>
                        <x-password-input id="user-password" name="password" required minlength="8" autocomplete="new-password" :invalid="$userErrors->has('password')" />
                        @include('boats._error', ['bag' => $userErrors, 'key' => 'password'])
                    </div>
                </div>
                <button class="btn-ghost btn-sm mt-4"><x-icon name="plus" class="w-4 h-4" />Ajouter l’utilisateur</button>
            </form>
        </section>

        <section class="card p-5 lg:p-6 xl:col-span-2 scroll-mt-24" id="postes">
            <h3 class="font-bold mb-1">Postes & rôles</h3>
            <p class="text-xs muted mb-4">Référentiel commun à toutes les yoles. Les couleurs sont utilisées dans le plan d’équipage.</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2">
                @foreach ($roles as $crewRole)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                        <span class="w-9 h-9 rounded-full grid place-items-center text-white text-[10px] font-extrabold shrink-0" style="background: {{ $crewRole->color }}">{{ $crewRole->short() }}</span>
                        <div>
                            <p class="font-semibold text-sm">{{ $crewRole->label }}</p>
                            <p class="text-[11px] muted">{{ $crewRole->zone->label() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
