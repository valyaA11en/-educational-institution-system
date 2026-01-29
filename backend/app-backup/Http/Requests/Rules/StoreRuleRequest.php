<?php

namespace App\Http\Requests\Rules;

use App\Services\Rule\RuleEngine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'scope' => ['required', 'in:global,org,term'],
            'conditions_json' => ['required', 'array'],
            'actions_json' => ['required', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $conditions = $this->input('conditions_json', []);
            $actions = $this->input('actions_json', []);

            $ruleEngine = app(RuleEngine::class);
            $errors = $ruleEngine->validateRule($conditions, $actions);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $validator->errors()->add('rule', $error);
                }
            }
        });
    }
}

