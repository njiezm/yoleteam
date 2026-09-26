@php
    $rateColor = fn (?int $rate) => \App\Services\AttendanceStats::rateColor($rate);
@endphp
<x-layouts.app title="Statistiques de présence" crumb="Présences" :back="route('attendance.today')">
    <x-slot:actions>
        <a href="{{ route('attendance.today') }}" class="btn-ghost btn-sm"><x-icon name="check-square" class="w-4 h-4" />Faire l’appel</a>
        <a href="{{ route('history.export', array_filter($filters)) }}" class="btn-ghost btn-sm"><x-icon name="download" class="w-4 h-4" />Exporter CSV</a>
    </x-slot:actions>

    @include('attendance._alerts', ['alerts' => $alerts, 'withRules' => true])

    <form method="GET" action="{{ route('attendance.stats') }}" class="flex flex-wrap gap-2 mt-5">
        <select name="period" class="input w-48" aria-label="Période" onchange="this.form.submit()">
            <option value="30" @selected($filters['period'] === '30')>30 derniers jours</option>
            <option value="season" @selected($filters['period'] === 'season')>Saison {{ today()->year }}</option>
        </select>
        <select name="type" class="input w-44" aria-label="Type de sortie" onchange="this.form.submit()">
            <option value="">Tous types</option>
            @foreach (\App\Enums\OutingType::options() as $value => $label)
                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn-ghost" title="Filtrer"><x-icon name="filter" class="w-4 h-4" /><span class="sr-only">Filtrer</span></button>
        <a href="{{ route('history.export', array_filter($filters)) }}" class="btn-ghost lg:hidden ml-auto"><x-icon name="download" class="w-4 h-4" />CSV</a>
    </form>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5 mt-5">
        <x-kpi label="Taux de présence" :value="$overall['rate'] !== null ? $overall['rate'].' %' : '—'" :sub="$outings->count().' sortie'.($outings->count() > 1 ? 's' : '').' · '.$members->count().' membres'" icon="chart" tone="green" />
        <x-kpi label="Présents / sortie" :value="$onSitePerOuting !== null ? number_format($onSitePerOuting, 1, ',', ' ') : '—'" sub="moyenne (présents + retards)" icon="users" />
        <x-kpi label="Retards" :value="$overall['retard']" sub="sur la période" icon="clock" tone="sun" />
        <x-kpi label="Absences non excusées" :value="$overall['absent']" :sub="$unexcusedMembers.' membre'.($unexcusedMembers > 1 ? 's' : '').' concerné'.($unexcusedMembers > 1 ? 's' : '')" icon="alert" tone="sky" />
    </div>

    @if ($outings->isEmpty())
        <x-empty-state class="mt-5" icon="chart" title="Aucun appel sur la période" text="Les statistiques apparaîtront dès qu’un appel aura été fait pour une sortie de cette période." />
    @else
        <div class="card p-5 mt-5 overflow-hidden">
            <x-section-title title="Grille des présences">
                <div class="hidden sm:flex gap-3 text-xs muted">
                    @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                        <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-sm" style="background: {{ $status->color() }}"></i>{{ $status->label() }}</span>
                    @endforeach
                </div>
            </x-section-title>
            <div class="overflow-x-auto -mx-5 px-5">
                <table class="min-w-[640px] w-full">
                    <thead>
                        <tr>
                            <th class="th pl-0">Membre</th>
                            @foreach ($gridOutings as $outing)
                                <th class="th text-center px-1" title="{{ $outing->title }}"><a href="{{ route('outings.show', $outing) }}">{{ $outing->date->format('d/m') }}</a></th>
                            @endforeach
                            <th class="th text-right">Taux</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            @php($rate = $perMember[$member->id]['rate'] ?? null)
                            <tr>
                                <td class="py-1.5 pr-3">
                                    <a href="{{ route('members.show', $member) }}" class="flex items-center gap-2"><x-avatar :member="$member" size="w-7 h-7 text-[10px]" /><span class="text-sm font-semibold whitespace-nowrap">{{ $member->short_name }}</span></a>
                                </td>
                                @foreach ($gridOutings as $outing)
                                    @php($status = $grid->get($member->id)?->get($outing->id))
                                    <td class="px-1 py-1.5"><div @class(['h-7 rounded-md', 'bg-slate-100' => ! $status]) @if ($status) style="background: {{ $status->color() }}" @endif title="{{ $outing->date->format('d/m') }} · {{ $status?->label() ?? 'Non pointé' }}"></div></td>
                                @endforeach
                                <td class="text-right text-sm font-extrabold whitespace-nowrap" style="color: {{ $rateColor($rate) }}">{{ $rate !== null ? $rate.'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs muted mt-3">{{ $gridOutings->count() }} dernière{{ $gridOutings->count() > 1 ? 's' : '' }} sortie{{ $gridOutings->count() > 1 ? 's' : '' }} pointée{{ $gridOutings->count() > 1 ? 's' : '' }} sur {{ $outings->count() }} · taux calculé sur toute la période</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2 mt-5">
            <div class="card p-5">
                <x-section-title title="Les plus assidus" />
                @foreach ($mostAssiduous as $row)
                    <a href="{{ route('members.show', $row['member']) }}" class="flex items-center gap-3 py-2">
                        <span @class(['w-6 text-sm font-extrabold', 'text-sun-500' => $loop->first, 'muted' => ! $loop->first])>{{ $loop->iteration }}</span>
                        <x-avatar :member="$row['member']" size="w-8 h-8 text-[10px]" />
                        <span class="flex-1 text-sm font-semibold truncate">{{ $row['member']->full_name }}</span>
                        <div class="w-24"><x-bar :value="$row['rate']" :color="$rateColor($row['rate'])" /></div>
                        <span class="text-sm font-bold w-10 text-right">{{ $row['rate'] }}%</span>
                    </a>
                @endforeach
            </div>
            <div class="card p-5">
                <x-section-title title="À relancer" />
                @foreach ($toFollowUp as $row)
                    <div class="flex items-center gap-3 py-2">
                        <x-avatar :member="$row['member']" size="w-8 h-8 text-[10px]" />
                        <a href="{{ route('members.show', $row['member']) }}" class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate">{{ $row['member']->full_name }}</p>
                            <p class="text-[11px] muted">{{ $row['absent'] }} absence{{ $row['absent'] > 1 ? 's' : '' }} non excusée{{ $row['absent'] > 1 ? 's' : '' }} sur {{ $row['total'] }} sortie{{ $row['total'] > 1 ? 's' : '' }} pointée{{ $row['total'] > 1 ? 's' : '' }}</p>
                        </a>
                        <span class="text-sm font-bold" style="color: {{ $rateColor($row['rate']) }}">{{ $row['rate'] }}%</span>
                        @if ($row['member']->phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $row['member']->phone) }}" class="w-8 h-8 grid place-items-center rounded-lg bg-slate-100" title="Appeler {{ $row['member']->first_name }}"><x-icon name="phone" class="w-4 h-4" /></a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.app>
