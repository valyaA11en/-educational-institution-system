<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class SuggestTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'subgroup_id' => ['sometimes', 'nullable', 'integer', 'exists:subgroups,id'],
            'version_id' => ['sometimes', 'integer', 'exists:schedule_versions,id'],
        ];
    }
}

