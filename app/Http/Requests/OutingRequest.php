<?php

namespace App\Http\Requests;

use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Enums\SeaState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutingRequest extends FormRequest
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
        $associationId = $this->user()->association_id;
        $creating = $this->route('outing') === null;

        return [
            // Outings created offline keep the uuid generated on the device (appel and plans point to it).
            'uuid' => [$creating ? 'nullable' : 'exclude', 'uuid', Rule::unique('outings', 'uuid')],
            'type' => ['required', Rule::enum(OutingType::class)],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'wind_direction' => ['nullable', 'integer', 'between:0,359'],
            'wind_strength' => ['nullable', 'integer', 'between:0,80'],
            'wind_gusts' => ['nullable', 'integer', 'between:0,99'],
            'sea_state' => ['nullable', Rule::enum(SeaState::class)],
            'swell_m' => ['nullable', 'numeric', 'between:0,15'],
            'weather' => ['nullable', 'string', 'max:255'],
            'status' => [$creating ? 'exclude' : 'required', Rule::enum(OutingStatus::class)],
            'race_stage_id' => [
                'nullable', 'integer',
                Rule::exists('race_stages', 'id')->whereIn('race_id', fn ($query) => $query->select('id')->from('races')->where('association_id', $associationId)),
            ],
            'boats' => [$creating ? 'nullable' : 'exclude', 'array'],
            'boats.*' => [
                'integer',
                Rule::exists('boats', 'id')->where('association_id', $associationId)->where('is_active', true),
            ],
            'configurations' => [$creating ? 'nullable' : 'exclude', 'array'],
            'configurations.*' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'boats.*' => 'yole',
            'race_stage_id' => 'étape de régate',
            'wind_direction' => 'direction du vent',
            'wind_strength' => 'force du vent',
            'wind_gusts' => 'rafales',
            'sea_state' => 'état de la mer',
            'swell_m' => 'houle',
            'weather' => 'météo',
        ];
    }
}
