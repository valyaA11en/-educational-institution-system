<?php

namespace App\Support\Security;

/**
 * Helper class for invalidating AccessScopeService cache
 * Use this when modifying pivot tables (group_members, teacher_subject_group, user_roles)
 */
class AccessScopeCacheHelper
{
    /**
     * Invalidate cache for user when group_members changes
     */
    public static function invalidateForGroupMember(int $userId): void
    {
        AccessScopeService::invalidateForUser($userId);
    }

    /**
     * Invalidate cache for teacher when teacher_subject_group changes
     */
    public static function invalidateForTeacherAssignment(int $teacherUserId): void
    {
        AccessScopeService::invalidateForUser($teacherUserId);
    }

    /**
     * Invalidate cache for user when roles change
     */
    public static function invalidateForUserRole(int $userId): void
    {
        AccessScopeService::invalidateForUser($userId);
    }

    /**
     * Invalidate cache for multiple users
     */
    public static function invalidateForUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            AccessScopeService::invalidateForUser($userId);
        }
    }
}

