@php
    $isSelf = $user->is(auth()->user());
@endphp
<x-layouts.app :title="$user->name" crumb="Super admin · Utilisateurs" :back="route('super-admin.users.index')">
    <x-slot:actions>
        <a href="{{ route('super-admin.users.index') }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="user-form" class="btn-primary btn-sm">Enregistrer</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="user-form" class="btn-primary w-full h-12">Enregistrer le compte</button>
    </x-slot:sticky>

    <div class="flex flex-wrap items-center gap-2 mb-5 max-w-3xl">
        @if ($user->isDisabled())
            <span class="chip bg-red-50 text-red-700">Désactivé depuis le {{ $user->disabled_at->translatedFormat('j M Y') }}</span>
        @else
            <span class="chip bg-emerald-50 text-emerald-700">Actif</span>
        @endif
        <span class="text-xs muted">Dernière connexion : {{ $user->last_login_at?->translatedFormat('j M Y à H:i') ?? 'jamais' }}</span>
    </div>

    @error('reset')
        <div class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold p-3 mb-5 flex items-center gap-2 max-w-3xl"><x-icon name="alert" class="w-4 h-4" />{{ $message }}</div>
    @enderror

    @include('super-admin.users._form', ['action' => route('super-admin.users.update', $user), 'method' => 'PUT'])

    <section class="card p-5 lg:p-6 max-w-3xl mt-5">
        <h3 class="font-bold mb-3">Actions</h3>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('super-admin.users.reset-link', $user) }}">
                @csrf
                <button class="btn-ghost btn-sm"><x-icon name="mail" class="w-4 h-4" />Envoyer un lien de réinitialisation</button>
            </form>
            @unless ($isSelf)
                <form method="POST" action="{{ route('super-admin.users.toggle', $user) }}">
                    @csrf
                    <button class="btn-ghost btn-sm"><x-icon name="lock" class="w-4 h-4" />{{ $user->isDisabled() ? 'Réactiver le compte' : 'Désactiver le compte' }}</button>
                </form>
                <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer définitivement le compte de {{ $user->name }} ?">
                    @csrf
                    @method('DELETE')
                    <button class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="w-4 h-4" />Supprimer</button>
                </form>
            @endunless
        </div>
        @if ($isSelf)
            <p class="text-xs muted mt-3">Vous ne pouvez ni désactiver ni supprimer votre propre compte.</p>
        @endif
    </section>
</x-layouts.app>
