<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowDefinitionService;
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
                $email = $alias.'@gmail.com';
                $legacy = User::where('email', $alias.'@demo.processflow.test')->lockForUpdate()->first();
                $existing = User::where('email', $email)->lockForUpdate()->first();

                if ($legacy) {
                    if ($existing || $legacy->name !== 'Demo '.$role || ! $legacy->hasExactRoles([$role])) {
                        throw new RuntimeException('Cannot safely migrate demo account to '.$email.'. Resolve the account collision or identity mismatch first.');
                    }

                    $legacy->update(['email' => $email]);

                    continue;
                }

                if ($existing) {
                    if ($existing->name !== 'Demo '.$role || ! $existing->hasExactRoles([$role])) {
                        $this->command?->warn('Existing account '.$email.' left unchanged: demo identity does not match.');
                    }

                    continue;
                }

                $user = User::firstOrCreate(['email' => $email], [
                    'name' => 'Demo '.$role,
                    'password' => $password,
                    'department_id' => Department::where('name', $department)->sole()->id,
                ]);

                if (! $user->wasRecentlyCreated) {
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

        $designer = User::where('email', 'designer@gmail.com')->firstOrFail();
        $approver = User::where('email', 'approver@gmail.com')->firstOrFail();
        if (! $designer->hasExactRoles(['Workflow Designer']) || $designer->name !== 'Demo Workflow Designer' || ! $approver->hasRole('Approver')) {
            return;
        }
        
        $workflow = Workflow::firstOrCreate(['code' => 'DEMO-PROCUREMENT'], ['name' => 'Procurement request', 'description' => 'Configurable procurement approval', 'category' => 'Finance', 'owner_id' => $designer->id, 'status' => 'draft']);
        if (! $workflow->versions()->exists() && $workflow->owner_id === $designer->id && $workflow->status === 'draft') {
            $node = fn ($id, $type, $data) => ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => ucfirst($id), ...$data]];
            $edge = fn ($source, $target, $handle = 'next') => ['id' => $source.'-'.$handle, 'source' => $source, 'target' => $target, 'sourceHandle' => $handle];
            $definition = ['nodes' => [
                $node('start', 'start', []),
                $node('form', 'form', ['fields' => [
                    ['key' => 'item', 'label' => 'Item', 'type' => 'text', 'required' => true],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'min' => 1],
                    ['key' => 'attachment', 'label' => 'Supporting document', 'type' => 'file', 'required' => false],
                ]]),
                $node('budget', 'condition', ['condition' => ['field' => 'amount', 'operator' => 'gte', 'value' => 10000000]]),
                $node('approval', 'approval', ['label' => 'Department approval', 'assignee_type' => 'user', 'assignee_id' => $approver->id, 'deadline_hours' => 24, 'escalation_user_id' => $approver->id]),
                $node('notice', 'notification', ['message' => 'Procurement request processed.']),
                $node('completed', 'end', ['outcome' => 'completed']),
                $node('rejected', 'end', ['outcome' => 'rejected']),
                $node('finance', 'approval', ['label' => 'Finance approval', 'assignee_type' => 'user', 'assignee_id' => $approver->id, 'deadline_hours' => 24]),
            ], 'edges' => [$edge('start', 'form'), $edge('form', 'approval'), $edge('approval', 'budget', 'approved'), $edge('approval', 'rejected', 'rejected'), $edge('budget', 'finance', 'true'), $edge('budget', 'notice', 'false'), $edge('finance', 'notice', 'approved'), $edge('finance', 'rejected', 'rejected'), $edge('notice', 'completed')]];
            app(WorkflowDefinitionService::class)->save($designer, $workflow, $definition, $workflow->draft_revision, true);
        }
    }
}
