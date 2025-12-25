<?php

namespace App\Http\Requests\Documents;

use App\Support\DTO\Documents\DocumentCreateDTO;
use Illuminate\Foundation\Http\FormRequest;

class DocumentCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'template_id' => ['required', 'integer'],
            'data_json' => ['required', 'array'],
        ];
    }

    public function toDTO(): DocumentCreateDTO
    {
        $data = $this->validated();

        return new DocumentCreateDTO(
            type: $data['type'],
            templateId: (int) $data['template_id'],
            dataJson: $data['data_json'],
        );
    }
}


