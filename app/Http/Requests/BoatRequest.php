<?php

namespace App\Http\Requests;

use App\Models\Boat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoatRequest extends FormRequest
{
    /**
     * Access is enforced by the `can:manage` route middleware.
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'hull_color' => ['nullable', Rule::in(array_keys(Boat::HULL_COLORS))],
            'length_m' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, hull_color: ?string, length_m: ?string, notes: ?string, is_active: bool}
     */
    public function boatData(): array
    {
        return [
            ...$this->safe()->only(['name', 'hull_color', 'length_m', 'notes']),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
