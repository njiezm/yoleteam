<?php

namespace App\Http\Requests;

use App\Enums\RaceResultStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Results of one stage: `results[boat_id][rank|time|points|status|notes]`.
 */
class UpdateRaceResultsRequest extends FormRequest
{
    /**
     * Access is enforced by the `can:manage` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Each stage has its own results form on the race page: errors go to a per-stage bag.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'results-'.$this->route('stage')->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'results' => ['required', 'array'],
            'results.*' => ['array'],
            'results.*.rank' => ['nullable', 'integer', 'between:1,999'],
            'results.*.time' => ['nullable', 'string', 'regex:/^\d{1,3}:[0-5]\d:[0-5]\d$/'],
            'results.*.points' => ['nullable', 'numeric', 'between:0,9999'],
            'results.*.status' => ['nullable', Rule::enum(RaceResultStatus::class)],
            'results.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'results.*.time.regex' => 'Le temps doit être au format H:MM:SS (ex. 1:04:30).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'results.*.rank' => 'rang',
            'results.*.points' => 'points',
            'results.*.status' => 'statut',
            'results.*.notes' => 'notes',
        ];
    }
}
