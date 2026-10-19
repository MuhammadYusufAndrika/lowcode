<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\WorkflowEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RuntimeDemoInstancesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Runtime samples are only allowed locally.');
        }

        DB::transaction(function (): void {
            $requester = User::where('email', 'requester@gmail.com')->sole();
            $approver = User::where('email', 'approver@gmail.com')->sole();
            $workflow = Workflow::where('code', 'DEMO-PROCUREMENT')->sole();
            $version = $workflow->versions()->orderByDesc('version')->firstOrFail();
            $engine = app(WorkflowEngine::class);

            foreach ([
                ['10000000-0000-4000-8000-000000000001', 'Low value awaiting department approval', 500000, 'running', 0],
                ['10000000-0000-4000-8000-000000000002', 'High value awaiting Finance approval', 15000000, 'running', 1],
                ['10000000-0000-4000-8000-000000000003', 'Completed low value procurement', 750000, 'completed', 2],
                ['10000000-0000-4000-8000-000000000004', 'Completed high value procurement', 20000000, 'completed', 3],
                ['10000000-0000-4000-8000-000000000005', 'Rejected high value procurement', 25000000, 'rejected', 1],
            ] as [$key, $item, $amount, $expected, $steps]) {
                if (WorkflowInstance::where('requester_id', $requester->id)->where('idempotency_key', $key)->exists()) {
                    continue;
                }
                $instance = $engine->submit($requester, $workflow->id, $version->id, $key, ['item' => $item, 'amount' => $amount]);
                for ($step = 0; $step < $steps; $step++) {
                    $task = $instance->tasks()->where('status', 'pending')->first();
                    if (! $task) {
                        break;
                    }
                    $engine->decide($approver, $task, $expected === 'rejected' ? 'rejected' : 'approved', 'Local demo sample');
                }
                if ($instance->fresh()->status !== $expected) {
                    throw new RuntimeException('Published procurement graph does not support the expected demo path.');
                }
            }
        });
    }
}
