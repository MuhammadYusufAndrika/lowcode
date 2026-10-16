<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Spatie\Permission\Models\Role;

class RuntimeSerializer
{
    public function __construct(private RuntimeAccess $access) {}

    public function instance(WorkflowInstance $instance, User $actor, bool $detail = false): array
    {
        $instance->loadMissing(['workflow:id,name,code', 'requester:id,name', 'version']);
        $result = [...$instance->only(['id', 'status', 'current_node_id', 'created_at', 'completed_at']), 'workflow' => $instance->workflow->only(['id', 'name', 'code']), 'requester' => $instance->requester->only(['id', 'name']), 'version' => $instance->version->version];
        if ($detail) {
            $result['data'] = (object) $instance->values()->pluck('value', 'key')->all();
            $result['tasks'] = $instance->tasks()->orderBy('id')->get()->map(fn ($task) => $this->task($task, $actor));
            $result['logs'] = $instance->logs()->with('actor:id,name')->orderBy('id')->get()->map(fn ($log) => [...$log->only(['id', 'action', 'node_id', 'metadata', 'created_at']), 'actor_name' => $log->actor?->name ?? 'System']);
            $result['files'] = ($this->access->canDownload($actor, $instance) ? $instance->files()->get() : collect())->map(fn ($file) => [...$file->only(['id', 'field_key', 'original_name', 'mime', 'size']), 'download_url' => route('files.download', $file)]);
        }

        return $result;
    }

    public function task(WorkflowTask $task, User $actor, bool $withInstance = false): array
    {
        $name = $task->assignee_type === 'user' ? User::find($task->assignee_id)?->name : Role::find($task->assignee_id)?->name;
        $result = [...$task->only(['id', 'node_id', 'node_name', 'status', 'due_at', 'comment', 'decided_at']), 'assignee_name' => $name ?? 'Unavailable', 'can_decide' => $task->status === 'pending' && $task->instance->status === 'running' && $this->access->canDecide($actor, $task)];
        if ($withInstance) {
            $result['instance'] = $this->instance($task->instance, $actor);
        }

        return $result;
    }
}
