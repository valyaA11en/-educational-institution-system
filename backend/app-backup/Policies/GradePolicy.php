<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class GradePolicy extends BasePolicy
{
    public function __construct(
        private AccessScopeService $accessScope
    ) {}

    public function viewAny(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin() || $scope->isTeacher() || $scope->isStudent() || $scope->isParent();
    }

    public function view(User $user, Grade $grade): bool
    {
        $scope = AccessScopeService::forUser($user);

        // Student can view their own grades
        if ($scope->isStudent() && $user->id === $grade->student_user_id) {
            return true;
        }

        // Parent can view children's grades
        if ($scope->isParent()) {
            $childrenIds = $scope->childrenStudentIds();
            if (in_array($grade->student_user_id, $childrenIds)) {
                return true;
            }
        }

        // Get group_id from lesson or assignment
        $groupId = null;
        if ($grade->lesson_id) {
            $lesson = DB::table('lessons')->find($grade->lesson_id);
            if ($lesson && isset($lesson->schedule_item_id)) {
                $scheduleItem = DB::table('schedule_items')->find($lesson->schedule_item_id);
                if ($scheduleItem) {
                    $groupId = $scheduleItem->group_id;
                }
            }
        } elseif ($grade->assignment_id) {
            $assignment = DB::table('assignments')->find($grade->assignment_id);
            if ($assignment) {
                $target = DB::table('assignment_targets')
                    ->where('assignment_id', $assignment->id)
                    ->whereNotNull('group_id')
                    ->first();
                if ($target) {
                    $groupId = $target->group_id;
                }
            }
        }

        // Teacher/curator/admin can view by group
        if ($groupId && ($scope->isTeacher() || $scope->isAdmin())) {
            $accessibleGroupIds = $scope->accessibleGroupIds();
            if (in_array($groupId, $accessibleGroupIds)) {
                return true;
            }
        }

        // Curator check
        if ($groupId) {
            $curatorIds = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('role_in_group', 'curator')
                ->pluck('user_id')
                ->toArray();
            if (in_array($user->id, $curatorIds)) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user, ?int $lessonId = null, ?int $assignmentId = null): bool
    {
        $scope = AccessScopeService::forUser($user);

        if ($scope->isAdmin()) {
            return true;
        }

        if (!$scope->isTeacher()) {
            return false;
        }

        // Check lesson
        if ($lessonId) {
            $lesson = DB::table('lessons')->find($lessonId);
            if ($lesson && $lesson->schedule_item_id) {
                $scheduleItem = DB::table('schedule_items')->find($lesson->schedule_item_id);
                if ($scheduleItem && $scheduleItem->teacher_user_id === $user->id) {
                    return true;
                }
            }
        }

        // Check assignment
        if ($assignmentId) {
            $assignment = DB::table('assignments')->find($assignmentId);
            if ($assignment && $assignment->teacher_user_id === $user->id) {
                return true;
            }

            // Check teacher_subject_group
            if ($assignment) {
                $target = DB::table('assignment_targets')
                    ->where('assignment_id', $assignment->id)
                    ->whereNotNull('group_id')
                    ->first();
                if ($target) {
                    $hasAssignment = DB::table('teacher_subject_group')
                        ->where('teacher_user_id', $user->id)
                        ->where('subject_id', $assignment->subject_id)
                        ->where('group_id', $target->group_id)
                        ->exists();
                    if ($hasAssignment) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function update(User $user, Grade $grade, ?string $reason = null): bool
    {
        $scope = AccessScopeService::forUser($user);

        if ($scope->isAdmin()) {
            if ($reason) {
                $this->logGradeChange($grade, $user, $reason);
            }
            return true;
        }

        if (!$scope->isTeacher()) {
            return false;
        }

        // Check lesson
        if ($grade->lesson_id) {
            $lesson = DB::table('lessons')->find($grade->lesson_id);
            if ($lesson && $lesson->schedule_item_id) {
                $scheduleItem = DB::table('schedule_items')->find($lesson->schedule_item_id);
                if ($scheduleItem && $scheduleItem->teacher_user_id === $user->id) {
                    if ($reason) {
                        $this->logGradeChange($grade, $user, $reason);
                    }
                    return true;
                }
            }
        }

        // Check assignment
        if ($grade->assignment_id) {
            $assignment = DB::table('assignments')->find($grade->assignment_id);
            if ($assignment && $assignment->teacher_user_id === $user->id) {
                if ($reason) {
                    $this->logGradeChange($grade, $user, $reason);
                }
                return true;
            }

            // Check teacher_subject_group
            if ($assignment) {
                $target = DB::table('assignment_targets')
                    ->where('assignment_id', $assignment->id)
                    ->whereNotNull('group_id')
                    ->first();
                if ($target) {
                    $hasAssignment = DB::table('teacher_subject_group')
                        ->where('teacher_user_id', $user->id)
                        ->where('subject_id', $assignment->subject_id)
                        ->where('group_id', $target->group_id)
                        ->exists();
                    if ($hasAssignment) {
                        if ($reason) {
                            $this->logGradeChange($grade, $user, $reason);
                        }
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }

    private function logGradeChange(Grade $grade, User $user, string $reason): void
    {
        \App\Models\GradeChange::create([
            'grade_id' => $grade->id,
            'changed_by' => $user->id,
            'before_json' => $grade->getOriginal(),
            'after_json' => $grade->toArray(),
            'reason' => $reason,
        ]);
    }
}

