<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workflow;
use App\Services\DefinitionValidator;
use App\Services\WorkflowDefinitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class WorkflowBuilderController extends Controller
{
    public function builder(Workflow $workflow)
    {
        Gate::authorize('update', $workflow);

        return Inertia::render('workflows/builder', [
            'workflow' => $workflow->only(['id', 'name', 'code', 'description', 'category', 'status', 'draft_revision']),
            'definition' => $workflow->draft_definition ?? ['nodes' => [], 'edges' => []],
            'versions' => $workflow->versions()->orderByDesc('version')->get(['id', 'version', 'created_at']),
            'users' => User::role('Approver')->permission('approvals.decide')->orderBy('name')->get(['id', 'name']),
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Workflow $workflow, WorkflowDefinitionService $service)
    {
        return response()->json($this->save($request, $workflow, $service));
    }

    public function publish(Request $request, Workflow $workflow, WorkflowDefinitionService $service)
    {
        return response()->json($this->save($request, $workflow, $service, true));
    }

    private function save(Request $request, Workflow $workflow, WorkflowDefinitionService $service, bool $publish = false): array
    {
        Gate::authorize('update', $workflow);
        $data = $request->validate(['definition' => ['required', 'array'], 'revision' => ['required', 'integer', 'min:0']]);

        return $service->save($request->user(), $workflow, $data['definition'], $data['revision'], $publish);
    }

    public function validateDefinition(Request $request, Workflow $workflow, DefinitionValidator $validator)
    {
        Gate::authorize('update', $workflow);
        $data = $request->validate(['definition' => ['required', 'array']]);
        try {
            $validator->validate($data['definition'], true);

            return response()->json(['valid' => true, 'errors' => []]);
        } catch (ValidationException $exception) {
            return response()->json(['valid' => false, 'errors' => collect($exception->errors())->flatten()->values()->all()]);
        }
    }
}
