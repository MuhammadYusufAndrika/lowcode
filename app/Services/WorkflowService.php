<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkflowService
{
    public function create(User $actor, array $data): Workflow
    {
        return DB::transaction(function () use ($actor, $data) {
            Gate::forUser($actor)->authorize('create', Workflow::class);
            $workflow = Workflow::create([...$data, 'owner_id' => $actor->id, 'status' => 'draft']);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'workflow.created', 'target_type' => 'workflow', 'target_id' => $workflow->id]);

            return $workflow;
        });
    }

    public function update(User $actor, Workflow $workflow, array $data): void
    {
        DB::transaction(function () use ($actor, $workflow, $data) {
            $locked = Workflow::lockForUpdate()->findOrFail($workflow->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $locked->update($data);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'workflow.updated', 'target_type' => 'workflow', 'target_id' => $locked->id, 'metadata' => $data]);
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

            $copy = $this->create($actor, [...$locked->only(['description', 'category']), ...$data]);
            $copy->forceFill(['draft_definition' => $locked->draft_definition])->save();

            return $copy;
        });
    }

    public function delete(User $actor, Workflow $workflow): void
    {
        DB::transaction(function () use ($actor, $workflow) {
            $locked = Workflow::lockForUpdate()->findOrFail($workflow->id);
            Gate::forUser($actor)->authorize('delete', $locked);
            abort_if($locked->versions()->exists() || $locked->instances()->exists(), 422, 'Published or executed workflows cannot be deleted.');
            $locked->delete();
        });
    }
}
