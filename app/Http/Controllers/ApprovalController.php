<?php

namespace App\Http\Controllers;

use App\Models\WorkflowTask;
use App\Services\RuntimeSerializer;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function index(Request $request, RuntimeSerializer $serializer)
    {
        $actor = $request->user();
        abort_unless($actor->can('approvals.decide') && $actor->hasRole('Approver'), 403);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])]]);
        $roles = $actor->roles()->pluck('roles.id');
        $tasks = WorkflowTask::with(['instance.workflow', 'instance.requester', 'instance.version'])->where(function ($query) use ($actor, $roles) {
            $query->where(fn ($query) => $query->where('assignee_type', 'user')->where('assignee_id', $actor->id))
                ->orWhere(fn ($query) => $query->where('assignee_type', 'role')->whereIn('assignee_id', $roles))
                ->orWhere('decided_by', $actor->id);
        })->where('status', $filters['status'] ?? 'pending')->latest()->paginate(15)->withQueryString()->through(fn ($task) => $serializer->task($task, $actor, true));

        return Inertia::render('approvals/index', ['tasks' => $tasks, 'filters' => ['status' => $filters['status'] ?? 'pending']]);
    }

    public function decision(Request $request, WorkflowTask $task, WorkflowEngine $engine)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'comment' => ['nullable', 'string', 'max:5000']]);
        $instance = $engine->decide($request->user(), $task, $data['decision'], $data['comment'] ?? null);

        return to_route('requests.show', $instance);
    }
}
