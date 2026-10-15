<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $users = User::with(['roles', 'department:id,name'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(15)->withQueryString()
            ->through(fn (User $user) => $this->serialize($user, $request));

        return Inertia::render('users/index', [...$this->options(), 'users' => $users, 'filters' => ['search' => $filters['search'] ?? '']]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/create', [...$this->options(), 'user' => null]);
    }

    public function store(UserRequest $request, UserService $service): RedirectResponse
    {
        $service->create($request->validated());

        return to_route('users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('users/edit', [...$this->options(), 'user' => $this->serialize($user->load(['roles', 'department:id,name']), $request)]);
    }

    public function update(UserRequest $request, User $user, UserService $service): RedirectResponse
    {
        $service->update($request->user(), $user, $request->validated());

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user, UserService $service): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $service->delete($request->user(), $user);

        return to_route('users.index');
    }

    private function options(): array
    {
        return [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function serialize(User $user, Request $request): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'department_id', 'created_at', 'updated_at']),
            'department' => $user->department?->only(['id', 'name']),
            'roles' => $user->getRoleNames()->values(),
            'can' => ['update' => $request->user()->can('update', $user), 'delete' => $request->user()->can('delete', $user)],
        ];
    }
}
