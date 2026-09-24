<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Association;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creation / update of an association from the super admin panel, with an optional first admin on creation.
 */
class AssociationRequest extends FormRequest
{
    /**
     * Access is enforced by the `can:super-admin` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('slug')) ?: null,
            'primary_color' => $this->filled('primary_color') ? strtoupper((string) $this->input('primary_color')) : '#0B2545',
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('associations', 'slug')->ignore($this->route('association'))],
            'city' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
        ];

        if ($this->isMethod('POST')) {
            $rules += [
                'admin_name' => ['nullable', 'required_with:admin_email,admin_password', 'string', 'max:255'],
                'admin_email' => ['nullable', 'required_with:admin_name,admin_password', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
                'admin_password' => ['nullable', 'required_with:admin_name,admin_email', 'string', 'min:8'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'identifiant',
            'admin_name' => 'nom de l’administrateur',
            'admin_email' => 'e-mail de l’administrateur',
            'admin_password' => 'mot de passe de l’administrateur',
        ];
    }

    /**
     * Association attributes, with a unique slug derived from the name when none was given.
     *
     * @return array{name: string, slug: string, city: ?string, primary_color: string}
     */
    public function associationAttributes(): array
    {
        $data = $this->safe()->only(['name', 'slug', 'city', 'primary_color']);
        $data['slug'] ??= $this->uniqueSlug(Str::slug($data['name']) ?: 'association');

        return $data;
    }

    /**
     * First admin account typed along with a new association, if any.
     *
     * @return array{name: string, email: string, password: string}|null
     */
    public function firstAdminAttributes(): ?array
    {
        if (! $this->filled('admin_email')) {
            return null;
        }

        return [
            'name' => $this->validated('admin_name'),
            'email' => $this->validated('admin_email'),
            'password' => $this->validated('admin_password'),
        ];
    }

    private function uniqueSlug(string $base): string
    {
        $ignoredId = $this->route('association')?->getKey();
        $slug = $base;

        for ($suffix = 2; Association::query()->where('slug', $slug)->when($ignoredId, fn ($query) => $query->whereKeyNot($ignoredId))->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
