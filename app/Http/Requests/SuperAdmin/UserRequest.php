<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Account creation / update from the super admin panel (any role, any association).
 */
class UserRequest extends FormRequest
{
    /**
     * Access is enforced by the `can:super-admin` route middleware.
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
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'association_id' => ['required', 'integer', Rule::exists('associations', 'id')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['association_id' => 'association'];
    }

    /**
     * A super admin cannot strip their own role and lock themselves out of the panel.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');

                if ($user instanceof User && $user->is($this->user()) && $this->input('role') !== UserRole::SuperAdmin->value && ! $validator->errors()->has('role')) {
                    $validator->errors()->add('role', 'Vous ne pouvez pas retirer votre propre rôle de super admin.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        $data = $this->safe()->only(['name', 'email', 'phone', 'role', 'association_id', 'password']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
