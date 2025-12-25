<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class DocumentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->permissions()->where('code', 'documents.view')->exists();
    }

    public function view(User $user, Document $document): bool
    {
        $scope = AccessScopeService::forUser($user);

        // Admin/руководство
        if ($scope->isAdmin() || $this->isManagement($user)) {
            return true;
        }

        // Participants in route
        $routeSteps = DB::table('document_routes')
            ->where('document_id', $document->id)
            ->get();

        foreach ($routeSteps as $step) {
            if ($step->approver_user_id === $user->id) {
                return true;
            }
            if ($step->approver_role_id) {
                $userRoleIds = DB::table('user_roles')
                    ->where('user_id', $user->id)
                    ->pluck('role_id')
                    ->toArray();
                if (in_array($step->approver_role_id, $userRoleIds)) {
                    return true;
                }
            }
        }

        // Addresses in acknowledgments
        $acks = DB::table('document_ack')
            ->where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($acks) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->permissions()->where('code', 'documents.create')->exists();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->id === $document->created_by;
    }

    public function approve(User $user, Document $document): bool
    {
        // User must be on current route step (role/user match)
        $currentStep = DB::table('document_routes')
            ->where('document_id', $document->id)
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->first();

        if (!$currentStep) {
            return false;
        }

        $stepData = json_decode($currentStep->step_data_json, true);

        if (isset($stepData['user_id']) && $stepData['user_id'] === $user->id) {
            return true;
        }

        if (isset($stepData['role']) && $this->userHasRole($user, $stepData['role'])) {
            return true;
        }

        return false;
    }

    public function sign(User $user, Document $document): bool
    {
        return $user->permissions()->where('code', 'documents.sign')->exists();
    }

    public function export(User $user, Document $document): bool
    {
        return $this->view($user, $document)
            && $user->permissions()->where('code', 'documents.export')->exists();
    }

    private function isManagement(User $user): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->whereIn('roles.name', ['руководство', 'директор'])
            ->exists();
    }

    private function userHasRole(User $user, string $roleName): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.name', $roleName)
            ->exists();
    }
}
