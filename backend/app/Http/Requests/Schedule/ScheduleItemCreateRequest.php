<?php

namespace App\Http\Requests\Schedule;

use App\Support\DTO\Schedule\ScheduleItemCreateDTO;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleItemCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'subgroup_id' => ['nullable', 'integer', 'exists:subgroups,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_user_id' => ['required', 'integer', 'exists:users,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
            'force' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:force,true', 'nullable', 'string', 'max:500'],
        ];
    }

    public function toDTO(): ScheduleItemCreateDTO
    {
        $data = $this->validated();

        return new ScheduleItemCreateDTO(
            date: CarbonImmutable::parse($data['date']),
            timeSlotId: (int) $data['time_slot_id'],
            groupId: (int) $data['group_id'],
            subgroupId: isset($data['subgroup_id']) ? (int) $data['subgroup_id'] : null,
            subjectId: (int) $data['subject_id'],
            teacherUserId: (int) $data['teacher_user_id'],
            roomId: (int) $data['room_id'],
            force: (bool) ($data['force'] ?? false),
            overrideReason: $data['override_reason'] ?? null,
        );
    }
}


