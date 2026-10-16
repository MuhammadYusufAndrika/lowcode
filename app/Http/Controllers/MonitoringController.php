<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Services\RuntimeSerializer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    public function index(Request $request, RuntimeSerializer $serializer)
    {
        abort_unless($request->user()->can('monitoring.view') || $request->user()->hasRole('Super Admin'), 403);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['running', 'completed', 'rejected', 'failed', 'cancelled'])]]);
        $instances = WorkflowInstance::with(['workflow', 'requester', 'version'])->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20)->withQueryString()->through(fn ($instance) => $serializer->instance($instance, $request->user()));

        return Inertia::render('monitoring/index', ['instances' => $instances, 'stats' => $this->stats(), 'filters' => ['status' => $filters['status'] ?? '']]);
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->can('reports.view') || $request->user()->hasRole('Super Admin'), 403);
        $finished = WorkflowInstance::whereNotNull('completed_at')->get(['created_at', 'completed_at']);
        $average = $finished->isEmpty() ? 0 : $finished->avg(fn ($instance) => $instance->created_at->diffInSeconds($instance->completed_at));
        $bottlenecks = WorkflowTask::where('status', 'pending')->selectRaw('node_name, count(*) as pending')->groupBy('node_name')->orderByDesc('pending')->get();

        return Inertia::render('reports/index', ['stats' => [...$this->stats(), 'average_completion_hours' => round($average / 3600, 2), 'overdue_tasks' => WorkflowTask::where('status', 'pending')->where('due_at', '<', now())->count(), 'bottlenecks' => $bottlenecks], 'audit_logs' => AuditLog::with('actor:id,name')->latest()->paginate(30)]);
    }

    private function stats(): array
    {
        $counts = WorkflowInstance::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(['running', 'completed', 'rejected', 'failed', 'cancelled'])->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }
}
