<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('departments/index', ['departments' => Department::withCount('users')->orderBy('name')->paginate(15)]);
    }

    public function store(DepartmentRequest $request, DepartmentService $service): RedirectResponse
    {
        $service->save(null, $request->validated());

        return to_route('departments.index');
    }

    public function update(DepartmentRequest $request, Department $department, DepartmentService $service): RedirectResponse
    {
        $service->save($department, $request->validated());

        return to_route('departments.index');
    }

    public function destroy(Request $request, Department $department, DepartmentService $service): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $service->delete($department);

        return to_route('departments.index');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('Super Admin') && $request->user()->can('departments.manage'), 403);
    }
}
