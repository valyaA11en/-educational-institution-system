<?php

namespace App\Http\Requests\Rules;

use App\Services\Rule\RuleEngine;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Add permission check
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'in:global,org,term'],
            'conditions_json' => ['sometimes', 'array'],
            'actions_json' => ['sometimes', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $conditions = $this->input('conditions_json');
            $actions = $this->input('actions_json');

            // Only validate if conditions or actions are provided
            if ($conditions !== null || $actions !== null) {
                $ruleEngine = app(RuleEngine::class);
                
                // Get existing rule data if updating
                $ruleId = $this->route('id');
                if ($ruleId) {
                    $rule = \App\Models\Rule::find($ruleId);
                    if ($rule) {
                        $conditions = $conditions ?? $rule->conditions_json ?? [];
                        $actions = $actions ?? $rule->actions_json ?? [];
                    } else {
                        $conditions = $conditions ?? [];
                        $actions = $actions ?? [];
                    }
                } else {
                    $conditions = $conditions ?? [];
                    $actions = $actions ?? [];
                }

                $errors = $ruleEngine->validateRule($conditions, $actions);

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $validator->errors()->add('rule', $error);
                    }
                }
            }
        });
    }
}

