<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecutionLog;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = Workflow::visibleTo($request->user());
        $counts = [
            'total' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'archived' => (clone $query)->where('status', 'archived')->count(),
        ];
        $stats = ['workflows' => $counts];
        if ($request->user()->hasRole('Super Admin')) {
            $stats['users'] = User::count();
            $stats['departments'] = Department::count();
        }

        $actor = $request->user();
        $instances = WorkflowInstance::query()->when(! $actor->can('requests.view.all') && ! $actor->hasRole('Super Admin'), fn ($query) => $query->where('requester_id', $actor->id));
        $stats['runtime'] = [
            'running' => (clone $instances)->where('status', 'running')->count(),
            'completed' => (clone $instances)->where('status', 'completed')->count(),
            'rejected' => (clone $instances)->where('status', 'rejected')->count(),
            'pending_tasks' => WorkflowTask::where('status', 'pending')->whereHas('instance', fn ($query) => $query->whereIn('id', (clone $instances)->select('id')))->count(),
        ];
        $logs = WorkflowExecutionLog::with('actor:id,name')->whereIn('workflow_instance_id', (clone $instances)->select('id'))->latest()->limit(10)->get()->map(fn ($log) => [...$log->only(['id', 'action', 'node_id', 'created_at', 'workflow_instance_id']), 'actor_name' => $log->actor?->name ?? 'System']);
        $chart = collect(range(6, 0))->map(function ($days) use ($instances) {
            $date = today()->subDays($days);

            return ['date' => $date->toDateString(), 'count' => (clone $instances)->whereDate('created_at', $date)->count()];
        });

        return Inertia::render('dashboard', ['stats' => $stats, 'recent_logs' => $logs, 'chart' => $chart]);
    }
}
