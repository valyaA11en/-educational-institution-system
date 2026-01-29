<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy extends BasePolicy
{
    public function view(User $user, Exam $exam): bool
    {
        // Participants: group members, commission members, or admins
        if ($user->hasPermission('exams.view') || $user->hasPermission('admin')) {
            return true;
        }

        // Check if user is in group
        if ($exam->group_id) {
            $isInGroup = $exam->group->members()->where('user_id', $user->id)->exists();
            if ($isInGroup) {
                return true;
            }
        }

        // Check if user is in commission
        $isInCommission = $exam->commissions()->where('user_id', $user->id)->exists();
        if ($isInCommission) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('exams.create') ||
               $user->hasPermission('curriculum.manage') ||
               $user->hasPermission('admin');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exams.update') ||
               $user->hasPermission('curriculum.manage') ||
               $user->hasPermission('admin');
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exams.delete') ||
               $user->hasPermission('admin');
    }

    public function updateResult(User $user, Exam $exam): bool
    {
        // Commission members, teachers, or admins
        if ($user->hasPermission('admin')) {
            return true;
        }

        $isInCommission = $exam->commissions()->where('user_id', $user->id)->exists();
        if ($isInCommission) {
            return true;
        }

        // Check if user is teacher for this subject
        if ($exam->subject_id) {
            // TODO: Check teacher_subject_group table
            return false;
        }

        return false;
    }
}






