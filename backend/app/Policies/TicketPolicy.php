<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class TicketPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin()
            || $user->permissions()->where('code', 'tickets.manage')->exists()
            || $user->permissions()->where('code', 'tickets.create')->exists();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        $scope = AccessScopeService::forUser($user);

        if ($scope->isAdmin()) {
            return true;
        }

        if ($user->id === $ticket->created_by || $user->id === $ticket->assigned_to) {
            return true;
        }

        // Curator by group (if ticket linked to group)
        // TODO: add group_id to tickets table if needed
        // For now, check if user is curator and ticket category matches

        // Methodist
        if ($this->isMethodist($user)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isStudent()
            || $scope->isParent()
            || $scope->isTeacher()
            || $user->permissions()->where('code', 'tickets.create')->exists();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->manage($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin();
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        $scope = AccessScopeService::forUser($user);

        if ($scope->isAdmin()) {
            return true;
        }

        if ($user->id === $ticket->assigned_to) {
            return true;
        }

        // Curator by group
        // TODO: implement when group_id added to tickets

        // Methodist
        if ($this->isMethodist($user)) {
            return true;
        }

        return $user->permissions()->where('code', 'tickets.manage')->exists();
    }

    private function isMethodist(User $user): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.name', 'методист')
            ->exists();
    }
}
