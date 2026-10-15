<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
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

        return Inertia::render('dashboard', ['stats' => $stats]);
    }
}
