<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data is only allowed in local or testing environments.');
        }

        $password = 'password';

        DB::transaction(function () use ($password): void {
            $this->call(DatabaseSeeder::class);

            $accounts = [
                ['admin', 'Super Admin', 'IT'],
                ['designer', 'Workflow Designer', 'IT'],
                ['requester', 'Requester', 'Operations'],
                ['approver', 'Approver', 'Finance'],
                ['auditor', 'Auditor', 'Finance'],
            ];

            foreach ($accounts as [$alias, $role, $department]) {
                $user = User::firstOrCreate(['email' => $alias.'@demo.processflow.test'], [
                    'name' => 'Demo '.$role,
                    'password' => $password,
                    'department_id' => Department::where('name', $department)->sole()->id,
                ]);

                if (! $user->wasRecentlyCreated) {
                    if ($user->name === 'Demo '.$role && $user->hasExactRoles([$role])) {
                        $user->update(['password' => $password]);
                    }

                    continue;
                }

                $user->assignRole($role);

                if ($role !== 'Workflow Designer') {
                    continue;
                }

                foreach ([
                    ['DEMO-PROCUREMENT', 'Procurement request', 'Finance', 'draft'],
                    ['DEMO-ONBOARDING', 'Employee onboarding', 'Operations', 'draft'],
                    ['DEMO-LEGACY', 'Legacy purchase request', 'Finance', 'archived'],
                ] as [$code, $name, $category, $status]) {
                    Workflow::firstOrCreate(['code' => $code], [
                        'name' => $name,
                        'description' => 'Phase 1 demo metadata only; no runtime execution.',
                        'category' => $category,
                        'owner_id' => $user->id,
                        'status' => $status,
                    ]);
                }
            }
        });
    }
}
