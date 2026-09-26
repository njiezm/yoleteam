{{--
    Impressions de navigation: end time, distance and one free text; duration and average speed are computed.
    Expects $outing (may be null on the page of an outing created offline: the JS fills the action), optional
    $redirectTo (page to come back to) and $number (step number).
--}}
@php
    $decimal = fn (float $value) => number_format($value, 1, ',', ' ');
    $speed = $outing?->averageSpeedKnots();
    $figures = $outing ? [
        ['Durée', $outing->durationLabel(), 'clock'],
        ['Distance', $outing->distance_nm !== null ? $decimal((float) $outing->distance_nm).' milles' : null, 'pin'],
        ['Vitesse', $speed !== null ? $decimal($speed).' nœuds' : null, 'wind'],
    ] : [];
    $endTime = $outing?->end_time ? substr((string) $outing->end_time, 0, 5) : null;
    $redirectTo ??= $outing ? route('outings.show', $outing, false).'#navigation' : null;
@endphp
<section class="card p-5 scroll-mt-24" id="navigation">
    <x-section-title :title="(isset($number) ? $number.'. ' : '').'Impressions de navigation'" />

    @if ($figures)
        <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-4">
            @foreach ($figures as [$label, $value, $icon])
                <div class="rounded-xl bg-slate-50 p-3 min-w-0">
                    <p class="text-[11px] font-bold uppercase muted flex items-center gap-1 truncate"><x-icon :name="$icon" class="w-3.5 h-3.5 shrink-0" />{{ $label }}</p>
                    <p @class(['mt-1 font-extrabold truncate', 'text-slate-300' => $value === null])>{{ $value ?? '—' }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $outing ? route('outings.navigation.update', $outing->uuid) : '' }}" data-navigation-form
          data-offline-form="Impressions de navigation{{ $outing ? ' : '.$outing->title : '' }}" data-offline-redirect="{{ $redirectTo }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="Heure de fin" name="end_time">
                <x-input name="end_time" type="time" :value="$endTime" />
            </x-field>
            <x-field label="Distance parcourue (milles)" name="distance_nm">
                <x-input name="distance_nm" type="number" min="0" max="9999.9" step="0.1" inputmode="decimal" :value="$outing?->distance_nm !== null ? (float) $outing->distance_nm : null" placeholder="8,5" />
            </x-field>
            <x-field label="Impressions" name="impressions" class="sm:col-span-2">
                <textarea id="impressions" name="impressions" rows="4" maxlength="5000" @class(['input', 'input-error' => $errors->has('impressions')]) placeholder="Forme de l’équipage, manœuvres, sensations, points à retravailler…">{{ old('impressions', $outing?->impressions) }}</textarea>
            </x-field>
        </div>
        <button class="btn-primary btn-sm mt-4"><x-icon name="check" class="w-4 h-4" />Enregistrer</button>
    </form>
</section>
