<?php

namespace App\Http\Requests\Journal;

use App\Support\DTO\Journal\GradeCreateDTO;
use Illuminate\Foundation\Http\FormRequest;

class GradeCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lesson_id' => ['nullable', 'integer'],
            'assignment_id' => ['nullable', 'integer'],
            'student_id' => ['required', 'integer'],
            'value' => ['required', 'integer'],
            'weight' => ['sometimes', 'integer', 'min:1'],
            'grade_type' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): GradeCreateDTO
    {
        $data = $this->validated();

        return new GradeCreateDTO(
            lessonId: $data['lesson_id'] ?? null,
            assignmentId: $data['assignment_id'] ?? null,
            studentId: (int) $data['student_id'],
            value: (int) $data['value'],
            weight: (int) ($data['weight'] ?? 1),
            gradeType: $data['grade_type'] ?? null,
            comment: $data['comment'] ?? null,
        );
    }
}



