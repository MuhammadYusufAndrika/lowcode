<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->can('workflows.view.all') || $user->can('workflows.view.own');
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->hasRole('Super Admin') || $user->can('workflows.view.all') || ($user->can('workflows.view.own') && $workflow->owner_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->can('workflows.create');
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $workflow->status === 'draft' && ($user->hasRole('Super Admin') || ($user->can('workflows.update') && $workflow->owner_id === $user->id));
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $workflow->status === 'draft' && ($user->hasRole('Super Admin') || ($user->can('workflows.delete') && $workflow->owner_id === $user->id));
    }

    public function duplicate(User $user, Workflow $workflow): bool
    {
        return $this->create($user) && $this->view($user, $workflow);
    }
}
