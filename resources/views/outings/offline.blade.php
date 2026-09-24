{{--
    Outing created offline: this page is cached by the service worker and filled client-side from the queued
    outing (?uuid=…). Appel and crew plans are recorded on the device and replayed once the outing is synced.
--}}
<x-layouts.app title="Sortie créée hors ligne" crumb="Enregistrée sur cet appareil · envoyée au retour du réseau" :back="route('outings.index')">
    @php
        $statuses = \App\Enums\AttendanceStatus::cases();
        $attendances = collect();
    @endphp

    <div data-offline-outing-page>
        <x-empty-state class="hidden" data-offline-missing icon="calendar" title="Sortie introuvable sur cet appareil"
                       text="Elle a sans doute déjà été envoyée au serveur : retrouvez-la dans la liste des sorties.">
            <a href="{{ route('outings.index') }}" class="btn-primary btn-sm">Voir les sorties</a>
        </x-empty-state>

        <div class="hidden space-y-6" data-offline-outing-body>
            <div class="card p-5 flex flex-wrap items-center gap-x-5 gap-y-2 bg-amber-50 border-amber-200">
                <span class="chip bg-amber-400 text-navy-950">Créée hors ligne</span>
                <div class="min-w-0">
                    <p class="font-extrabold text-lg" data-offline-field="title"></p>
                    <p class="text-sm text-amber-900" data-offline-field="meta"></p>
                </div>
            </div>

            <section id="appel">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-extrabold text-lg">Appel</h2>
                    <button type="button" class="btn-ghost btn-sm" data-all-present><x-icon name="check" class="w-4 h-4" />Tous présents</button>
                </div>
                <div data-attendance data-deferred data-url="" data-outing-uuid="" data-outing-label="">
                    <div class="grid grid-cols-5 gap-2">
                        @foreach ($statuses as $status)
                            <div class="rounded-xl p-2.5 text-center" style="background: {{ $status->background() }}; color: {{ $status->textColor() }}">
                                <p class="text-xl font-extrabold" data-count="{{ $status->value }}">0</p>
                                <p class="text-[10px] font-bold uppercase tracking-wide">{{ $status->label() }}</p>
                            </div>
                        @endforeach
                        <div class="rounded-xl p-2.5 text-center bg-slate-100">
                            <p class="text-xl font-extrabold text-slate-600" data-count="none">{{ $members->count() }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">À pointer</p>
                        </div>
                    </div>
                    <label class="relative block mt-4">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><x-icon name="search" class="w-4 h-4" /></span>
                        <input type="search" data-search class="input pl-9" placeholder="Rechercher un membre…" aria-label="Rechercher un membre">
                    </label>
                    <form id="attendance-form" onsubmit="return false">
                        <div class="card mt-4 divide-y divide-slate-100 overflow-hidden">
                            @include('attendance._rows')
                        </div>
                    </form>
                    <p class="text-xs muted text-center mt-4" data-save-state>Chaque clic est enregistré sur l’appareil.</p>
                </div>
            </section>

            <section>
                <h2 class="font-extrabold text-lg mb-3">Plans d’équipage</h2>
                <div class="grid gap-5 lg:grid-cols-2" data-outing-plans data-deferred data-outing-uuid="">
                    <div class="lg:col-span-2 hidden" data-offline-plans></div>
                    @if ($boats->isNotEmpty())
                        <form data-plan-create class="card p-5 border-dashed border-2 border-slate-300 bg-slate-50/50 flex flex-col justify-center gap-3">
                            <p class="font-bold flex items-center gap-2"><x-icon name="plus" class="w-4 h-4" />Engager une yole</p>
                            <div class="flex flex-wrap gap-2">
                                <select name="boat_id" class="input flex-1 min-w-40" aria-label="Yole">
                                    @foreach ($boats as $boat)
                                        <option value="{{ $boat->id }}">{{ $boat->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn-primary">Créer le plan</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        </div>

        <script type="application/json" data-plan-templates>@json($planTemplates, JSON_UNESCAPED_UNICODE)</script>
        <script type="application/json" data-editor-members>@json($editorMembers, JSON_UNESCAPED_UNICODE)</script>
        <template data-offline-editor>
            @include('crew-plans._editor', ['editor' => null, 'plan' => null, 'outing' => null, 'inline' => true])
        </template>
        <div class="hidden" data-offline-editor-host></div>
    </div>
</x-layouts.app>
