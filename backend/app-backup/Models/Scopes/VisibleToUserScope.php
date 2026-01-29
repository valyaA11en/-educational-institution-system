<?php

namespace App\Models\Scopes;

use App\Support\Security\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class VisibleToUserScope implements Scope
{
    public function __construct(
        private ?int $userId = null
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $userId = $this->userId ?? auth()->id();

        if (!$userId) {
            // If no user, return empty result
            $builder->whereRaw('1 = 0');
            return;
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $scope = AccessScopeService::forUser($user);

        // Admin can see all
        if ($scope->isAdmin()) {
            return; // No filtering for admin
        }

        // Get accessible group IDs
        $accessibleGroupIds = $scope->accessibleGroupIds();
        $childrenStudentIds = $scope->childrenStudentIds();

        // Determine target table name and model ID column based on model
        $targetTable = null;
        $modelIdColumn = null;

        if ($model instanceof \App\Models\Assignment) {
            $targetTable = 'assignment_targets';
            $modelIdColumn = 'assignment_id';
        } elseif ($model instanceof \App\Models\Material) {
            $targetTable = 'material_targets';
            $modelIdColumn = 'material_id';
        } else {
            return;
        }

        $modelTable = $model->getTable();

        // Build visibility query
        $builder->where(function ($query) use (
            $modelTable,
            $modelIdColumn,
            $targetTable,
            $userId,
            $accessibleGroupIds,
            $childrenStudentIds,
            $scope
        ) {
            // Teacher can see assignments/materials they created or are assigned to
            if ($scope->isTeacher()) {
                $query->where(function ($teacherQuery) use ($modelTable, $userId) {
                    if ($modelTable === 'assignments') {
                        $teacherQuery->where("{$modelTable}.teacher_user_id", $userId);
                    } elseif ($modelTable === 'materials') {
                        $teacherQuery->where("{$modelTable}.created_by", $userId);
                    }
                });
            }

            // Check targets
            $query->orWhereExists(function ($subQuery) use (
                $modelTable,
                $modelIdColumn,
                $targetTable,
                $userId,
                $accessibleGroupIds,
                $childrenStudentIds
            ) {
                $subQuery->select(DB::raw(1))
                    ->from($targetTable)
                    ->whereColumn("{$targetTable}.{$modelIdColumn}", "{$modelTable}.id")
                    ->where(function ($targetQuery) use (
                        $targetTable,
                        $userId,
                        $accessibleGroupIds,
                        $childrenStudentIds
                    ) {
                        // Individual target - user is the student or parent of student
                        $targetQuery->where(function ($q) use ($targetTable, $userId, $childrenStudentIds) {
                            $q->whereNotNull("{$targetTable}.student_user_id")
                                ->where(function ($studentQuery) use ($targetTable, $userId, $childrenStudentIds) {
                                    $studentQuery->where("{$targetTable}.student_user_id", $userId);
                                    if (!empty($childrenStudentIds)) {
                                        $studentQuery->orWhereIn("{$targetTable}.student_user_id", $childrenStudentIds);
                                    }
                                });
                        });

                        // Group target - user is member of the group
                        if (!empty($accessibleGroupIds)) {
                            $targetQuery->orWhere(function ($q) use ($targetTable, $accessibleGroupIds) {
                                $q->whereNotNull("{$targetTable}.group_id")
                                    ->whereIn("{$targetTable}.group_id", $accessibleGroupIds);
                            });
                        }

                        // Subgroup target - user is member of the group containing the subgroup
                        if (!empty($accessibleGroupIds)) {
                            $targetQuery->orWhere(function ($q) use ($targetTable, $accessibleGroupIds) {
                                $q->whereNotNull("{$targetTable}.subgroup_id")
                                    ->whereExists(function ($subgroupQuery) use ($targetTable, $accessibleGroupIds) {
                                        $subgroupQuery->select(DB::raw(1))
                                            ->from('subgroups')
                                            ->whereColumn('subgroups.id', "{$targetTable}.subgroup_id")
                                            ->whereIn('subgroups.group_id', $accessibleGroupIds);
                                    });
                            });
                        }
                    });
            });
        });
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('visibleToUser', function (Builder $builder, ?int $userId = null) {
            return $builder->withoutGlobalScope(VisibleToUserScope::class)
                ->withGlobalScope('visibleToUser', new VisibleToUserScope($userId));
        });
    }
}
