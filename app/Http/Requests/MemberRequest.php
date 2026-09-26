<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\MemberLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    /**
     * Also enforced by the `can:manage` route middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'weight_kg' => ['nullable', 'numeric', 'between:30,150'],
            'height_cm' => ['nullable', 'integer', 'between:100,220'],
            'level' => ['required', Rule::enum(MemberLevel::class)],
            'yole_since_year' => ['nullable', 'integer', 'min:1950', 'max:'.now()->year],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'distinct', Rule::exists('crew_roles', 'id')],
            'preferred' => ['nullable', 'array'],
            'preferred.*' => ['integer', Rule::exists('crew_roles', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'roles' => 'postes maîtrisés',
            'roles.*' => 'poste',
            'preferred' => 'postes préférés',
            'preferred.*' => 'poste préféré',
            'is_active' => 'membre actif',
            'yole_since_year' => 'année de début de la yole',
        ];
    }

    /**
     * Member columns (everything except the crew roles).
     *
     * @return array<string, mixed>
     */
    public function memberAttributes(): array
    {
        return $this->safe()->except(['roles', 'preferred']);
    }

    /**
     * Pivot payload for crewRoles()->sync(): a star only counts on a checked role.
     *
     * @return array<int, array{is_preferred: bool}>
     */
    public function crewRoles(): array
    {
        $preferred = array_map('intval', $this->validated('preferred') ?? []);

        return collect($this->validated('roles') ?? [])
            ->mapWithKeys(fn ($roleId) => [(int) $roleId => ['is_preferred' => in_array((int) $roleId, $preferred, true)]])
            ->all();
    }
}
