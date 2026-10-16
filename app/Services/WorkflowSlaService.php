<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\DB;

class WorkflowSlaService
{
    public function __construct(private WorkflowEngine $engine, private RuntimeAccess $access, private DefinitionValidator $definitions) {}

    public function run(): int
    {
        $processed = 0;
        WorkflowTask::where('status', 'pending')->where('due_at', '<=', now())->orderBy('id')->chunkById(100, function ($tasks) use (&$processed) {
            foreach ($tasks as $candidate) {
                $processed += DB::transaction(function () use ($candidate) {
                    $instance = WorkflowInstance::lockForUpdate()->findOrFail($candidate->workflow_instance_id);
                    $task = WorkflowTask::lockForUpdate()->findOrFail($candidate->id);
                    if ($instance->status !== 'running' || $instance->current_node_id !== $task->node_id || $task->status !== 'pending' || ! $task->due_at || $task->due_at->isFuture()) {
                        return 0;
                    }
                    $changed = false;
                    if (! $task->reminded_at) {
                        foreach ($this->access->recipients($task) as $user) {
                            $this->engine->notify($user->id, $instance, 'Overdue approval: '.$task->node_name);
                        }
                        $task->reminded_at = now();
                        $this->engine->record($instance, 'sla.reminder', null, $task->node_id, ['task_id' => $task->id]);
                        $changed = true;
                    }
                    if (! $task->escalated_at && $task->escalation_user_id && $this->definitions->eligible(User::find($task->escalation_user_id))) {
                        $task->assignee_type = 'user';
                        $task->assignee_id = $task->escalation_user_id;
                        $task->escalated_at = now();
                        $this->engine->notify($task->escalation_user_id, $instance, 'Escalated approval: '.$task->node_name);
                        $this->engine->record($instance, 'sla.escalated', null, $task->node_id, ['task_id' => $task->id, 'user_id' => $task->escalation_user_id]);
                        $changed = true;
                    }
                    $task->save();

                    return $changed ? 1 : 0;
                }, 3);
            }
        });

        return $processed;
    }
}
