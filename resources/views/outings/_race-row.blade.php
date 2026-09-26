{{-- One race (manche) of the results form. $index: row key ("__INDEX__" in the template), $row: place / result / points / remove. --}}
@php
    $name = fn (string $field) => "races[{$index}][{$field}]";
    $outcome = \App\Enums\RaceOutcome::tryFrom((string) ($row['result'] ?? '')) ?? \App\Enums\RaceOutcome::Classe;
    $place = filled($row['place'] ?? null) ? (int) $row['place'] : null;
    $typedPoints = filled($row['points'] ?? null) ? (int) $row['points'] : null;
    $points = $outcome->points($place, $typedPoints);
    $rowErrors = collect(['place', 'result', 'points'])->map(fn ($field) => $errors->first("races.{$index}.{$field}"))->filter();
@endphp
<tr data-race-row>
    <td class="td font-extrabold" data-race-number>{{ $number }}</td>
    <td class="td">
        <select name="{{ $name('place') }}" data-place @class(['input h-10 w-20', 'input-error' => $errors->has("races.{$index}.place")]) aria-label="Place">
            <option value="">—</option>
            @foreach ($places as $value => $label)
                <option value="{{ $value }}" @selected($place === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td class="td">
        <div class="seg" role="radiogroup" aria-label="Résultat">
            @foreach (\App\Enums\RaceOutcome::cases() as $case)
                <label title="{{ $case->label() }}">
                    <input type="radio" name="{{ $name('result') }}" value="{{ $case->value }}" data-outcome="{{ $case->value }}" class="sr-only" @checked($outcome === $case)>{{ $case->shortLabel() }}
                </label>
            @endforeach
        </div>
    </td>
    <td class="td">
        <span data-computed-points class="font-bold tabular-nums">{{ $points ?? '—' }}</span>
        <span data-typed-points>
            <input name="{{ $name('points') }}" type="number" min="0" max="999" inputmode="numeric" value="{{ $typedPoints }}" @class(['input h-10 w-20', 'input-error' => $errors->has("races.{$index}.points")]) aria-label="Points de la disqualification">
        </span>
    </td>
    <td class="td text-right">
        <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 cursor-pointer" title="Retirer cette course">
            <input type="checkbox" name="{{ $name('remove') }}" value="1" data-race-remove class="w-4 h-4 accent-red-600" @checked(! empty($row['remove']))><x-icon name="trash" class="w-4 h-4" /><span class="sr-only">Retirer</span>
        </label>
    </td>
</tr>
@if ($rowErrors->isNotEmpty())
    <tr><td colspan="5" class="px-4 pb-3 text-xs text-red-600 font-semibold">{{ $rowErrors->join(' ') }}</td></tr>
@endif
