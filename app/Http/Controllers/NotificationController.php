<?php

namespace App\Http\Controllers;

use App\Models\WorkflowNotification;
use App\Services\RuntimeAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request, RuntimeAccess $access)
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->hasRole('Super Admin'), 403);

        return Inertia::render('notifications/index', ['notifications' => WorkflowNotification::with('instance.workflow')->where('user_id', $request->user()->id)->latest()->paginate(20)->through(fn ($notification) => [...$notification->only(['id', 'message', 'workflow_instance_id', 'read_at', 'created_at']), 'can_view_instance' => $notification->instance && $access->canView($request->user(), $notification->instance)])]);
    }

    public function read(Request $request, WorkflowNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        WorkflowNotification::whereKey($notification->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
