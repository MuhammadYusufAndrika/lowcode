<?php

namespace App\Http\Controllers;

use App\Models\WorkflowFile;
use App\Models\WorkflowInstance;
use App\Services\RuntimeAccess;
use App\Services\RuntimeSerializer;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RequestController extends Controller
{
    public function index(Request $request, RuntimeSerializer $serializer)
    {
        $actor = $request->user();
        abort_unless($actor->can('requests.view.own') || $actor->can('requests.view.all') || $actor->hasRole('Super Admin'), 403);
        $instances = WorkflowInstance::with(['workflow', 'requester', 'version'])->when(! $actor->can('requests.view.all') && ! $actor->hasRole('Super Admin'), fn ($query) => $query->where('requester_id', $actor->id))->latest()->paginate(15)->through(fn ($instance) => $serializer->instance($instance, $actor));

        return Inertia::render('requests/index', ['instances' => $instances]);
    }

    public function store(Request $request, WorkflowEngine $engine)
    {
        $data = $request->validate(['workflow_id' => ['required', 'integer'], 'version_id' => ['nullable', 'integer'], 'idempotency_key' => ['required', 'uuid'], 'data' => ['present', 'array']]);
        $instance = $engine->submit($request->user(), $data['workflow_id'], $data['version_id'] ?? null, $data['idempotency_key'], $data['data']);

        return to_route('requests.show', $instance);
    }

    public function show(Request $request, WorkflowInstance $instance, RuntimeAccess $access, RuntimeSerializer $serializer)
    {
        abort_unless($access->canView($request->user(), $instance), 403);

        return Inertia::render('requests/show', ['instance' => $serializer->instance($instance, $request->user(), true)]);
    }

    public function cancel(Request $request, WorkflowInstance $instance, WorkflowEngine $engine)
    {
        $engine->cancel($request->user(), $instance);

        return to_route('requests.show', $instance);
    }

    public function download(Request $request, WorkflowFile $file, RuntimeAccess $access)
    {
        abort_unless($access->canDownload($request->user(), $file->instance), 403);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
