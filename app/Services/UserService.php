<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
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
            foreach ([['workflow_versions', 'published_by'], ['workflow_instances', 'requester_id'], ['workflow_tasks', 'decided_by'], ['workflow_tasks', 'escalation_user_id'], ['workflow_execution_logs', 'actor_id'], ['audit_logs', 'actor_id'], ['workflow_files', 'user_id'], ['workflow_notifications', 'user_id']] as [$table, $column]) {
                if (DB::table($table)->where($column, $target->id)->exists()) {
                    throw ValidationException::withMessages(['user' => 'Pengguna memiliki referensi riwayat workflow.']);
                }
            }
            if (DB::table('workflow_tasks')->where('assignee_type', 'user')->where('assignee_id', $target->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'Pengguna memiliki tugas workflow.']);
            }
            foreach (WorkflowVersion::cursor() as $version) {
                foreach ($version->definition['nodes'] as $node) {
                    $data = $node['data'];
                    if (($data['assignee_type'] ?? null) === 'user' && (int) ($data['assignee_id'] ?? 0) === $target->id || (int) ($data['escalation_user_id'] ?? 0) === $target->id) {
                        throw ValidationException::withMessages(['user' => 'Pengguna direferensikan versi workflow.']);
                    }
                }
            }
            $target->delete();
        });
    }
}
