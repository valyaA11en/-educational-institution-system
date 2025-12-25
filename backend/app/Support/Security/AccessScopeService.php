<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccessScopeService
{
    private const CACHE_TTL = 60; // seconds
    private const CACHE_PREFIX = 'access_scope:';

    public function __construct(
        private User $user
    ) {}

    public static function forUser(User $user): self
    {
        return new self($user);
    }

    /**
     * Get all group IDs accessible to the user
     */
    public function accessibleGroupIds(): array
    {
        return $this->remember('groups', function () {
            $groupIds = [];

            // Admin has access to all groups
            if ($this->isAdmin()) {
                return DB::table('groups')->pluck('id')->toArray();
            }

            // Groups where user is a member
            $memberGroups = DB::table('group_members')
                ->where('user_id', $this->user->id)
                ->pluck('group_id')
                ->toArray();
            $groupIds = array_merge($groupIds, $memberGroups);

            // Groups where user is a teacher (via teacher_subject_group)
            $teacherGroups = DB::table('teacher_subject_group')
                ->where('teacher_user_id', $this->user->id)
                ->distinct()
                ->pluck('group_id')
                ->toArray();
            $groupIds = array_merge($groupIds, $teacherGroups);

            // Groups of children (for parents)
            if ($this->isParent()) {
                $childrenIds = $this->childrenStudentIds();
                if (!empty($childrenIds)) {
                    $childrenGroups = DB::table('group_members')
                        ->whereIn('user_id', $childrenIds)
                        ->where('role_in_group', 'student')
                        ->distinct()
                        ->pluck('group_id')
                        ->toArray();
                    $groupIds = array_merge($groupIds, $childrenGroups);
                }
            }

            return array_unique($groupIds);
        });
    }

    /**
     * Get all subject IDs accessible to the user
     */
    public function accessibleSubjectIds(): array
    {
        return $this->remember('subjects', function () {
            $subjectIds = [];

            // Admin has access to all subjects
            if ($this->isAdmin()) {
                return DB::table('subjects')->pluck('id')->toArray();
            }

            // Subjects where user is a teacher
            $teacherSubjects = DB::table('teacher_subject_group')
                ->where('teacher_user_id', $this->user->id)
                ->distinct()
                ->pluck('subject_id')
                ->toArray();
            $subjectIds = array_merge($subjectIds, $teacherSubjects);

            // Subjects of groups where user is a member (student)
            if ($this->isStudent()) {
                $groupIds = $this->accessibleGroupIds();
                if (!empty($groupIds)) {
                    $groupSubjects = DB::table('teacher_subject_group')
                        ->whereIn('group_id', $groupIds)
                        ->distinct()
                        ->pluck('subject_id')
                        ->toArray();
                    $subjectIds = array_merge($subjectIds, $groupSubjects);
                }
            }

            return array_unique($subjectIds);
        });
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->remember('is_admin', function () {
            return DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $this->user->id)
                ->where('roles.name', 'admin')
                ->exists();
        });
    }

    /**
     * Check if user is teacher
     */
    public function isTeacher(): bool
    {
        return $this->remember('is_teacher', function () {
            return DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $this->user->id)
                ->where('roles.name', 'преподаватель')
                ->exists()
                || DB::table('teacher_subject_group')
                    ->where('teacher_user_id', $this->user->id)
                    ->exists();
        });
    }

    /**
     * Check if user is student
     */
    public function isStudent(): bool
    {
        return $this->remember('is_student', function () {
            return DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $this->user->id)
                ->where('roles.name', 'студент')
                ->exists()
                || DB::table('group_members')
                    ->where('user_id', $this->user->id)
                    ->where('role_in_group', 'student')
                    ->exists();
        });
    }

    /**
     * Check if user is parent
     */
    public function isParent(): bool
    {
        return $this->remember('is_parent', function () {
            return DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $this->user->id)
                ->where('roles.name', 'родитель')
                ->exists()
                || DB::table('user_links_parent_child')
                    ->where('parent_user_id', $this->user->id)
                    ->where('status', 'approved')
                    ->exists();
        });
    }

    /**
     * Get student IDs of children (for parents)
     */
    public function childrenStudentIds(): array
    {
        return $this->remember('children', function () {
            return DB::table('user_links_parent_child')
                ->where('parent_user_id', $this->user->id)
                ->where('status', 'approved')
                ->pluck('student_user_id')
                ->toArray();
        });
    }

    /**
     * Get teacher assignments (teacher_subject_group) with groups
     */
    public function teacherAssignments(): array
    {
        return $this->remember('teacher_assignments', function () {
            $assignments = DB::table('teacher_subject_group')
                ->where('teacher_user_id', $this->user->id)
                ->leftJoin('subjects', 'teacher_subject_group.subject_id', '=', 'subjects.id')
                ->leftJoin('groups', 'teacher_subject_group.group_id', '=', 'groups.id')
                ->leftJoin('subgroups', 'teacher_subject_group.subgroup_id', '=', 'subgroups.id')
                ->select(
                    'teacher_subject_group.id',
                    'teacher_subject_group.subject_id',
                    'teacher_subject_group.group_id',
                    'teacher_subject_group.subgroup_id',
                    'subjects.name as subject_name',
                    'groups.name as group_name',
                    'groups.id as group_id',
                    'subgroups.name as subgroup_name'
                )
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'subject_id' => $assignment->subject_id,
                        'subject_name' => $assignment->subject_name,
                        'group_id' => $assignment->group_id,
                        'group_name' => $assignment->group_name,
                        'subgroup_id' => $assignment->subgroup_id,
                        'subgroup_name' => $assignment->subgroup_name,
                    ];
                })
                ->toArray();

            return $assignments;
        });
    }

    /**
     * Check if user is member of a group
     */
    public function isMemberOfGroup(int $groupId): bool
    {
        return in_array($groupId, $this->accessibleGroupIds());
    }

    /**
     * Check if user can access a student
     */
    public function canAccessStudent(int $studentUserId): bool
    {
        return $this->remember("can_access_student:{$studentUserId}", function () use ($studentUserId) {
            // Admin can access all students
            if ($this->isAdmin()) {
                return true;
            }

            // Student can access themselves
            if ($this->user->id === $studentUserId) {
                return true;
            }

            // Parent can access their children
            if ($this->isParent()) {
                $childrenIds = $this->childrenStudentIds();
                if (in_array($studentUserId, $childrenIds)) {
                    return true;
                }
            }

            // Curator can access students in their groups
            $studentGroups = DB::table('group_members')
                ->where('user_id', $studentUserId)
                ->where('role_in_group', 'student')
                ->pluck('group_id')
                ->toArray();

            $userGroups = DB::table('group_members')
                ->where('user_id', $this->user->id)
                ->where('role_in_group', 'curator')
                ->pluck('group_id')
                ->toArray();

            if (!empty(array_intersect($studentGroups, $userGroups))) {
                return true;
            }

            // Teacher can access students in groups where they teach
            $teacherGroups = DB::table('teacher_subject_group')
                ->where('teacher_user_id', $this->user->id)
                ->pluck('group_id')
                ->toArray();

            if (!empty(array_intersect($studentGroups, $teacherGroups))) {
                return true;
            }

            return false;
        });
    }

    /**
     * Invalidate cache for user
     */
    public function invalidateCache(): void
    {
        $keys = [
            'groups',
            'subjects',
            'is_admin',
            'is_teacher',
            'is_student',
            'is_parent',
            'children',
            'teacher_assignments',
        ];

        foreach ($keys as $key) {
            Cache::forget($this->getCacheKey($key));
        }

        // Invalidate can_access_student keys (pattern-based)
        $pattern = $this->getCacheKey('can_access_student:*');
        // Note: Laravel Cache doesn't support pattern deletion by default
        // This would require Redis SCAN or similar, so we'll invalidate on-demand
    }

    /**
     * Invalidate cache for specific student access check
     */
    public function invalidateStudentAccessCache(int $studentUserId): void
    {
        Cache::forget($this->getCacheKey("can_access_student:{$studentUserId}"));
    }

    /**
     * Remember value in cache
     */
    private function remember(string $key, callable $callback): mixed
    {
        return Cache::remember(
            $this->getCacheKey($key),
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Get cache key for user
     */
    private function getCacheKey(string $key): string
    {
        return self::CACHE_PREFIX . $this->user->id . ':' . $key;
    }

    /**
     * Static method to invalidate cache for a user
     */
    public static function invalidateForUser(int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            self::forUser($user)->invalidateCache();
        }
    }
}

