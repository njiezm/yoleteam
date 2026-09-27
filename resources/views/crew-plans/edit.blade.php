<x-layouts.app title="Plan d’équipage" :crumb="ucfirst($outing->date->translatedFormat('D j M')).' · '.$outing->title.' · '.$plan->name()" :back="route('outings.show', $outing).'#equipages'">
    <x-slot:actions>
        <button type="button" data-action="reset" class="btn-ghost btn-sm"><x-icon name="refresh" class="w-4 h-4" />Vider</button>
        <a href="{{ route('crew-plans.show', [$outing, $plan]) }}" class="btn-ghost btn-sm" data-preview-link><x-icon name="printer" class="w-4 h-4" />Aperçu</a>
        <button type="button" data-action="validate" class="btn-sun btn-sm"><x-icon name="check" class="w-4 h-4" />Valider le plan</button>
    </x-slot:actions>
    <x-slot:sticky>
        <div class="flex gap-2">
            <div class="flex-1 card px-3 flex items-center gap-2 h-12">
                <span class="text-sm font-bold" data-progress-text></span>
                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-navy-900" data-progress-bar></div></div>
                <span class="chip" data-balance-chip></span>
            </div>
            <button type="button" data-action="validate" class="btn-sun h-12">Valider</button>
        </div>
    </x-slot:sticky>

    <x-outing-picker :current="$outing" target="plans" class="mb-4 max-w-xl" />

    @if ($plans->count() > 1)
        {{-- Several boats: every crew is composed on this page (tabs), with one pool of rowers. --}}
        <div class="card p-3 mb-4 flex flex-col lg:flex-row lg:items-center gap-3" data-plan-tabs>
            <div class="seg overflow-x-auto scrollbar-none max-w-full" role="tablist" aria-label="Yoles de la sortie">
                @foreach ($plans as $tab)
                    <button type="button" role="tab" data-switch-plan="{{ $tab->id }}" aria-selected="{{ $tab->is($plan) ? 'true' : 'false' }}" @class(['whitespace-nowrap', 'on' => $tab->is($plan)])
                            data-edit-url="{{ route('crew-plans.edit', [$outing, $tab]) }}" data-show-url="{{ route('crew-plans.show', [$outing, $tab]) }}" data-crumb="{{ $tab->name() }}">
                        <i class="w-2.5 h-2.5 rounded-full" style="background: {{ $tab->boat->color() }}"></i>{{ $tab->hasRaceLabel() ? $tab->boat->name.' · M'.$tab->race_number : $tab->boat->name }}
                        <span class="text-[11px] muted tabular-nums" data-tab-count="{{ $tab->id }}">{{ $tab->assignments->count() }}/{{ $tab->configuration->crewSeatCount() }}</span>
                    </button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:ml-auto">
                <button type="button" data-share-toggle data-share-url="{{ route('outings.share-crew.update', $outing) }}" data-outing-uuid="{{ $outing->uuid }}" aria-pressed="{{ $outing->share_crew ? 'true' : 'false' }}"
                        @class(['btn-sm btn', 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' => $outing->share_crew, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => ! $outing->share_crew])
                        title="Autoriser ou non un coursier à être placé sur plusieurs yoles">
                    <x-icon name="lock" :class="$outing->share_crew ? 'w-4 h-4 hidden' : 'w-4 h-4'" data-share-icon="lock" />
                    <x-icon name="unlock" :class="$outing->share_crew ? 'w-4 h-4' : 'w-4 h-4 hidden'" data-share-icon="unlock" />
                    <span data-share-label>{{ $outing->share_crew ? 'Coursiers réutilisables' : 'Un coursier = une yole' }}</span>
                </button>
                <a href="{{ route('crew-plans.index', $outing) }}" class="btn-ghost btn-sm"><x-icon name="boat" class="w-4 h-4" />Voir tous les plans</a>
            </div>
        </div>
    @endif

    @foreach ($plans as $panel)
        <div data-editor-panel="{{ $panel->id }}" @unless ($panel->is($plan)) hidden @endunless>
            @include('crew-plans._editor', ['inline' => false, 'plan' => $panel, 'editor' => $editors[$panel->id]])
        </div>
    @endforeach
</x-layouts.app>
