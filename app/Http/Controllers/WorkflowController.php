<?php

namespace App\Http\Controllers;

use App\Http\Requests\DuplicateWorkflowRequest;
use App\Http\Requests\WorkflowRequest;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Workflow::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'archived'])],
        ]);
        $workflows = Workflow::visibleTo($request->user())->with('owner:id,name')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString()
            ->through(fn (Workflow $workflow) => $this->serialize($workflow, $request));

        return Inertia::render('workflows/index', [
            'workflows' => $workflows,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
            'can' => ['create' => $request->user()->can('create', Workflow::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Workflow::class);

        return Inertia::render('workflows/create', ['workflow' => null]);
    }

    public function store(WorkflowRequest $request, WorkflowService $service): RedirectResponse
    {
        $workflow = $service->create($request->user(), $request->validated());

        return to_route('workflows.edit', $workflow);
    }

    public function edit(Request $request, Workflow $workflow): Response
    {
        Gate::authorize('update', $workflow);

        return Inertia::render('workflows/edit', ['workflow' => $this->serialize($workflow->load('owner:id,name'), $request)]);
    }

    public function update(WorkflowRequest $request, Workflow $workflow, WorkflowService $service): RedirectResponse
    {
        $service->update($request->user(), $workflow, $request->validated());

        return to_route('workflows.index');
    }

    public function archive(Request $request, Workflow $workflow, WorkflowService $service): RedirectResponse
    {
        Gate::authorize('update', $workflow);
        $service->archive($request->user(), $workflow);

        return to_route('workflows.index');
    }

    public function duplicate(DuplicateWorkflowRequest $request, Workflow $workflow, WorkflowService $service): RedirectResponse
    {
        $copy = $service->duplicate($request->user(), $workflow, $request->validated());

        return to_route('workflows.edit', $copy);
    }

    public function destroy(Request $request, Workflow $workflow, WorkflowService $service): RedirectResponse
    {
        Gate::authorize('delete', $workflow);
        $service->delete($request->user(), $workflow);

        return to_route('workflows.index');
    }

    private function serialize(Workflow $workflow, Request $request): array
    {
        return [
            ...$workflow->only(['id', 'name', 'code', 'description', 'category', 'status', 'created_at', 'updated_at']),
            'owner' => $workflow->owner->only(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $workflow),
                'delete' => $request->user()->can('delete', $workflow),
                'duplicate' => $request->user()->can('duplicate', $workflow),
            ],
        ];
    }
}
