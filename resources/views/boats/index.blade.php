<x-layouts.app title="Yoles" :crumb="trans_choice(':count yole|:count yoles', $boats->count()).' · flotte de l’association'">
    @can('manage')
        <x-slot:actions>
            <a href="{{ route('boats.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Ajouter une yole</a>
        </x-slot:actions>
        <x-slot:sticky>
            <a href="{{ route('boats.create') }}" class="btn-primary w-full"><x-icon name="plus" class="w-4 h-4" />Ajouter une yole</a>
        </x-slot:sticky>
    @endcan

    @if ($boats->isEmpty())
        <x-empty-state icon="boat" title="Aucune yole" text="Ajoutez les yoles de l’association pour composer les plans d’équipage.">
            @can('manage')
                <a href="{{ route('boats.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Ajouter une yole</a>
            @endcan
        </x-empty-state>
    @else
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach ($boats as $boat)
                @php
                    $default = $boat->primaryConfiguration();
                @endphp
                <a href="{{ route('boats.show', $boat) }}" class="card overflow-hidden hover:shadow-lg transition group">
                    <div class="h-2" style="background: {{ $boat->color() }}"></div>
                    <div class="p-5 flex gap-4">
                        <div class="w-24 h-44 shrink-0 rounded-xl overflow-hidden bg-sea-50">
                            @if ($drawings[$boat->id])
                                <x-yole :data="$drawings[$boat->id]" class="h-full mx-auto" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-lg font-extrabold truncate group-hover:text-navy-600">{{ $boat->name }}</h3>
                                @if ($boat->is_active)
                                    <span class="chip bg-emerald-100 text-emerald-800 shrink-0">Opérationnelle</span>
                                @else
                                    <span class="chip bg-amber-100 text-amber-800 shrink-0">Indisponible</span>
                                @endif
                            </div>
                            <p class="text-sm muted truncate">{{ $boat->sponsor ?: 'Sans sponsor' }}</p>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-[11px] font-bold uppercase muted">Longueur</dt>
                                    <dd class="font-bold">{{ $boat->length_m !== null ? number_format((float) $boat->length_m, 2, ',', ' ').' m' : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-bold uppercase muted">Équipage</dt>
                                    <dd class="font-bold">{{ $default ? $default->positions->count().' postes' : '—' }}</dd>
                                </div>
                            </dl>
                            <p class="text-[11px] font-bold uppercase muted mt-4 mb-1.5">Configurations</p>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($boat->configurations as $configuration)
                                    <span class="chip {{ $configuration->is_default ? 'bg-navy-900 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $configuration->name }} · {{ $configuration->bwa_count }} bwa{{ $configuration->is_default ? ' · défaut' : '' }}</span>
                                @empty
                                    <span class="text-xs muted">Aucune configuration</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
