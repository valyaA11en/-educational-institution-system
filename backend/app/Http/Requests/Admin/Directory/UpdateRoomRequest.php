<?php

namespace App\Http\Requests\Admin\Directory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('directory.manage');
    }

    public function rules(): array
    {
        $roomId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:rooms,code,' . $roomId],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'attributes' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'capacity.min' => 'Вместимость должна быть больше 0.',
        ];
    }
}


