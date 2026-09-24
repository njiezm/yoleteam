<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    /**
     * Route bindings already restrict the outing to the user's association.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `statuses` maps member id => status value ('' clears the record).
     * `all_present` marks every active member not yet recorded as present.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['array', 'required_without:all_present'],
            'statuses.*' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'all_present' => ['boolean'],
        ];
    }

    /**
     * Keys of `statuses` must be members of the association.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $memberIds = array_map('intval', array_keys($this->input('statuses', [])));

                if ($memberIds === []) {
                    return;
                }

                $known = Member::query()->forAssociation($this->user()->association_id)->whereIn('id', $memberIds)->count();

                if ($known !== count($memberIds)) {
                    $validator->errors()->add('statuses', 'Membre inconnu.');
                }
            },
        ];
    }
}
