{{-- Race fields. Expects $race (may be a new instance). --}}
<div class="grid sm:grid-cols-2 gap-4">
    <x-field label="Nom *" name="name" class="sm:col-span-2">
        <x-input name="name" :value="$race->name" required maxlength="255" placeholder="Tour des Yoles Rondes 2026" />
    </x-field>
    <x-field label="Type *" name="type">
        <x-select name="type" :value="$race->type" :options="\App\Enums\RaceType::options()" required />
    </x-field>
    <x-field label="Saison *" name="season">
        <x-input name="season" type="number" min="2000" max="2100" :value="$race->season" required />
    </x-field>
    <x-field label="Date de début *" name="start_date">
        <x-input name="start_date" type="date" :value="$race->start_date?->toDateString()" required />
    </x-field>
    <x-field label="Date de fin" name="end_date" hint="Laisser vide pour une course d’un jour.">
        <x-input name="end_date" type="date" :value="$race->end_date?->toDateString()" />
    </x-field>
    <x-field label="Lieu" name="location" class="sm:col-span-2">
        <x-input name="location" :value="$race->location" maxlength="255" placeholder="Tour de la Martinique" />
    </x-field>
    <x-field label="Notes" name="notes" class="sm:col-span-2">
        <textarea id="notes" name="notes" rows="3" @class(['input h-auto py-2.5', 'input-error' => $errors->has('notes')])>{{ old('notes', $race->notes) }}</textarea>
    </x-field>
</div>
