{{--
    Crew plan editor body, used by crew-plans/edit and (as a template for plans created offline) outings/show.
    Expects $editor (array|null: filled by JS for offline plans), $outing, $plan (?CrewPlan), $windOptions, $inline (bool).
--}}
@if ($inline ?? false)
    <div class="card p-3 mb-4 flex flex-wrap items-center gap-2 bg-amber-50 border-amber-200">
        <p class="text-sm font-semibold text-amber-900 flex-1 min-w-0" data-inline-title>Plan créé hors ligne</p>
        <button type="button" data-action="reset" class="btn-ghost btn-sm"><x-icon name="refresh" class="w-4 h-4" />Vider</button>
        <button type="button" data-action="validate" class="btn-sun btn-sm"><x-icon name="check" class="w-4 h-4" />Valider</button>
        <button type="button" data-action="close-inline" class="btn-ghost btn-sm"><x-icon name="x" class="w-4 h-4" />Fermer</button>
    </div>
@endif
    <div data-crew-editor="{{ $editor ? json_encode($editor, JSON_UNESCAPED_UNICODE) : '' }}">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
            <div class="flex flex-wrap items-center gap-2">
                @if ($plan && $outing && $outing->crewPlans->count() > 1)
                    <div class="seg">
                        @foreach ($outing->crewPlans as $other)
                            <a href="{{ route('crew-plans.edit', [$outing, $other]) }}" @class(['on' => $other->id === $plan->id])><i class="w-2.5 h-2.5 rounded-full" style="background: {{ $other->boat->color() }}"></i>{{ $other->boat->name }}</a>
                        @endforeach
                    </div>
                @endif
                <div class="seg" data-configs></div>
                <div class="flex flex-wrap items-center gap-2" data-crew-options></div>
                <label class="chip bg-slate-100 text-slate-700 h-9 pl-3 pr-1 gap-1">
                    <x-icon name="wind" class="w-4 h-4" /><span class="sr-only">Vent</span>
                    <select data-wind-dir class="bg-transparent font-semibold focus:outline-none cursor-pointer" aria-label="Direction du vent">
                        <option value="">Vent ?</option>
                        @foreach ($windOptions as $degrees => $label)
                            <option value="{{ $degrees }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input data-wind-kts type="number" min="0" max="60" inputmode="numeric" class="w-12 bg-white rounded-md px-1.5 h-7 text-center font-semibold focus:outline-none" placeholder="nds" aria-label="Force du vent en nœuds">
                </label>
            </div>
            <div class="hidden lg:flex items-center gap-3 text-sm">
                <span class="font-bold" data-progress-text></span>
                <div class="w-32 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-navy-900" data-progress-bar></div></div>
                <span class="chip" data-status-chip></span>
                <span class="text-xs muted w-28" data-save-state></span>
            </div>
        </div>

        <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[260px_minmax(0,1fr)_340px]">
            <aside class="hidden xl:flex flex-col gap-4">
                <div data-balance></div>
                <div class="card p-4">
                    <p class="font-bold mb-3">Légende des postes</p>
                    <div class="space-y-2" data-legend></div>
                    <div class="mt-3 pt-3 border-t border-slate-100 text-[12px] muted space-y-1.5">
                        <p class="flex items-center gap-2"><span class="w-4 h-4 rounded-full border-2 border-dashed border-slate-400"></span>Poste à pourvoir</p>
                        <p class="flex items-center gap-2"><span class="w-4 h-1.5 rounded bg-[#B7793F]"></span>Bwa dressé (hors coque)</p>
                        <p class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-navy-900 ring-2 ring-white"></span>Mât</p>
                    </div>
                </div>
            </aside>

            <section class="card p-3 lg:p-4">
                <div class="mx-auto max-w-[440px] aspect-[400/820]" data-canvas></div>
                <p class="text-center text-[12px] muted mt-2">Glissez-déposez un membre sur un poste · Touchez un poste pour l’attribuer</p>
            </section>

            <aside class="flex flex-col gap-4">
                <div data-inspector></div>
                <div class="xl:hidden" data-balance></div>
                <div class="card p-4 hidden lg:block">
                    <div class="flex items-center justify-between mb-3"><p class="font-bold">Membres disponibles</p><span class="chip bg-emerald-100 text-emerald-800" data-available-count></span></div>
                    <label class="relative block"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span><input data-member-search type="search" class="input h-10 pl-9" placeholder="Rechercher…" aria-label="Rechercher un membre"></label>
                    <div class="flex gap-1.5 overflow-x-auto scrollbar-none mt-3 -mx-1 px-1" data-role-filters></div>
                    <div class="mt-3 space-y-2 max-h-[560px] overflow-y-auto pr-1" data-member-list></div>
                    <p class="text-[11px] muted mt-3" data-availability-note></p>
                </div>
            </aside>
        </div>

        <div data-sheet></div>
    </div>

        <div class="no-print fixed inset-0 z-[70] bg-navy-950/50 hidden place-items-center p-4" data-validate-modal>
            <form method="POST" action="{{ $plan ? route('crew-plans.validation.store', [$outing, $plan]) : '#' }}" class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-6">
                @csrf
                <span class="w-12 h-12 rounded-2xl bg-sun-100 text-amber-700 grid place-items-center"><x-icon name="check" class="w-6 h-6" /></span>
                <h3 class="text-lg font-extrabold mt-4">Valider le plan d’équipage ?</h3>
                <p class="text-sm muted mt-1" data-validate-summary></p>
                <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm space-y-1.5">
                    <p class="flex justify-between"><span class="muted">Bwa au vent</span><b data-validate-sides></b></p>
                    <p class="flex justify-between"><span class="muted">Avant / arrière</span><b data-validate-ends></b></p>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-5">
                    <button type="button" data-action="close-modal" class="btn-ghost">Annuler</button>
                    <button class="btn-sun" data-validate-submit>Valider</button>
                </div>
            </form>
        </div>
