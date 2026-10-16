<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecutionLog;
use App\Models\WorkflowFile;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceData;
use App\Models\WorkflowNotification;
use App\Models\WorkflowTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkflowEngine
{
    public function __construct(private DefinitionValidator $definitions, private SubmissionValidator $submissions, private RuntimeAccess $access) {}

    public function submit(User $actor, int $workflowId, ?int $versionId, string $key, array $data): WorkflowInstance
    {
        abort_unless($actor->can('requests.submit') || $actor->hasRole('Super Admin'), 403);
        Validator::make(['idempotency_key' => $key], ['idempotency_key' => ['required', 'uuid']])->validate();
        $key = strtolower($key);
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $workflowId, $versionId, $key, $data, &$paths) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $existing = WorkflowInstance::where('requester_id', $actor->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    abort_unless($existing->workflow_id === $workflowId && (! $versionId || $existing->workflow_version_id === $versionId), 409, 'Idempotency key already used for a different request.');

                    $values = $this->submissions->validate($this->definitions->fields($existing->version->definition), $data);
                    $stored = $existing->values()->pluck('value', 'key')->all();
                    foreach ($existing->files()->get() as $file) {
                        $stored[$file->field_key] = ['name' => $file->original_name, 'hash' => hash('sha256', Storage::disk('local')->get($file->path))];
                    }
                    foreach ($values as &$value) {
                        if ($value instanceof UploadedFile) {
                            $value = ['name' => mb_substr(basename(str_replace('\\', '/', $value->getClientOriginalName())), 0, 200), 'hash' => hash_file('sha256', $value->getRealPath())];
                        }
                    }
                    unset($value);
                    ksort($values);
                    ksort($stored);
                    abort_unless(json_encode($values) === json_encode($stored), 409, 'Idempotency key already used for a different payload.');

                    return $existing;
                }
                $workflow = Workflow::lockForUpdate()->findOrFail($workflowId);
                abort_if($workflow->status === 'archived', 422, 'Archived workflows cannot accept requests.');
                $version = $workflow->versions()->when($versionId, fn ($query) => $query->whereKey($versionId))->orderByDesc('version')->first();
                if (! $version) {
                    throw ValidationException::withMessages(['version_id' => 'A published version belonging to this workflow is required.']);
                }
                $values = $this->submissions->validate($this->definitions->fields($version->definition), $data);
                $instance = WorkflowInstance::create(['workflow_id' => $workflow->id, 'workflow_version_id' => $version->id, 'requester_id' => $actor->id, 'idempotency_key' => $key, 'status' => 'running']);
                foreach ($values as $field => $value) {
                    if ($value instanceof UploadedFile) {
                        $path = $value->store('workflow-files', 'local');
                        if (! $path) {
                            throw new \RuntimeException('File could not be stored.');
                        }
                        $paths[] = $path;
                        $file = WorkflowFile::create(['workflow_instance_id' => $instance->id, 'user_id' => $actor->id, 'field_key' => $field, 'path' => $path, 'original_name' => mb_substr(basename(str_replace('\\', '/', $value->getClientOriginalName())), 0, 200), 'mime' => $value->getMimeType(), 'size' => $value->getSize()]);
                        $value = ['file_id' => $file->id, 'name' => $file->original_name];
                    }
                    WorkflowInstanceData::create(['workflow_instance_id' => $instance->id, 'key' => $field, 'value' => $value]);
                }
                $this->record($instance, 'request.submitted', $actor);
                $start = collect($version->definition['nodes'])->firstWhere('type', 'start');
                $this->advance($instance, $start['id'], $actor);

                return $instance->fresh();
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function decide(User $actor, WorkflowTask $task, string $decision, ?string $comment): WorkflowInstance
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);

        return DB::transaction(function () use ($actor, $task, $decision, $comment) {
            $instance = WorkflowInstance::lockForUpdate()->findOrFail($task->workflow_instance_id);
            $task = WorkflowTask::lockForUpdate()->findOrFail($task->id);
            if ($task->status !== 'pending') {
                abort_unless($task->decided_by === $actor->id && $task->status === $decision, 409, 'Task was already decided.');
                abort_unless($this->access->canDecide($actor, $task), 403);

                return $instance;
            }
            abort_unless($this->access->canDecide($actor, $task), 403);
            abort_unless($instance->status === 'running' && $instance->current_node_id === $task->node_id, 409, 'Request is not waiting for this task.');
            $task->update(['status' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(), 'comment' => $comment]);
            $this->record($instance, 'approval.'.$decision, $actor, $task->node_id, ['task_id' => $task->id, 'comment' => $comment]);
            $this->notify($instance->requester_id, $instance, 'Approval '.$decision.': '.$task->node_name);
            $this->advance($instance, $this->next($instance->version->definition, $task->node_id, $decision), $actor);

            return $instance;
        }, 3);
    }

    public function cancel(User $actor, WorkflowInstance $instance): void
    {
        DB::transaction(function () use ($actor, $instance) {
            $instance = WorkflowInstance::lockForUpdate()->findOrFail($instance->id);
            abort_unless($instance->requester_id === $actor->id, 403);
            abort_unless($instance->status === 'running', 409, 'Only running requests can be cancelled.');
            $instance->tasks()->where('status', 'pending')->update(['status' => 'cancelled']);
            $instance->update(['status' => 'cancelled', 'completed_at' => now()]);
            $this->record($instance, 'request.cancelled', $actor);
        }, 3);
    }

    private function advance(WorkflowInstance $instance, string $nodeId, ?User $actor): void
    {
        $definition = $instance->version->definition;
        $nodes = collect($definition['nodes'])->keyBy('id');
        for ($step = 0; $step < 101; $step++) {
            $node = $nodes->get($nodeId);
            if (! $node) {
                throw new \LogicException('Published snapshot has an invalid transition.');
            }
            $instance->update(['current_node_id' => $nodeId]);
            $this->record($instance, 'node.entered', $actor, $nodeId);
            $data = $node['data'];
            if ($node['type'] === 'end') {
                $status = $data['outcome'] ?? 'completed';
                $instance->update(['status' => $status, 'completed_at' => now()]);
                $this->record($instance, 'request.'.$status, $actor, $nodeId);
                $this->notify($instance->requester_id, $instance, 'Request '.$status);

                return;
            }
            if ($node['type'] === 'approval') {
                $task = WorkflowTask::create(['workflow_instance_id' => $instance->id, 'node_id' => $nodeId, 'node_name' => $data['label'], 'assignee_type' => $data['assignee_type'], 'assignee_id' => $data['assignee_id'], 'escalation_user_id' => $data['escalation_user_id'] ?? null, 'status' => 'pending', 'due_at' => isset($data['deadline_hours']) ? now()->addSeconds((int) round($data['deadline_hours'] * 3600)) : null]);
                $this->record($instance, 'approval.assigned', $actor, $nodeId, ['task_id' => $task->id]);
                foreach ($this->access->recipients($task) as $recipient) {
                    $this->notify($recipient->id, $instance, 'Approval required: '.$data['label']);
                }

                return;
            }
            $handle = 'next';
            if ($node['type'] === 'condition') {
                $condition = $data['condition'];
                $value = $instance->values()->where('key', $condition['field'])->first()?->value;
                $result = $this->evaluate($value, $condition['operator'], $condition['value']);
                $handle = $result ? 'true' : 'false';
                $this->record($instance, 'condition.evaluated', $actor, $nodeId, ['result' => $result]);
            } elseif ($node['type'] === 'notification') {
                $this->notify($instance->requester_id, $instance, $data['message'] ?? $data['label']);
                $this->record($instance, 'notification.sent', $actor, $nodeId);
            }
            $nodeId = $this->next($definition, $nodeId, $handle);
        }
        throw new \LogicException('Workflow execution limit exceeded.');
    }

    public function evaluate(mixed $actual, string $operator, mixed $expected): bool
    {
        if ($actual === null) {
            return false;
        }
        $equal = fn ($left, $right) => (is_int($left) || is_float($left)) && (is_int($right) || is_float($right)) ? (float) $left === (float) $right : $left === $right;

        return match ($operator) {
            'eq' => $equal($actual, $expected),
            'neq' => ! $equal($actual, $expected),
            'gt' => is_numeric($actual) && $actual > $expected,
            'gte' => is_numeric($actual) && $actual >= $expected,
            'lt' => is_numeric($actual) && $actual < $expected,
            'lte' => is_numeric($actual) && $actual <= $expected,
            'in' => collect($expected)->contains(fn ($value) => $equal($actual, $value)),
            default => throw new \LogicException('Unknown condition operator.'),
        };
    }

    private function next(array $definition, string $nodeId, string $handle): string
    {
        foreach ($definition['edges'] as $edge) {
            if ($edge['source'] === $nodeId && ($edge['sourceHandle'] ?? 'next') === $handle) {
                return $edge['target'];
            }
        }
        throw new \LogicException('Missing transition in published snapshot.');
    }

    public function record(WorkflowInstance $instance, string $action, ?User $actor = null, ?string $nodeId = null, array $metadata = []): void
    {
        WorkflowExecutionLog::create(['workflow_instance_id' => $instance->id, 'actor_id' => $actor?->id, 'action' => $action, 'node_id' => $nodeId, 'metadata' => $metadata]);
        AuditLog::create(['actor_id' => $actor?->id, 'action' => $action, 'target_type' => 'workflow_instance', 'target_id' => $instance->id, 'metadata' => ['node_id' => $nodeId, ...$metadata]]);
    }

    public function notify(int $userId, WorkflowInstance $instance, string $message): void
    {
        WorkflowNotification::create(['user_id' => $userId, 'workflow_instance_id' => $instance->id, 'message' => $message]);
    }
}
