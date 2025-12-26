<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRuleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'string', Rule::in(['global', 'org', 'term'])],
            'conditions_json' => ['sometimes', 'array'],
            'conditions_json.event_type' => ['required_with:conditions_json', 'string'],
            'actions_json' => ['sometimes', 'array', 'min:1'],
            'actions_json.*.type' => ['required_with:actions_json', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If conditions_json is provided, ensure it has event_type
            if ($this->has('conditions_json')) {
                $conditions = $this->input('conditions_json', []);
                if (!isset($conditions['event_type'])) {
                    $validator->errors()->add('conditions_json.event_type', 'The conditions_json must contain event_type.');
                }
            }

            // If actions_json is provided, ensure it has at least one action
            if ($this->has('actions_json')) {
                $actions = $this->input('actions_json', []);
                if (empty($actions)) {
                    $validator->errors()->add('actions_json', 'The actions_json must contain at least one action.');
                }
            }

            // If updating both conditions and actions, validate together
            if ($this->has('conditions_json') || $this->has('actions_json')) {
                $ruleId = $this->route('id');
                if ($ruleId) {
                    $rule = \App\Models\Rule::find($ruleId);
                    if ($rule) {
                        $conditions = $this->input('conditions_json', $rule->conditions_json ?? []);
                        $actions = $this->input('actions_json', $rule->actions_json ?? []);

                        // Ensure conditions has event_type
                        if (!isset($conditions['event_type'])) {
                            $validator->errors()->add('conditions_json.event_type', 'The conditions_json must contain event_type.');
                        }

                        // Ensure actions has at least one action
                        if (empty($actions)) {
                            $validator->errors()->add('actions_json', 'The actions_json must contain at least one action.');
                        }
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'conditions_json.event_type.required_with' => 'The conditions_json must contain event_type.',
            'actions_json.min' => 'The actions_json must contain at least one action.',
        ];
    }
}


