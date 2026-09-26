@php
    $sides = ['babord' => 'Bâbord', 'tribord' => 'Tribord', 'centre' => 'Axe'];
    $configurationErrors = $errors->getBag('configuration');
    $newConfigurationErrors = $errors->getBag('newConfiguration');
@endphp
<x-layouts.app :title="$boat->name.' · Configuration'" crumb="Yoles" :back="route('boats.index')">
    @can('manage')
        <x-slot:actions>
            <a href="#nouvelle-configuration" class="btn-ghost btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle configuration</a>
            @if ($configuration)
                <button form="configuration-form" class="btn-primary btn-sm">Enregistrer</button>
            @endif
        </x-slot:actions>
        @if ($configuration)
            <x-slot:sticky>
                <button form="configuration-form" class="btn-primary w-full">Enregistrer la configuration</button>
            </x-slot:sticky>
        @endif
    @endcan

    <div class="grid gap-5 lg:grid-cols-[1fr_400px]">
        <div class="space-y-5 min-w-0">
            <section class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($boat->configurations->isNotEmpty())
                        <div class="seg max-w-full overflow-x-auto scrollbar-none">
                            @foreach ($boat->configurations as $item)
                                <a href="{{ route('boats.show', [$boat, 'configuration' => $item->id]) }}" @class(['whitespace-nowrap', 'on' => $configuration?->is($item)])>
                                    {{ $item->name }}
                                    @if ($item->is_default)
                                        <x-icon name="star" class="w-3 h-3 fill-current text-sun-500" />
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($boat->is_active)
                        <span class="chip bg-emerald-100 text-emerald-800">Opérationnelle</span>
                    @else
                        <span class="chip bg-amber-100 text-amber-800">Indisponible / en réparation</span>
                    @endif
                </div>

                @if ($configuration)
                    <div class="mt-5">
                        @can('manage')
                            @include('boats._configuration-form', [
                                'action' => route('boats.configurations.update', [$boat, $configuration]),
                                'method' => 'PUT',
                                'formKey' => 'configuration-'.$configuration->id,
                                'bag' => $configurationErrors,
                                'configuration' => $configuration,
                                'submit' => 'Enregistrer la configuration',
                                'id' => 'configuration-form',
                            ])
                            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                                @include('boats._error', ['bag' => $configurationErrors, 'key' => 'delete'])
                                <form method="POST" action="{{ route('boats.configurations.destroy', [$boat, $configuration]) }}" class="ml-auto" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer la configuration « {{ $configuration->name }} » et ses postes ?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer la configuration</button>
                                </form>
                            </div>
                        @else
                            <dl class="grid grid-cols-3 gap-4 text-sm">
                                <div><dt class="text-[11px] font-bold uppercase muted">Nom</dt><dd class="font-bold">{{ $configuration->name }}</dd></div>
                                <div><dt class="text-[11px] font-bold uppercase muted">Voiles</dt><dd class="font-bold">{{ $configuration->sail_count }}</dd></div>
                                <div><dt class="text-[11px] font-bold uppercase muted">Bwa dressés</dt><dd class="font-bold">{{ $configuration->bwa_count }}</dd></div>
                            </dl>
                            @if ($configuration->is_default)
                                <p class="text-xs muted mt-3">Configuration par défaut de la yole.</p>
                            @endif
                        @endcan
                    </div>
                @else
                    <p class="text-sm muted mt-4">Cette yole n’a encore aucune configuration.</p>
                @endif
            </section>

            @if ($configuration)
                <section class="card overflow-hidden">
                    <div class="p-5 pb-3 flex items-center justify-between gap-3">
                        <h3 class="font-bold">Postes ({{ $configuration->crewSeatCount() }} + {{ \App\Services\BoatLayoutGenerator::MAX_FONDS }} places de fond au choix)</h3>
                        <span class="text-xs muted">Générés automatiquement</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px]">
                            <thead class="bg-slate-50">
                                <tr><th class="th">Poste</th><th class="th">Rôle</th><th class="th">Côté</th><th class="th">Bwa</th><th class="th">Optionnel</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($configuration->positions as $position)
                                    <tr>
                                        <td class="td font-semibold">{{ $position->label }}</td>
                                        <td class="td"><x-role-pill :role="$position->crewRole" /></td>
                                        <td class="td text-sm">{{ $position->bwa_index !== null ? 'Au vent' : $sides[$position->side->value] }}</td>
                                        <td class="td text-sm">{{ $position->bwa_index ?? '—' }}</td>
                                        <td class="td">
                                            @if ($position->is_optional)
                                                <span class="chip bg-slate-100 text-slate-600">Oui</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @can('manage')
                <section class="card p-5 scroll-mt-24" id="nouvelle-configuration">
                    <h3 class="font-bold mb-4">Nouvelle configuration</h3>
                    @include('boats._configuration-form', [
                        'action' => route('boats.configurations.store', $boat),
                        'method' => 'POST',
                        'formKey' => 'new-configuration',
                        'bag' => $newConfigurationErrors,
                        'configuration' => null,
                        'submit' => 'Créer la configuration',
                        'id' => 'new-configuration-form',
                    ])
                    <button form="new-configuration-form" class="btn-ghost w-full mt-4 lg:hidden"><x-icon name="plus" class="w-4 h-4" />Créer la configuration</button>
                </section>

                <section class="card p-5 lg:p-6">
                    <h3 class="font-bold mb-4">Informations de la yole</h3>
                    <form method="POST" action="{{ route('boats.update', $boat) }}">
                        @csrf
                        @method('PUT')
                        @if ($configuration)
                            <input type="hidden" name="configuration" value="{{ $configuration->id }}">
                        @endif
                        @include('boats._fields', ['boat' => $boat])
                        <button class="btn-primary btn-sm mt-4">Enregistrer la yole</button>
                    </form>
                    <div class="mt-5 pt-5 border-t border-slate-100">
                        @error('delete')
                            <p class="text-sm text-red-600 font-semibold mb-3 flex items-start gap-1.5"><x-icon name="alert" class="w-4 h-4 mt-0.5" />{{ $message }}</p>
                        @enderror
                        <form method="POST" action="{{ route('boats.destroy', $boat) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Supprimer définitivement la yole {{ $boat->name }} ? Cette action est irréversible.">
                            @csrf
                            @method('DELETE')
                            @if ($historyCount)
                                <label class="flex items-start gap-2 text-sm mb-3 p-3 rounded-xl bg-red-50 text-red-800">
                                    <input type="checkbox" name="with_history" value="1" class="w-4 h-4 mt-0.5 accent-red-600">
                                    <span>Supprimer aussi son historique : {{ $historyCount }}. Pour le garder, décochez « Opérationnelle » ci-dessus à la place.</span>
                                </label>
                            @endif
                            <button class="btn-danger btn-sm"><x-icon name="trash" class="w-4 h-4" />Supprimer la yole</button>
                        </form>
                    </div>
                </section>
            @else
                <section class="card p-5">
                    <h3 class="font-bold mb-3">Informations de la yole</h3>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-[11px] font-bold uppercase muted">Longueur</dt><dd class="font-bold">{{ $boat->length_m !== null ? number_format((float) $boat->length_m, 2, ',', ' ').' m' : '—' }}</dd></div>
                        <div><dt class="text-[11px] font-bold uppercase muted">Coque</dt><dd class="font-bold flex items-center gap-1.5"><i class="w-3 h-3 rounded-full" style="background: {{ $boat->color() }}"></i>{{ $boat->hull_color ? ucfirst($boat->hull_color) : '—' }}</dd></div>
                    </dl>
                    @if ($boat->notes)
                        <p class="text-sm mt-4 whitespace-pre-line">{{ $boat->notes }}</p>
                    @endif
                </section>
            @endcan
        </div>

        @if ($drawing)
            <aside class="card p-4 lg:sticky lg:top-24 self-start">
                <p class="font-bold mb-2">Aperçu · {{ $configuration->name }}</p>
                <x-yole :data="$drawing" class="max-w-[360px] mx-auto" />
            </aside>
        @endif
    </div>
</x-layouts.app>
