<x-layouts.app title="Sorties" crumb="Entraînements, courses et TDY">
    <x-slot:actions>
        <a href="{{ route('outings.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
    </x-slot:actions>
    <x-slot:sticky>
        <a href="{{ route('outings.create') }}" class="btn-primary w-full h-12"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
    </x-slot:sticky>

    @php
        $start = $month->copy()->startOfWeek();
        $end = $month->copy()->endOfMonth()->endOfWeek();
        $dotColor = fn ($outing) => match ($outing->type) {
            \App\Enums\OutingType::Regate => 'bg-sun-400',
            \App\Enums\OutingType::Tdy => 'bg-sky-400',
            default => 'bg-navy-500',
        };
    @endphp
    <div class="card p-4">
        <div class="flex items-center justify-between">
            <p class="font-bold">{{ ucfirst($month->translatedFormat('F Y')) }}</p>
            <div class="flex gap-1">
                <a href="{{ route('outings.index', ['mois' => $month->copy()->subMonth()->format('Y-m'), 'filtre' => $filter]) }}" class="w-9 h-9 grid place-items-center rounded-lg hover:bg-slate-100" aria-label="Mois précédent"><x-icon name="left" class="w-4 h-4" /></a>
                <a href="{{ route('outings.index', ['mois' => $month->copy()->addMonth()->format('Y-m'), 'filtre' => $filter]) }}" class="w-9 h-9 grid place-items-center rounded-lg hover:bg-slate-100" aria-label="Mois suivant"><x-icon name="right" class="w-4 h-4" /></a>
            </div>
        </div>
        <div class="grid grid-cols-7 gap-1.5 mt-3 text-center">
            @foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $day)
                <p class="text-[11px] font-bold muted">{{ $day }}</p>
            @endforeach
            @for ($day = $start->copy(); $day->lte($end); $day->addDay())
                @php($dayOutings = $calendar->get($day->toDateString(), collect()))
                @php($isToday = $day->isToday())
                <a href="{{ $dayOutings->count() === 1 ? route('outings.show', $dayOutings->first()) : route('outings.create', ['date' => $day->toDateString()]) }}"
                   title="{{ $dayOutings->pluck('title')->join(' · ') ?: 'Programmer une sortie' }}"
                   @class([
                       'aspect-square sm:aspect-auto sm:h-14 rounded-xl p-1 flex flex-col items-center justify-center gap-1 hover:ring-2 hover:ring-sun-400/60',
                       'bg-navy-900 text-white' => $isToday,
                       'bg-slate-50' => ! $isToday && $day->month === $month->month,
                       'text-slate-300' => $day->month !== $month->month,
                   ])>
                    <span class="text-sm font-bold">{{ $day->day }}</span>
                    @if ($dayOutings->isNotEmpty())
                        <span class="flex gap-0.5">
                            @foreach ($dayOutings->take(3) as $dayOuting)
                                <i class="w-1.5 h-1.5 rounded-full {{ $dotColor($dayOuting) }}"></i>
                            @endforeach
                        </span>
                    @endif
                </a>
            @endfor
        </div>
        @php($printDate = today()->isSameMonth($month) ? today()->toDateString() : $month->toDateString())
        <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
            <div class="flex gap-4 text-[11px] muted">
                <span class="flex items-center gap-1.5"><i class="w-2 h-2 rounded-full bg-navy-500"></i>Entraînement</span>
                <span class="flex items-center gap-1.5"><i class="w-2 h-2 rounded-full bg-sun-400"></i>Course</span>
                <span class="flex items-center gap-1.5"><i class="w-2 h-2 rounded-full bg-sky-400"></i>TDY</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-[11px] font-bold uppercase muted flex items-center gap-1"><x-icon name="printer" class="w-3.5 h-3.5" />PDF</span>
                <div class="seg">
                    @foreach (['semaine' => 'Semaine', 'mois' => 'Mois', 'annee' => 'Année'] as $view => $label)
                        <a href="{{ route('outings.calendar', ['vue' => $view, 'date' => $printDate]) }}" target="_blank" rel="noopener">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between mt-5 mb-2">
        <div class="seg">
            @foreach (['a-venir' => 'À venir', 'passees' => 'Passées', 'toutes' => 'Toutes'] as $value => $label)
                <a href="{{ route('outings.index', ['filtre' => $value, 'mois' => $month->format('Y-m')]) }}" @class(['on' => $filter === $value])>{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="hidden mb-5" data-offline-outings></div>

    @if ($outings->isEmpty())
        <x-empty-state title="Aucune sortie" text="Programmez un entraînement ou une régate pour faire l’appel et composer les équipages.">
            <a href="{{ route('outings.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="w-4 h-4" />Nouvelle sortie</a>
        </x-empty-state>
    @else
        <div class="card px-4 lg:px-5 divide-y divide-slate-100">
            @foreach ($outings as $outing)
                <x-outing-row :outing="$outing" />
            @endforeach
        </div>
        <div class="mt-4">{{ $outings->links() }}</div>
    @endif
</x-layouts.app>
