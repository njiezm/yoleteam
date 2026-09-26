{{-- Outing page: consignes, impressions and navigation figures (duration, distance, average speed). --}}
@php
    $decimal = fn (float $value) => number_format($value, 1, ',', ' ');
    $speed = $outing->averageSpeedKnots();
    $impressions = collect(['Avant la sortie' => $outing->notes_before, 'Pendant' => $outing->notes_during, 'Après' => $outing->notes_after])->filter(fn ($text) => filled($text));
    $figures = [
        ['Durée', $outing->durationLabel(), 'clock'],
        ['Distance', $outing->distance_nm !== null ? $decimal((float) $outing->distance_nm).' milles' : null, 'pin'],
        ['Vitesse moyenne', $speed !== null ? $decimal($speed).' nœuds' : null, 'wind'],
    ];
@endphp
<section class="card p-5 mt-5" id="notes">
    <x-section-title title="Notes & navigation">
        <a href="{{ route('outings.edit', $outing) }}" class="text-sm font-semibold text-navy-700 flex items-center gap-1"><x-icon name="edit" class="w-4 h-4" />Compléter</a>
    </x-section-title>

    <div class="grid grid-cols-3 gap-3">
        @foreach ($figures as [$label, $value, $icon])
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase muted flex items-center gap-1"><x-icon :name="$icon" class="w-3.5 h-3.5" />{{ $label }}</p>
                <p @class(['mt-1 font-extrabold', 'text-slate-300' => $value === null])>{{ $value ?? '—' }}</p>
            </div>
        @endforeach
    </div>
    @if ($speed === null)
        <p class="text-xs muted mt-2">L’heure de fin et la distance peuvent être saisies après la sortie (« Compléter ») : la durée et la vitesse moyenne sont alors calculées.</p>
    @endif

    @if ($outing->notes)
        <div class="mt-4">
            <p class="text-[11px] font-bold uppercase muted mb-1">Consignes</p>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $outing->notes }}</p>
        </div>
    @endif

    @if ($impressions->isNotEmpty())
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
            @foreach ($impressions as $label => $text)
                <div>
                    <p class="text-[11px] font-bold uppercase muted mb-1">{{ $label }}</p>
                    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    @elseif (! $outing->notes)
        <p class="text-sm muted mt-4">Aucune consigne ni impression pour l’instant.</p>
    @endif
</section>
