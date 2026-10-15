<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except('roles')->all());
            $user->syncRoles($data['roles']);

            return $user;
        });
    }

    public function update(User $actor, User $target, array $data): void
    {
        DB::transaction(function () use ($actor, $target, $data) {
            $adminRole = Role::where('name', 'Super Admin')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $target = User::lockForUpdate()->findOrFail($target->id);
            Gate::forUser($actor)->authorize('update', $target);
            if ($target->hasRole($adminRole) && ! in_array('Super Admin', $data['roles'], true)) {
                if ($target->id === $actor->id || User::role($adminRole)->count() <= 1) {
                    throw ValidationException::withMessages(['roles' => 'Administrator tidak dapat menghapus peran admin sendiri atau admin terakhir.']);
                }
            }
            $attributes = collect($data)->except('roles')->all();
            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }
            if ($target->email !== $attributes['email']) {
                $target->email_verified_at = null;
            }
            $target->fill($attributes)->save();
            $target->syncRoles($data['roles']);
        });
    }

    public function delete(User $actor, User $target, bool $selfService = false): void
    {
        DB::transaction(function () use ($actor, $target, $selfService) {
            $role = Role::where('name', 'Super Admin')->where('guard_name', 'web')->lockForUpdate()->first();
            $target = User::lockForUpdate()->findOrFail($target->id);
            if (! $selfService) {
                Gate::forUser($actor)->authorize('delete', $target);
            }
            if ($target->hasRole('Super Admin') && ($selfService || ($role && User::role($role)->count() <= 1))) {
                throw ValidationException::withMessages(['user' => 'Administrator tidak dapat menghapus akun sendiri atau admin terakhir.']);
            }
            if (Workflow::where('owner_id', $target->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'Pengguna masih memiliki workflow.']);
            }
            $target->delete();
        });
    }
}
