<?php

namespace App\Http\Requests\Admin\Directory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('directory.manage');
    }

    public function rules(): array
    {
        $timeSlotId = $this->route('id');
        $timeSlot = \App\Models\TimeSlot::find($timeSlotId);

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'order' => ['sometimes', 'required', 'integer', 'min:1'],
        ];

        // Validate end_time > start_time
        // If both are provided, use provided values; otherwise use existing values
        $startTime = $this->input('start_time', $timeSlot?->start_time);
        $endTime = $this->input('end_time', $timeSlot?->end_time);

        if ($startTime && $endTime) {
            $rules['end_time'][] = function ($attribute, $value, $fail) use ($startTime) {
                if (strtotime($value) <= strtotime($startTime)) {
                    $fail('Время окончания должно быть позже времени начала.');
                }
            };
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'Время окончания должно быть позже времени начала.',
        ];
    }
}

