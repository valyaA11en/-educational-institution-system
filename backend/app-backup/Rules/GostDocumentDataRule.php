<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class GostDocumentDataRule implements Rule
{
    protected string $type;
    protected array $errors = [];

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (!is_array($value)) {
            $this->errors[] = 'data_json must be an array';
            return false;
        }

        // Validate only for order and decision types
        if (!in_array($this->type, ['order', 'decision', 'приказ', 'распоряжение'])) {
            return true;
        }

        $this->errors = [];

        // Required fields
        $requiredFields = [
            'org_name' => 'string',
            'title' => 'string',
            'basis' => 'string',
            'body_items' => 'array',
            'signer_name' => 'string',
            'signer_role' => 'string',
        ];

        foreach ($requiredFields as $field => $fieldType) {
            if (!isset($value[$field])) {
                $this->errors[] = "data_json.{$field} is required";
                continue;
            }

            if ($fieldType === 'string' && empty(trim($value[$field]))) {
                $this->errors[] = "data_json.{$field} cannot be empty";
            } elseif ($fieldType === 'array' && (!is_array($value[$field]) || empty($value[$field]))) {
                $this->errors[] = "data_json.{$field} must be a non-empty array";
            }
        }

        // Validate body_items structure
        if (isset($value['body_items']) && is_array($value['body_items'])) {
            foreach ($value['body_items'] as $index => $item) {
                if (!is_array($item)) {
                    $this->errors[] = "data_json.body_items[{$index}] must be an object";
                    continue;
                }
                if (!isset($item['no']) || !isset($item['text'])) {
                    $this->errors[] = "data_json.body_items[{$index}] must contain 'no' and 'text' fields";
                } elseif (empty(trim($item['text']))) {
                    $this->errors[] = "data_json.body_items[{$index}].text cannot be empty";
                }
            }
        }

        // Optional fields validation
        if (isset($value['appendix']) && !is_string($value['appendix']) && !is_array($value['appendix'])) {
            $this->errors[] = "data_json.appendix must be a string or array";
        }
        if (isset($value['recipients']) && !is_array($value['recipients'])) {
            $this->errors[] = "data_json.recipients must be an array";
        }

        return empty($this->errors);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return implode(', ', $this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}


