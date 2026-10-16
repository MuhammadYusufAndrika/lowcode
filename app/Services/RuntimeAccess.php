<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Spatie\Permission\Models\Role;

class RuntimeAccess
{
    public function canDecide(User $user, WorkflowTask $task): bool
    {
        if (! $user->can('approvals.decide') || ! $user->hasRole('Approver')) {
            return false;
        }

        return $task->assignee_type === 'user'
            ? (int) $task->assignee_id === $user->id
            : $user->roles()->where('roles.id', $task->assignee_id)->exists();
    }

    public function canView(User $user, WorkflowInstance $instance): bool
    {
        return $user->can('requests.view.all') || $user->hasRole('Super Admin')
            || ($user->can('requests.view.own') && $instance->requester_id === $user->id)
            || $instance->tasks()->get()->contains(fn ($task) => $this->canDecide($user, $task) || $task->decided_by === $user->id);
    }

    public function canDownload(User $user, WorkflowInstance $instance): bool
    {
        return $instance->requester_id === $user->id
            || $instance->workflow->owner_id === $user->id
            || $instance->tasks()->get()->contains(fn ($task) => $this->canDecide($user, $task));
    }

    public function recipients(WorkflowTask $task)
    {
        if ($task->assignee_type === 'user') {
            return User::whereKey($task->assignee_id)->get()->filter(fn ($user) => $this->canDecide($user, $task));
        }
        $role = Role::find($task->assignee_id);

        return $role ? User::role($role->name)->get()->filter(fn ($user) => $this->canDecide($user, $task)) : collect();
    }
}
