<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\MemberLevel;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    private function creating(): bool
    {
        return $this->route('member') === null;
    }

    /**
     * A member with the same first and last name is most likely a duplicate: it is refused unless the form
     * says it is another person (homonym).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['first_name', 'last_name']) || $this->boolean('homonym')) {
                return;
            }

            $twin = Member::query()
                ->forAssociation($this->user()->association_id)
                ->whereRaw('lower(first_name) = ?', [mb_strtolower(trim((string) $this->input('first_name')))])
                ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim((string) $this->input('last_name')))])
                ->when(! $this->creating(), fn ($query) => $query->whereKeyNot($this->route('member')->getKey()))
                ->when($this->creating() && filled($this->input('uuid')), fn ($query) => $query->where('uuid', '!=', $this->input('uuid')))
                ->first();

            if ($twin) {
                $validator->errors()->add('first_name', "{$twin->full_name} existe déjà dans les membres. S’il s’agit d’une autre personne, cochez « Homonyme » puis enregistrez.");
                $validator->errors()->add('homonym', 'duplicate');
            }
        }];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => [$this->creating() ? 'nullable' : 'exclude', 'uuid'],
            'homonym' => ['exclude'],
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
            'yole_years' => ['nullable', 'integer', 'min:0', 'max:80'],
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
            'yole_years' => 'nombre d’années de yole',
        ];
    }

    /**
     * Member columns (everything except the crew roles). The number of years typed is stored as a starting
     * year, so that it keeps growing by itself every year.
     *
     * @return array<string, mixed>
     */
    public function memberAttributes(): array
    {
        $years = $this->validated('yole_years');

        return [
            ...$this->safe()->except(['roles', 'preferred', 'yole_years']),
            'yole_since_year' => $years === null ? null : today()->year - (int) $years,
        ];
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
