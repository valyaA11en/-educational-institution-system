<?php

namespace App\Http\Requests\Admin\Directory;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('directory.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'order' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'Время окончания должно быть позже времени начала.',
        ];
    }
}

