@php
    $typeClass = fn ($outing) => match ($outing->type) {
        \App\Enums\OutingType::Regate => 'border-sun-400 bg-sun-100/60',
        \App\Enums\OutingType::Tdy => 'border-sky-400 bg-sky-50',
        default => 'border-navy-500 bg-navy-50/60',
    };
    $time = fn ($outing) => $outing->start_time ? substr($outing->start_time, 0, 5) : null;
    $weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendrier des sorties · {{ $title }} — YoleTeam</title>
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 {{ $view === 'annee' ? 'portrait' : 'landscape' }}; margin: 10mm; }
        @media print {
            body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-avoid-break { break-inside: avoid; }
        }
    </style>
</head>
<body class="font-sans text-navy-950 bg-slate-100 print:bg-white">
<div class="max-w-6xl mx-auto p-4 sm:p-8 print:p-0 print:max-w-none">
    <nav class="flex flex-wrap items-center gap-2 mb-6 print:hidden">
        <a href="{{ route('outings.index') }}" class="btn-ghost btn-sm"><x-icon name="left" class="w-4 h-4" />Sorties</a>
        <div class="seg">
            @foreach (['semaine' => 'Semaine', 'mois' => 'Mois', 'annee' => 'Année'] as $value => $label)
                <a href="{{ route('outings.calendar', ['vue' => $value, 'date' => $date->toDateString()]) }}" @class(['on' => $view === $value])>{{ $label }}</a>
            @endforeach
        </div>
        <a href="{{ route('outings.calendar', ['vue' => $view, 'date' => $previous]) }}" class="btn-ghost btn-sm" aria-label="Période précédente"><x-icon name="left" class="w-4 h-4" />Précédent</a>
        <a href="{{ route('outings.calendar', ['vue' => $view, 'date' => $next]) }}" class="btn-ghost btn-sm" aria-label="Période suivante">Suivant<x-icon name="right" class="w-4 h-4" /></a>
        <button type="button" onclick="window.print()" class="btn-primary btn-sm ml-auto"><x-icon name="printer" class="w-4 h-4" />Imprimer / Enregistrer en PDF</button>
    </nav>

    <header class="flex items-end justify-between gap-4 border-b-2 border-navy-900 pb-3 mb-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $associationName }}</p>
            <h1 class="text-2xl font-extrabold tracking-tight">Calendrier des sorties · {{ $title }}</h1>
        </div>
        <div class="flex gap-3 text-[11px] font-semibold text-slate-600">
            <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm bg-navy-500"></i>Entraînement</span>
            <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm bg-sun-400"></i>Course</span>
            <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm bg-sky-400"></i>TDY</span>
        </div>
    </header>

    @if ($view === 'semaine')
        <div class="grid grid-cols-7 gap-2">
            @for ($day = $start; $day->lte($end); $day = $day->addDay())
                <section class="bg-white rounded-lg border border-slate-200 min-h-64 p-2 print-avoid-break">
                    <p @class(['text-xs font-bold uppercase', 'text-sun-600' => $day->isToday(), 'text-slate-500' => ! $day->isToday()])>{{ $weekDays[$day->dayOfWeekIso - 1] }}</p>
                    <p class="text-lg font-extrabold leading-tight mb-2">{{ $day->translatedFormat('j M') }}</p>
                    <div class="space-y-1.5">
                        @foreach ($outingsByDay->get($day->toDateString(), collect()) as $outing)
                            <div class="border-l-4 rounded p-1.5 text-xs {{ $typeClass($outing) }}">
                                @if ($outing->time_range)<p class="font-bold tabular-nums">{{ $outing->time_range }}</p>@endif
                                <p class="font-bold">{{ $outing->title }}</p>
                                <p class="text-slate-600">{{ $outing->type->label() }}</p>
                                @if ($outing->location)<p class="text-slate-600">{{ $outing->location }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endfor
        </div>
    @elseif ($view === 'mois')
        <div class="grid grid-cols-7 gap-1">
            @foreach ($weekDays as $dayName)
                <p class="text-center text-[11px] font-bold uppercase text-slate-500 py-1">{{ $dayName }}</p>
            @endforeach
            @for ($day = $start->startOfWeek(); $day->lte($end->endOfWeek()); $day = $day->addDay())
                <section @class(['rounded-md border min-h-24 p-1.5 print-avoid-break', 'bg-white border-slate-200' => $day->month === $start->month, 'bg-slate-50 border-slate-100 text-slate-400' => $day->month !== $start->month])>
                    <p class="text-xs font-extrabold">{{ $day->day }}</p>
                    @if ($day->month === $start->month)
                        <div class="space-y-1 mt-1">
                            @foreach ($outingsByDay->get($day->toDateString(), collect()) as $outing)
                                <div class="border-l-4 rounded px-1 py-0.5 text-[10px] leading-tight {{ $typeClass($outing) }}">
                                    <p><span class="font-bold tabular-nums">{{ $time($outing) }}</span> <span class="font-semibold">{{ $outing->title }}</span></p>
                                    @if ($outing->location)<p class="text-slate-600">{{ $outing->location }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endfor
        </div>
    @else
        <div class="grid grid-cols-3 gap-3">
            @for ($month = $start; $month->lte($end); $month = $month->addMonthNoOverflow())
                @php($monthOutings = $outingsByMonth->get($month->month, collect()))
                <section class="bg-white rounded-lg border border-slate-200 p-2.5 print-avoid-break">
                    <h2 class="font-extrabold text-sm border-b border-slate-200 pb-1 mb-1.5">{{ ucfirst($month->translatedFormat('F')) }} <span class="text-slate-400 font-semibold">· {{ $monthOutings->count() }}</span></h2>
                    @forelse ($monthOutings as $outing)
                        <div class="border-l-4 rounded px-1.5 py-0.5 mb-1 text-[10px] leading-tight {{ $typeClass($outing) }}">
                            <p><span class="font-bold">{{ $outing->date->format('d/m') }}</span>@if ($time($outing)) <span class="tabular-nums">{{ $time($outing) }}</span>@endif · <span class="font-semibold">{{ $outing->title }}</span></p>
                            <p class="text-slate-600">{{ $outing->type->label() }}@if ($outing->location) · {{ $outing->location }}@endif</p>
                        </div>
                    @empty
                        <p class="text-[10px] text-slate-400">Aucune sortie</p>
                    @endforelse
                </section>
            @endfor
        </div>
    @endif

    <p class="mt-4 text-[10px] text-slate-400">Édité le {{ now()->translatedFormat('j F Y à H:i') }} · YoleTeam</p>
</div>
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
</script>
</body>
</html>
