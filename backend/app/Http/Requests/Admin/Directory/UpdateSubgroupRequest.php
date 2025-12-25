<?php

namespace App\Http\Requests\Admin\Directory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubgroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('directory.manage');
    }

    public function rules(): array
    {
        $subgroupId = $this->route('id');

        return [
            'group_id' => ['sometimes', 'required', 'integer', 'exists:groups,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:subgroups,code,' . $subgroupId],
        ];
    }
}

