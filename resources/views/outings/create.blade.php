<x-layouts.app title="Nouvelle sortie" crumb="Sorties" :back="route('outings.index')">
    <x-slot:actions>
        <a href="{{ route('outings.index') }}" class="btn-ghost btn-sm">Annuler</a>
        <button form="outing-form" class="btn-primary btn-sm">Créer la sortie</button>
    </x-slot:actions>
    <x-slot:sticky>
        <button form="outing-form" class="btn-primary w-full h-12">Créer la sortie</button>
    </x-slot:sticky>

    <form id="outing-form" method="POST" action="{{ route('outings.store') }}" class="max-w-3xl space-y-5" data-offline-form="Nouvelle sortie" data-offline-uuid data-offline-redirect="{{ route('outings.offline') }}?uuid={uuid}">
        @csrf
        @include('outings._form')

        <section class="card p-5 lg:p-6">
            <h3 class="font-bold mb-1">Yoles engagées</h3>
            <p class="text-xs muted mb-4">Un plan d’équipage sera créé pour chaque yole cochée.</p>
            <div class="space-y-2">
                @forelse ($boats as $boat)
                    @php
                        $default = $boat->configurations->firstWhere('is_default', true) ?? $boat->configurations->first();
                        $checked = in_array($boat->id, array_map('intval', old('boats', $boat->is_active ? [$boat->id] : [])), true);
                    @endphp
                    <label @class(['flex items-center gap-3 p-3 rounded-xl border has-checked:border-navy-900 has-checked:bg-navy-50/50', 'border-slate-200', 'opacity-60' => ! $boat->is_active])>
                        <input type="checkbox" name="boats[]" value="{{ $boat->id }}" @checked($checked) @disabled(! $boat->is_active) class="w-4 h-4 accent-navy-900">
                        <span class="w-3 h-8 rounded" style="background: {{ $boat->color() }}"></span>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold">{{ $boat->name }}</p>
                            <p class="text-xs muted">{{ $boat->is_active ? $boat->sponsor : 'Indisponible' }}</p>
                        </div>
                        @if ($boat->is_active && $boat->configurations->count() > 1)
                            <select name="configurations[{{ $boat->id }}]" class="input w-32 h-9">
                                @foreach ($boat->configurations as $configuration)
                                    <option value="{{ $configuration->id }}" @selected((int) old("configurations.$boat->id", $default?->id) === $configuration->id)>{{ $configuration->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @empty
                    <p class="text-sm muted">Aucune yole enregistrée.</p>
                @endforelse
            </div>
            @error('boats.*')<p class="text-xs text-red-600 font-semibold mt-1.5">{{ $message }}</p>@enderror
        </section>
    </form>
</x-layouts.app>
