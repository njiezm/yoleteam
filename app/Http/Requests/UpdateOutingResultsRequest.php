<?php

namespace App\Http\Requests;

use App\Enums\OutingType;
use App\Enums\RaceOutcome;
use App\Models\Outing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Results of a race or TDY outing: `races[i][place|result|points|remove]` (race outings only) and the rankings.
 */
class UpdateOutingResultsRequest extends FormRequest
{
    /**
     * Admins and patrons both manage outings.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $place = ['nullable', 'integer', 'between:1,'.RaceOutcome::MAX_PLACE];

        return [
            'races' => ['nullable', 'array', 'max:30'],
            'races.*' => ['array'],
            'races.*.place' => $place,
            'races.*.result' => ['nullable', Rule::enum(RaceOutcome::class)],
            'races.*.points' => ['nullable', 'integer', 'between:0,999'],
            'races.*.remove' => ['nullable', 'boolean'],
            'day_rank' => $place,
            'stage_rank' => $place,
            'general_rank' => $place,
        ];
    }

    /**
     * Only race and TDY outings have results; a disqualification needs its points.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Outing $outing */
                $outing = $this->route('outing');

                if (! $outing->type->hasResults()) {
                    $validator->errors()->add('races', 'Les résultats ne concernent que les courses et les étapes du TDY.');

                    return;
                }

                foreach ((array) $this->input('races', []) as $index => $row) {
                    $outcome = is_array($row) ? RaceOutcome::tryFrom((string) ($row['result'] ?? '')) : null;

                    if ($outcome?->hasTypedPoints() && empty($row['remove']) && blank($row['points'] ?? null) && ! $validator->errors()->has("races.{$index}.points")) {
                        $validator->errors()->add("races.{$index}.points", 'Indiquez les points de la disqualification.');
                    }
                }
            },
        ];
    }

    /**
     * Races to store, in the submitted order: removed rows and blank rows (classé without place) are dropped.
     *
     * @return list<array{place: int|null, result: RaceOutcome, points: int|null}>
     */
    public function races(): array
    {
        /** @var Outing $outing */
        $outing = $this->route('outing');

        if ($outing->type !== OutingType::Regate) {
            return [];
        }

        return collect($this->validated('races') ?? [])
            ->reject(fn (array $row) => ! empty($row['remove']))
            ->map(function (array $row) {
                $outcome = RaceOutcome::tryFrom((string) ($row['result'] ?? '')) ?? RaceOutcome::Classe;
                $place = isset($row['place']) ? (int) $row['place'] : null;
                $typedPoints = isset($row['points']) ? (int) $row['points'] : null;

                return ['place' => $place, 'result' => $outcome, 'points' => $outcome->points($place, $typedPoints)];
            })
            ->reject(fn (array $race) => $race['result'] === RaceOutcome::Classe && $race['place'] === null)
            ->values()
            ->all();
    }

    /**
     * Rankings kept for the outing type: day + general for a race, stage + general for a TDY stage.
     *
     * @return array{day_rank: int|null, stage_rank: int|null, general_rank: int|null}
     */
    public function rankings(): array
    {
        /** @var Outing $outing */
        $outing = $this->route('outing');
        $rank = fn (string $key) => $this->validated($key) !== null ? (int) $this->validated($key) : null;

        return [
            'day_rank' => $outing->type === OutingType::Regate ? $rank('day_rank') : null,
            'stage_rank' => $outing->type === OutingType::Tdy ? $rank('stage_rank') : null,
            'general_rank' => $rank('general_rank'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'races.*.place' => 'place',
            'races.*.result' => 'résultat',
            'races.*.points' => 'points',
            'day_rank' => 'place de la journée',
            'stage_rank' => 'classement de l’étape',
            'general_rank' => 'classement général',
        ];
    }
}
