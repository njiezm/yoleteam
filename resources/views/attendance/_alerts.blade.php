{{-- Attendance alerts card. Expects $alerts (AttendanceAlerts::for), optional $limit, $withRules. --}}
@php
    $limit ??= null;
    $shown = $limit ? $alerts->take($limit) : $alerts;
@endphp
<section class="card p-5">
    <x-section-title :title="'Alertes de présence'.($alerts->isNotEmpty() ? ' ('.$alerts->count().')' : '')">
        @if ($limit)
            <a href="{{ route('attendance.stats') }}#regles" class="text-sm font-semibold text-navy-700">Détail & règles</a>
        @endif
    </x-section-title>
    @forelse ($shown as $alert)
        <a href="{{ route('members.show', $alert['member']) }}" class="flex items-center gap-3 py-2 -mx-2 px-2 rounded-xl hover:bg-slate-50">
            <x-avatar :member="$alert['member']" size="w-9 h-9 text-xs" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold truncate">{{ $alert['member']->full_name }}</p>
                <p class="text-xs muted truncate">{{ $alert['detail'] }}</p>
            </div>
            <span @class(['chip whitespace-nowrap', 'bg-red-100 text-red-700' => $alert['type'] === 'consecutive', 'bg-amber-100 text-amber-800' => $alert['type'] === 'irregular'])>{{ $alert['title'] }}</span>
        </a>
    @empty
        <p class="text-sm muted">Aucune alerte : tout le monde est régulier aux entraînements.</p>
    @endforelse
    @if ($limit && $alerts->count() > $limit)
        <a href="{{ route('attendance.stats') }}" class="text-xs font-semibold text-navy-700 mt-2 inline-block">+ {{ $alerts->count() - $limit }} autre(s)</a>
    @endif

    @if ($withRules ?? false)
        <div id="regles" class="mt-4 pt-4 border-t border-slate-100 text-sm text-slate-600 space-y-2 scroll-mt-24">
            <p class="font-bold text-navy-950">Comment sont calculées les alertes ?</p>
            <p>Seuls les <b>entraînements</b> dont l’appel a été fait sont pris en compte. Un <b>excusé</b> ne compte ni comme absence ni comme présence.</p>
            <p><span class="chip bg-red-100 text-red-700">{{ \App\Services\AttendanceAlerts::CONSECUTIVE_MISSES }} manqués d’affilée</span> le membre était absent (ou non pointé) aux {{ \App\Services\AttendanceAlerts::CONSECUTIVE_MISSES }} derniers entraînements ou plus, sans excuse.</p>
            <p><span class="chip bg-amber-100 text-amber-800">Présence irrégulière</span> sur les {{ \App\Services\AttendanceAlerts::WINDOW }} derniers entraînements, le membre est venu (présent ou en retard) à moins de {{ \App\Services\AttendanceAlerts::MIN_RATE }} % d’entre eux — calculé seulement à partir de {{ \App\Services\AttendanceAlerts::MIN_COUNTED }} entraînements.</p>
            <p class="text-xs muted">Les membres inactifs ne sont pas concernés ; les entraînements antérieurs à l’inscription d’un membre ne comptent pas.</p>
        </div>
    @endif
</section>
