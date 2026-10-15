<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkflowService
{
    public function create(User $actor, array $data): Workflow
    {
        return DB::transaction(fn () => Workflow::create([...$data, 'owner_id' => $actor->id, 'status' => 'draft']));
    }

    public function update(User $actor, Workflow $workflow, array $data): void
    {
        DB::transaction(function () use ($actor, $workflow, $data) {
            $locked = Workflow::lockForUpdate()->findOrFail($workflow->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $locked->update($data);
        });
    }

    public function archive(User $actor, Workflow $workflow): void
    {
        $this->update($actor, $workflow, ['status' => 'archived']);
    }

    public function duplicate(User $actor, Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($actor, $workflow, $data) {
            $locked = Workflow::lockForUpdate()->findOrFail($workflow->id);
            Gate::forUser($actor)->authorize('duplicate', $locked);

            return $this->create($actor, [...$locked->only(['description', 'category']), ...$data]);
        });
    }

    public function delete(User $actor, Workflow $workflow): void
    {
        DB::transaction(function () use ($actor, $workflow) {
            $locked = Workflow::lockForUpdate()->findOrFail($workflow->id);
            Gate::forUser($actor)->authorize('delete', $locked);
            $locked->delete();
        });
    }
}
