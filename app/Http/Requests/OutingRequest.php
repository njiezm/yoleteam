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
            // Generated on the device: outings created offline keep it (appel and plans point to it), and a form
            // sent twice is recognised (OutingController::store opens the outing instead of creating it again).
            'uuid' => [$creating ? 'nullable' : 'exclude', 'uuid'],
            'type' => ['required', Rule::enum(OutingType::class)],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'impressions' => ['nullable', 'string', 'max:5000'],
            'distance_nm' => ['nullable', 'numeric', 'between:0,9999.9'],
            'wind_direction' => ['nullable', 'integer', 'between:0,359'],
            'wind_strength' => ['nullable', 'integer', 'between:0,80'],
            'wind_gusts' => ['nullable', 'integer', 'between:0,99'],
            'sea_state' => ['nullable', Rule::enum(SeaState::class)],
            'swell_m' => ['nullable', 'numeric', 'between:0,15'],
            'weather' => ['nullable', 'string', 'max:255'],
            'status' => [$creating ? 'exclude' : 'required', Rule::enum(OutingStatus::class)],
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
            'notes' => 'consignes',
            'impressions' => 'impressions de navigation',
            'distance_nm' => 'distance parcourue',
            'wind_direction' => 'direction du vent',
            'wind_strength' => 'force du vent',
            'wind_gusts' => 'rafales',
            'sea_state' => 'état de la mer',
            'swell_m' => 'houle',
            'weather' => 'météo',
        ];
    }
}
