<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('rules.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'string', Rule::in(['global', 'org', 'term'])],
            'conditions_json' => ['required', 'array'],
            'conditions_json.event_type' => ['required', 'string'],
            'actions_json' => ['required', 'array', 'min:1'],
            'actions_json.*.type' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'conditions_json.event_type.required' => 'The conditions_json must contain event_type.',
            'actions_json.min' => 'The actions_json must contain at least one action.',
        ];
    }
}









