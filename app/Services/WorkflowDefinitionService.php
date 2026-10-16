<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkflowDefinitionService
{
    public function __construct(private DefinitionValidator $validator) {}

    public function save(User $actor, Workflow $workflow, array $definition, int $revision, bool $publish = false): array
    {
        return DB::transaction(function () use ($actor, $workflow, $definition, $revision, $publish) {
            $workflow = Workflow::lockForUpdate()->findOrFail($workflow->id);
            Gate::forUser($actor)->authorize('update', $workflow);
            abort_if($workflow->draft_revision !== $revision, 409, 'Draft changed. Reload before saving.');
            $definition = $this->validator->validate($definition, $publish);
            $workflow->forceFill(['draft_definition' => $definition, 'draft_revision' => $revision + 1])->save();
            $result = ['revision' => $workflow->draft_revision, 'message' => $publish ? 'Workflow published.' : 'Draft saved.'];
            if ($publish) {
                $version = WorkflowVersion::create(['workflow_id' => $workflow->id, 'version' => (int) $workflow->versions()->max('version') + 1, 'definition' => $definition, 'published_by' => $actor->id]);
                $result['version'] = $version->version;
            }
            AuditLog::create(['actor_id' => $actor->id, 'action' => $publish ? 'workflow.published' : 'workflow.draft_saved', 'target_type' => 'workflow', 'target_id' => $workflow->id, 'metadata' => $result]);

            return $result;
        }, 3);
    }
}
