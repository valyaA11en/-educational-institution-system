<?php

namespace App\Http\Requests\Admin\Directory;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('directory.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:rooms,code'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'capacity.min' => 'Вместимость должна быть больше 0.',
        ];
    }
}


