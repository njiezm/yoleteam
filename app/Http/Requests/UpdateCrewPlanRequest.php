<?php

namespace App\Http\Requests;

use App\Enums\BoatSide;
use App\Enums\BwaPlacement;
use App\Models\BoatPosition;
use App\Models\CrewPlan;
use App\Services\BoatLayoutGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCrewPlanRequest extends FormRequest
{
    /**
     * Route bindings already restrict the plan to the user's association.
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
        return self::rulesFor($this->route('crewPlan'), $this->user()->association_id);
    }

    /**
     * Rules for a full crew plan state (also used when replaying offline operations).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(CrewPlan $plan, int $associationId): array
    {
        return [
            'boat_configuration_id' => ['required', 'integer', Rule::exists('boat_configurations', 'id')->where('boat_id', $plan->boat_id)],
            'wind_direction' => ['nullable', 'integer', 'between:0,359'],
            'wind_strength' => ['nullable', 'integer', 'between:0,60'],
            'bwa_side' => ['nullable', Rule::in([BoatSide::Babord->value, BoatSide::Tribord->value])],
            'fond_count' => ['nullable', 'integer', 'between:0,'.BoatLayoutGenerator::MAX_FONDS],
            'notes' => ['nullable', 'string', 'max:2000'],
            'assignments' => ['present', 'array'],
            'assignments.*.position_id' => ['required', 'integer', 'distinct'],
            'assignments.*.member_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('members', 'id')->where('association_id', $associationId)->whereNull('deleted_at'),
            ],
            'assignments.*.bwa_placement' => ['nullable', Rule::enum(BwaPlacement::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'assignments.*.member_id.distinct' => 'Un membre ne peut occuper qu’un seul poste sur la même yole.',
        ];
    }

    /**
     * Every position must belong to the selected configuration.
     */
    public static function checkPositions(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $data = $validator->getData();
        $positionIds = collect($data['assignments'] ?? [])->pluck('position_id')->map(fn ($id) => (int) $id);
        $codes = BoatPosition::query()
            ->where('boat_configuration_id', (int) $data['boat_configuration_id'])
            ->whereIn('id', $positionIds)
            ->pluck('code');

        if ($codes->count() !== $positionIds->count()) {
            $validator->errors()->add('assignments', 'Certains postes n’appartiennent pas à cette configuration.');

            return;
        }

        $fondCount = $data['fond_count'] ?? BoatLayoutGenerator::MAX_FONDS;
        if ($codes->contains(fn (string $code) => (BoatLayoutGenerator::fondIndex($code) ?? 0) > $fondCount)) {
            $validator->errors()->add('assignments', 'Un équipier est placé sur un fond qui n’est pas utilisé.');
        }
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => self::checkPositions($validator)];
    }

    public function messages(): array
    {
        return self::messagesFor();
    }
}
