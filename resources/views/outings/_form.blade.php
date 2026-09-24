{{-- Shared fields of the outing create / edit forms. --}}
@php
    $types = [
        \App\Enums\OutingType::Entrainement->value => ['Entraînement', 'calendar'],
        \App\Enums\OutingType::Regate->value => ['Course', 'trophy'],
        \App\Enums\OutingType::SortieLibre->value => ['Sortie libre', 'boat'],
    ];
    $currentType = old('type', $outing->type?->value ?? \App\Enums\OutingType::Entrainement->value);
@endphp
<section class="card p-5 lg:p-6">
    <p class="label">Type de sortie</p>
    <div class="grid grid-cols-3 gap-2">
        @foreach ($types as $value => [$label, $icon])
            <label class="p-3 rounded-xl border-2 flex flex-col items-center gap-1.5 text-sm font-bold cursor-pointer border-slate-200 has-checked:border-navy-900 has-checked:bg-navy-50">
                <input type="radio" name="type" value="{{ $value }}" class="sr-only" @checked($currentType === $value)>
                <x-icon :name="$icon" />{{ $label }}
            </label>
        @endforeach
    </div>
    @error('type')<p class="text-xs text-red-600 font-semibold mt-1.5">{{ $message }}</p>@enderror

    <div class="grid sm:grid-cols-2 gap-4 mt-5">
        <x-field label="Titre *" name="title" class="sm:col-span-2">
            <x-input name="title" :value="$outing->title" required placeholder="Entraînement du samedi" />
        </x-field>
        <x-field label="Date *" name="date">
            <x-input name="date" type="date" :value="$outing->date?->toDateString()" required />
        </x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="Début" name="start_time">
                <x-input name="start_time" type="time" :value="$outing->start_time ? substr($outing->start_time, 0, 5) : null" />
            </x-field>
            <x-field label="Fin" name="end_time">
                <x-input name="end_time" type="time" :value="$outing->end_time ? substr($outing->end_time, 0, 5) : null" />
            </x-field>
        </div>
        <x-field label="Lieu" name="location" class="sm:col-span-2">
            <x-input name="location" :value="$outing->location" placeholder="Baie du François" />
        </x-field>
    </div>
</section>

<section class="card p-5 lg:p-6">
    <h3 class="font-bold mb-1 flex items-center gap-2"><x-icon name="wind" class="w-4 h-4" />Conditions de navigation</h3>
    <p class="text-xs muted mb-4">Facultatif — à remplir avant la sortie ou au retour. Le vent est repris par défaut dans les plans d’équipage.</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <x-field label="Vent (d’où il vient)" name="wind_direction">
            <x-select name="wind_direction" :options="\App\Services\CrewPlanPresenter::windOptions()" :value="$outing->wind_direction" placeholder="—" />
        </x-field>
        <x-field label="Force (nœuds)" name="wind_strength">
            <x-input name="wind_strength" type="number" min="0" max="80" inputmode="numeric" :value="$outing->wind_strength" placeholder="15" />
        </x-field>
        <x-field label="Rafales (nœuds)" name="wind_gusts">
            <x-input name="wind_gusts" type="number" min="0" max="99" inputmode="numeric" :value="$outing->wind_gusts" placeholder="22" />
        </x-field>
        <x-field label="État de la mer" name="sea_state">
            <x-select name="sea_state" :options="\App\Enums\SeaState::options()" :value="$outing->sea_state" placeholder="—" />
        </x-field>
        <x-field label="Houle (m)" name="swell_m">
            <x-input name="swell_m" type="number" min="0" max="15" step="0.1" inputmode="decimal" :value="$outing->swell_m !== null ? (float) $outing->swell_m : null" placeholder="1,5" />
        </x-field>
        <x-field label="Météo" name="weather">
            <x-input name="weather" :value="$outing->weather" placeholder="Grains passagers, soleil…" maxlength="255" />
        </x-field>
    </div>
</section>

<section class="card p-5 lg:p-6 grid sm:grid-cols-2 gap-4">
    <x-field label="Rattacher à une étape de régate" name="race_stage_id">
        <x-select name="race_stage_id" :options="$stages" :value="$outing->race_stage_id" placeholder="— Aucune —" />
    </x-field>
    @isset($statusField)
        <x-field label="Statut" name="status">
            <x-select name="status" :options="\App\Enums\OutingStatus::options()" :value="$outing->status" />
        </x-field>
    @endisset
    <x-field label="Consignes" name="notes" class="sm:col-span-2">
        <textarea id="notes" name="notes" rows="3" class="input" placeholder="Objectifs de la séance, matériel…">{{ old('notes', $outing->notes) }}</textarea>
    </x-field>
</section>
