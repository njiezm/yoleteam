@php
    $seasons = $races->pluck('season')->unique()->sortDesc()->values();
    $formatPoints = fn (float $points) => rtrim(rtrim(number_format($points, 2, ',', ' '), '0'), ',');
@endphp
<x-layouts.app title="Régates & courses" :crumb="$seasons->isNotEmpty() ? 'Saison'.($seasons->count() > 1 ? 's ' : ' ').$seasons->join(', ') : 'Calendrier des courses'">
    @can('manage')
        <x-slot:actions>
            <a href="{{ route('races.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle régate</a>
        </x-slot:actions>
        <x-slot:sticky>
            <a href="{{ route('races.create') }}" class="btn-primary w-full"><x-icon name="plus" class="w-4 h-4" />Nouvelle régate</a>
        </x-slot:sticky>
    @endcan

    @if ($next)
        @php
            $days = (int) today()->diffInDays($next->start_date);
        @endphp
        <section class="rounded-3xl bg-gradient-to-br from-navy-900 to-navy-700 text-white p-5 lg:p-7 flex flex-col lg:flex-row gap-5 lg:items-center">
            <div class="flex-1">
                <span class="chip bg-sun-400 text-navy-950">Prochaine échéance · {{ $days === 0 ? 'aujourd’hui' : 'J-'.$days }}</span>
                <h2 class="text-2xl font-extrabold mt-3">{{ $next->name }}</h2>
                <p class="text-navy-200 text-sm mt-1">
                    {{ ucfirst($next->start_date->translatedFormat('l j F Y')) }}@if ($next->end_date && ! $next->end_date->isSameDay($next->start_date)) → {{ $next->end_date->translatedFormat('j F Y') }}@endif
                    @if ($next->location) · {{ $next->location }}@endif
                    @if ($next->stages_count) · {{ $next->stages_count }} étape{{ $next->stages_count > 1 ? 's' : '' }}@endif
                </p>
            </div>
            <div class="flex gap-2"><a href="{{ route('races.show', $next) }}" class="btn-sun">Voir la régate<x-icon name="right" class="w-4 h-4" /></a></div>
        </section>
    @endif

    @if ($races->isEmpty())
        <x-empty-state icon="trophy" title="Aucune régate" text="Enregistrez les régates et le Tour des Yoles pour suivre les étapes et les résultats de vos yoles.">
            @can('manage')
                <a href="{{ route('races.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle régate</a>
            @endcan
        </x-empty-state>
    @else
        <div @class(['grid md:grid-cols-2 gap-5', 'mt-5' => $next])>
            @foreach ($races as $race)
                @php
                    $standings = $race->standings();
                    $leader = $standings->first();
                @endphp
                <a href="{{ route('races.show', $race) }}" class="card p-5 hover:shadow-lg transition">
                    <div class="flex items-start gap-4">
                        <span class="w-12 h-12 shrink-0 rounded-2xl grid place-items-center {{ $race->isUpcoming() || $race->isInProgress() ? 'bg-sun-100 text-amber-700' : 'bg-navy-50 text-navy-700' }}"><x-icon name="trophy" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-extrabold">{{ $race->name }}</h3>
                                <span class="chip bg-slate-100 text-slate-700">{{ $race->type->label() }}</span>
                            </div>
                            <p class="text-sm muted mt-0.5">
                                {{ $race->start_date->translatedFormat('j M Y') }}@if ($race->end_date && ! $race->end_date->isSameDay($race->start_date)) → {{ $race->end_date->translatedFormat('j M Y') }}@endif
                                @if ($race->location) · {{ $race->location }}@endif
                            </p>
                            <div class="flex items-center gap-x-4 gap-y-1 mt-3 text-sm flex-wrap">
                                @if ($standings->isNotEmpty())
                                    <span class="flex items-center gap-1.5 font-semibold">
                                        <x-icon name="boat" class="w-4 h-4 text-slate-400" />
                                        {{ $standings->map(fn ($row) => $row['boat']->name)->join(', ') }}
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5 font-semibold"><x-icon name="pin" class="w-4 h-4 text-slate-400" />{{ $race->stages_count }} étape{{ $race->stages_count > 1 ? 's' : '' }}</span>
                            </div>
                        </div>
                        @if ($leader)
                            <div class="text-center shrink-0 max-w-24">
                                <p class="text-3xl font-extrabold text-navy-900">{{ $formatPoints($leader['points']) }}<span class="text-sm"> pts</span></p>
                                <p class="text-[10px] font-bold uppercase muted leading-tight truncate" title="Meilleur cumul · {{ $leader['boat']->name }} · {{ $leader['stages'] }} étape(s)">{{ $leader['boat']->name }}</p>
                            </div>
                        @elseif ($race->isInProgress())
                            <span class="chip bg-emerald-100 text-emerald-800 shrink-0">En cours</span>
                        @elseif ($race->isUpcoming())
                            <span class="chip bg-sky-100 text-sky-800 shrink-0">À venir</span>
                        @else
                            <span class="chip bg-slate-100 text-slate-600 shrink-0">Sans résultat</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
