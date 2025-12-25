<?php

namespace App\Http\Requests\Assignments;

use App\Support\DTO\Assignments\AssignmentCreateDTO;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AssignmentCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'teacher_user_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
            'max_file_size' => ['nullable', 'integer', 'min:1'],
            'allowed_types' => ['nullable', 'array'],
            'allowed_types.*' => ['string'],
            'visibility_scope' => ['required', 'string'],
        ];
    }

    public function toDTO(): AssignmentCreateDTO
    {
        $data = $this->validated();

        return new AssignmentCreateDTO(
            subjectId: (int) $data['subject_id'],
            teacherUserId: (int) $data['teacher_user_id'],
            title: $data['title'],
            description: $data['description'] ?? null,
            dueAt: isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
            maxAttempts: (int) ($data['max_attempts'] ?? 1),
            maxFileSize: isset($data['max_file_size']) ? (int) $data['max_file_size'] : null,
            allowedTypes: $data['allowed_types'] ?? null,
            visibilityScope: $data['visibility_scope'],
        );
    }
}


