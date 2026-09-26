{{--
    Boat details fields. Expects $boat (may be a new instance).
    Old input is only reused when it comes from this form ($form marker), since the boat page holds several forms.
--}}
@php
    $form = $form ?? 'boat';
    $old = fn (string $key, mixed $default = null) => old('_form', 'boat') === $form ? old($key, $default) : $default;
    $hullColors = collect(\App\Models\Boat::HULL_COLORS)->mapWithKeys(fn ($hex, $color) => [$color => ucfirst($color)]);
@endphp
<input type="hidden" name="_form" value="{{ $form }}">
<div class="grid sm:grid-cols-2 gap-4">
    <x-field label="Nom *" name="name" class="sm:col-span-2">
        <input id="name" name="name" value="{{ $old('name', $boat->name) }}" required maxlength="255" placeholder="Ti-Bwa" @class(['input', 'input-error' => $errors->has('name')])>
    </x-field>
    <x-field label="Couleur de coque" name="hull_color">
        @php
            $currentColor = (string) $old('hull_color', $boat->hull_color);
        @endphp
        <select id="hull_color" name="hull_color" @class(['input', 'input-error' => $errors->has('hull_color')])>
            <option value="">—</option>
            @foreach ($hullColors as $value => $label)
                <option value="{{ $value }}" @selected($currentColor === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Longueur (m)" name="length_m">
        <input id="length_m" name="length_m" type="number" step="0.01" min="1" max="99" value="{{ $old('length_m', $boat->length_m) }}" placeholder="9,50" @class(['input', 'input-error' => $errors->has('length_m')])>
    </x-field>
    <x-field label="Notes" name="notes" class="sm:col-span-2">
        <textarea id="notes" name="notes" rows="3" @class(['input h-auto py-2.5', 'input-error' => $errors->has('notes')])>{{ $old('notes', $boat->notes) }}</textarea>
    </x-field>
    <label class="sm:col-span-2 flex items-center gap-3 p-3 rounded-xl bg-slate-50 text-sm font-semibold">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="w-4 h-4 accent-navy-900" @checked($old('is_active', $boat->is_active ?? true))>
        <span>Opérationnelle <span class="font-normal muted">— décochez si la yole est indisponible ou en réparation</span></span>
    </label>
</div>
