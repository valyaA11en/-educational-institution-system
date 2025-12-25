<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class SuggestRoomRequest extends FormRequest
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
            'version_id' => ['sometimes', 'integer', 'exists:schedule_versions,id'],
            'required' => ['sometimes', 'array'],
            'required.type' => ['sometimes', 'string'],
            'required.min_capacity' => ['sometimes', 'integer', 'min:1'],
            'required.attributes' => ['sometimes', 'array'],
        ];
    }
}

