<?php

namespace Tests\Feature;

use App\Jobs\ProcessWorkflowDeadlines;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecutionLog;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNotification;
use App\Services\DefinitionValidator;
use App\Services\RuntimeAccess;
use App\Services\SubmissionValidator;
use App\Services\WorkflowDefinitionService;
use App\Services\WorkflowEngine;
use App\Services\WorkflowSlaService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RuntimeDemoInstancesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private User $designer;

    private User $requester;

    private User $approver;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoDataSeeder::class);
        $this->designer = User::where('email', 'designer@demo.processflow.test')->firstOrFail();
        $this->requester = User::where('email', 'requester@demo.processflow.test')->firstOrFail();
        $this->approver = User::where('email', 'approver@demo.processflow.test')->firstOrFail();
        $this->workflow = Workflow::where('code', 'DEMO-PROCUREMENT')->firstOrFail();
    }

    private function submit(int $amount = 20000000, ?string $key = null): WorkflowInstance
    {
        return app(WorkflowEngine::class)->submit($this->requester, $this->workflow->id, null, $key ?? (string) Str::uuid(), ['item' => 'Laptop', 'amount' => $amount]);
    }

    public function test_workflow_create_uses_the_shared_vite_entry(): void
    {
        $this->assertStringNotContainsString('pages/{$page', file_get_contents(resource_path('views/app.blade.php')));
        $this->actingAs($this->designer)->get(route('workflows.create'))->assertOk();
    }

    public function test_runtime_samples_are_local_only_and_idempotent(): void
    {
        $seeder = new RuntimeDemoInstancesSeeder;
        try {
            $seeder->run();
            $this->fail('Runtime samples must reject non-local environments.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Runtime samples are only allowed locally.', $exception->getMessage());
        }
        $environment = app()->environment();
        try {
            app()->instance('env', 'local');
            $seeder->run();
            $logs = WorkflowExecutionLog::count();
            $seeder->run();
            $this->assertDatabaseCount('workflow_instances', 5);
            $this->assertSame($logs, WorkflowExecutionLog::count());
            $this->assertSame(2, WorkflowInstance::where('status', 'running')->count());
            $this->assertSame(2, WorkflowInstance::where('status', 'completed')->count());
            $this->assertSame(1, WorkflowInstance::where('status', 'rejected')->count());
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_high_amount_approval_and_duplicate_decision_are_idempotent(): void
    {
        $key = (string) Str::uuid();
        $instance = $this->submit(key: $key);
        $this->assertSame($instance->id, $this->submit(key: $key)->id);
        $task = $instance->tasks()->sole();
        $this->actingAs($this->requester)->post(route('approvals.decision', $task), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->approver)->post(route('approvals.decision', $task), ['decision' => 'approved', 'comment' => 'OK'])->assertRedirect(route('requests.show', $instance));
        $count = $instance->logs()->count();
        $this->post(route('approvals.decision', $task), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame($count, $instance->logs()->count());
        $this->assertSame('running', $instance->fresh()->status);
        $finance = $instance->tasks()->where('status', 'pending')->sole();
        $this->assertSame('finance', $finance->node_id);
        $this->post(route('approvals.decision', $finance), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('completed', $instance->fresh()->status);
        $this->post(route('approvals.decision', $task), ['decision' => 'rejected'])->assertStatus(409);
    }

    public function test_rejection_and_low_amount_follow_configured_branches(): void
    {
        $low = $this->submit(100);
        $this->assertSame('running', $low->status);
        app(WorkflowEngine::class)->decide($this->approver, $low->tasks()->sole(), 'approved', null);
        $this->assertSame('completed', $low->fresh()->status);
        $this->assertSame(1, $low->tasks()->count());
        $high = $this->submit();
        app(WorkflowEngine::class)->decide($this->approver, $high->tasks()->sole(), 'rejected', 'Not approved');
        $this->assertSame('rejected', $high->fresh()->status);
    }

    public function test_draft_revision_ownership_and_snapshot_isolation(): void
    {
        $definition = $this->workflow->latestPublishedVersion->definition;
        $instance = $this->submit();
        $definition['nodes'][6]['data']['outcome'] = 'completed';
        $revision = $this->workflow->draft_revision;
        $this->actingAs($this->requester)->putJson(route('workflows.definition.update', $this->workflow), ['definition' => $definition, 'revision' => $revision])->assertForbidden();
        $this->actingAs($this->designer)->postJson(route('workflows.publish', $this->workflow), ['definition' => $definition, 'revision' => $revision])->assertOk()->assertJsonPath('version', 2);
        $this->putJson(route('workflows.definition.update', $this->workflow), ['definition' => $definition, 'revision' => $revision])->assertStatus(409);
        app(WorkflowEngine::class)->decide($this->approver, $instance->tasks()->sole(), 'rejected', null);
        $this->assertSame('rejected', $instance->fresh()->status);
        $this->expectException(\LogicException::class);
        $instance->version->update(['definition' => $definition]);
    }

    public function test_invalid_graphs_are_rejected(): void
    {
        $base = $this->workflow->latestPublishedVersion->definition;
        $mutations = [
            function (&$graph) {
                $graph['edges'][0]['target'] = 'missing';
            },
            function (&$graph) {
                $graph['edges'][1]['target'] = 'start';
            },
            function (&$graph) {
                $graph['nodes'][] = ['id' => 'orphan', 'type' => 'end', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Orphan']];
            },
            function (&$graph) {
                array_pop($graph['edges']);
            },
            function (&$graph) {
                $graph['nodes'][3]['data']['assignee_id'] = 999999;
            },
            function (&$graph) {
                $graph['nodes'][2]['data']['condition']['field'] = 'missing';
            },
            function (&$graph) {
                $graph['nodes'][2]['data']['condition']['value'] = 'wrong';
            },
        ];
        foreach ($mutations as $mutate) {
            $definition = $base;
            $mutate($definition);
            try {
                app(DefinitionValidator::class)->validate($definition, true);
                $this->fail('Invalid graph accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_private_files_and_deadline_processing_are_scoped_and_idempotent(): void
    {
        Storage::fake('local');
        $instance = app(WorkflowEngine::class)->submit($this->requester, $this->workflow->id, null, (string) Str::uuid(), ['item' => 'Laptop', 'amount' => 20000000, 'attachment' => UploadedFile::fake()->createWithContent('receipt.txt', 'Receipt')]);
        $file = $instance->files()->sole();
        $this->actingAs($this->requester)->get(route('files.download', $file))->assertOk();
        $stranger = User::factory()->create();
        $stranger->assignRole('Requester');
        $this->actingAs($stranger)->get(route('files.download', $file))->assertForbidden();
        $this->actingAs($this->approver)->get(route('files.download', $file))->assertOk();
        $this->travel(25)->hours();
        $this->assertSame(1, app(WorkflowSlaService::class)->run());
        $count = $instance->logs()->count();
        $this->assertSame(0, app(WorkflowSlaService::class)->run());
        $this->assertSame($count, $instance->logs()->count());
    }

    public function test_role_assignment_cancel_and_notification_ownership(): void
    {
        $definition = $this->workflow->latestPublishedVersion->definition;
        $definition['nodes'][3]['data']['assignee_type'] = 'role';
        $definition['nodes'][3]['data']['assignee_id'] = $this->approver->roles()->sole()->id;
        app(WorkflowDefinitionService::class)->save($this->designer, $this->workflow, $definition, $this->workflow->draft_revision, true);
        $instance = $this->submit();
        $this->assertTrue(app(RuntimeAccess::class)->canDecide($this->approver, $instance->tasks()->sole()));
        $this->actingAs($this->approver)->post(route('requests.cancel', $instance))->assertForbidden();
        $notification = WorkflowNotification::where('user_id', $this->approver->id)->firstOrFail();
        $this->actingAs($this->requester)->post(route('notifications.read', $notification))->assertForbidden();
        $this->post(route('requests.cancel', $instance))->assertRedirect();
        $this->assertSame('cancelled', $instance->fresh()->status);
        $this->assertSame('cancelled', $instance->tasks()->sole()->status);
        $this->actingAs($this->approver)->post(route('approvals.decision', $instance->tasks()->sole()), ['decision' => 'approved'])->assertStatus(409);
    }

    public function test_upload_allowlist_and_unknown_data_fields_are_rejected(): void
    {
        $payload = ['workflow_id' => $this->workflow->id, 'idempotency_key' => (string) Str::uuid(), 'data' => ['item' => 'Laptop', 'amount' => 100, 'unknown' => 'value']];
        $this->actingAs($this->requester)->postJson(route('requests.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('data');
        unset($payload['data']['unknown']);
        $payload['data']['attachment'] = UploadedFile::fake()->createWithContent('attack.php', '<?php echo 1;');
        $this->post(route('requests.store'), $payload)->assertSessionHasErrors('data.attachment');
        $this->assertDatabaseCount('workflow_instances', 0);
    }

    public function test_submission_key_rejects_changed_payload_and_normalizes_uuid_case(): void
    {
        $key = (string) Str::uuid();
        $instance = $this->submit(key: $key);
        $this->assertSame($instance->id, $this->submit(key: strtoupper($key))->id);
        $this->actingAs($this->requester)->postJson(route('requests.store'), ['workflow_id' => $this->workflow->id, 'idempotency_key' => $key, 'data' => ['item' => 'Different', 'amount' => 20000000]])->assertConflict();
        $this->assertDatabaseCount('workflow_instances', 1);
        $this->postJson(route('requests.store'), ['workflow_id' => $this->workflow->id, 'idempotency_key' => 'invalid', 'data' => []])->assertUnprocessable();
    }

    public function test_file_retries_compare_content_not_just_filename(): void
    {
        Storage::fake('local');
        $key = (string) Str::uuid();
        $payload = ['workflow_id' => $this->workflow->id, 'idempotency_key' => $key, 'data' => ['item' => 'Laptop', 'amount' => 100, 'attachment' => UploadedFile::fake()->createWithContent('receipt.txt', 'Receipt')]];
        $this->actingAs($this->requester)->post(route('requests.store'), $payload)->assertRedirect();
        $payload['data']['attachment'] = UploadedFile::fake()->createWithContent('receipt.txt', 'Receipt');
        $this->post(route('requests.store'), $payload)->assertRedirect();
        $payload['data']['attachment'] = UploadedFile::fake()->createWithContent('receipt.txt', 'Different');
        $this->post(route('requests.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('workflow_files', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_auditor_can_review_instances_but_cannot_download_private_files(): void
    {
        Storage::fake('local');
        $instance = app(WorkflowEngine::class)->submit($this->requester, $this->workflow->id, null, (string) Str::uuid(), ['item' => 'Laptop', 'amount' => 20000000, 'attachment' => UploadedFile::fake()->createWithContent('receipt.txt', 'Receipt')]);
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');
        $this->actingAs($auditor)->get(route('requests.show', $instance))->assertOk();
        $this->get(route('files.download', $instance->files()->sole()))->assertForbidden();
        $this->actingAs($this->designer)->get(route('files.download', $instance->files()->sole()))->assertOk();
    }

    public function test_instance_routes_do_not_expose_other_requesters_and_approvers_use_inbox(): void
    {
        $instance = $this->submit();
        $stranger = User::factory()->create();
        $stranger->assignRole('Requester');
        $this->actingAs($stranger)->get(route('requests.show', $instance))->assertForbidden();
        $this->get(route('requests.index'))->assertInertia(fn ($page) => $page->has('instances.data', 0));
        $this->actingAs($this->approver)->get(route('requests.index'))->assertInertia(fn ($page) => $page->has('instances.data', 0));
        $this->get(route('requests.show', $instance))->assertOk();
        $this->get(route('approvals.index'))->assertInertia(fn ($page) => $page->has('tasks.data', 1));
    }

    public function test_permission_without_approver_role_cannot_decide_even_when_assigned_role_matches(): void
    {
        $definition = $this->workflow->latestPublishedVersion->definition;
        $role = $this->requester->roles()->sole();
        $this->approver->assignRole($role);
        $definition['nodes'][3]['data']['assignee_type'] = 'role';
        $definition['nodes'][3]['data']['assignee_id'] = $role->id;
        app(WorkflowDefinitionService::class)->save($this->designer, $this->workflow, $definition, $this->workflow->draft_revision, true);
        $instance = $this->submit();
        $this->requester->givePermissionTo('approvals.decide');
        $this->actingAs($this->requester)->post(route('approvals.decision', $instance->tasks()->sole()), ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $instance->tasks()->sole()->status);
    }

    public function test_assigned_user_losing_permission_cannot_decide(): void
    {
        $instance = $this->submit();
        $this->approver->syncRoles([]);
        $this->actingAs($this->approver)->post(route('approvals.decision', $instance->tasks()->sole()), ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('running', $instance->fresh()->status);
    }

    public function test_failed_publish_is_atomic_and_does_not_consume_revision(): void
    {
        $definition = $this->workflow->latestPublishedVersion->definition;
        $definition['edges'] = [];
        $revision = $this->workflow->draft_revision;
        $this->actingAs($this->designer)->postJson(route('workflows.publish', $this->workflow), ['definition' => $definition, 'revision' => $revision])->assertUnprocessable();
        $this->assertSame($revision, $this->workflow->fresh()->draft_revision);
        $this->assertSame(1, $this->workflow->versions()->count());
    }

    public function test_additional_malformed_graphs_cannot_publish(): void
    {
        $base = $this->workflow->latestPublishedVersion->definition;
        $mutations = [
            function (&$graph) {
                unset($graph['nodes'][2]['data']['condition']['value']);
            },
            function (&$graph) {
                $graph['nodes'][2]['data']['condition']['value'] = null;
            },
            function (&$graph) {
                $graph['edges'][4]['sourceHandle'] = 'false';
            },
            function (&$graph) {
                $graph['edges'][3]['sourceHandle'] = 'approved';
            },
            function (&$graph) {
                $graph['edges'][0]['target'] = 'form';
                $graph['edges'][1]['target'] = 'form';
            },
            function (&$graph) {
                $graph['nodes'][] = $graph['nodes'][0];
            },
            function (&$graph) {
                $graph['nodes'][3]['data']['assignee_id'] = $this->requester->id;
            },
            function (&$graph) {
                $graph['nodes'][1]['data']['fields'][0]['max'] = -1;
            },
        ];
        foreach ($mutations as $mutate) {
            $graph = $base;
            $mutate($graph);
            $this->actingAs($this->designer)->postJson(route('workflows.publish', $this->workflow), ['definition' => $graph, 'revision' => $this->workflow->draft_revision])->assertUnprocessable();
        }
        $this->assertSame(1, $this->workflow->versions()->count());
    }

    public function test_sla_escalates_once_and_revokes_original_assignment(): void
    {
        $escalation = User::factory()->create();
        $escalation->assignRole('Approver');
        $instance = $this->submit(100);
        $task = $instance->tasks()->sole();
        $task->update(['due_at' => now()->subMinute(), 'escalation_user_id' => $escalation->id]);
        $this->assertSame(1, app(WorkflowSlaService::class)->run());
        $notifications = WorkflowNotification::count();
        $this->assertSame(0, app(WorkflowSlaService::class)->run());
        $this->assertSame($notifications, WorkflowNotification::count());
        $this->assertSame($escalation->id, $task->fresh()->assignee_id);
        $this->actingAs($this->approver)->post(route('approvals.decision', $task), ['decision' => 'approved'])->assertForbidden();
        $this->get(route('notifications.index'))->assertInertia(fn ($page) => $page->where('notifications.data.0.can_view_instance', false));
        $this->actingAs($escalation)->post(route('approvals.decision', $task), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('completed', $instance->fresh()->status);
    }

    public function test_delayed_deadline_jobs_ignore_cancelled_decided_and_stale_tasks(): void
    {
        $cancelled = $this->submit();
        app(WorkflowEngine::class)->cancel($this->requester, $cancelled);
        $decided = $this->submit(100);
        app(WorkflowEngine::class)->decide($this->approver, $decided->tasks()->sole(), 'approved', null);
        $stale = $this->submit();
        $stale->update(['current_node_id' => 'another-node']);
        $this->travel(25)->hours();
        $notifications = WorkflowNotification::count();
        (new ProcessWorkflowDeadlines)->handle(app(WorkflowSlaService::class));
        $this->assertSame($notifications, WorkflowNotification::count());
        $this->assertNull($stale->tasks()->sole()->reminded_at);
    }

    public function test_form_types_and_bounds_are_enforced_on_the_server(): void
    {
        $fields = [
            ['key' => 'number', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 10],
            ['key' => 'date', 'type' => 'date', 'required' => true],
            ['key' => 'choice', 'type' => 'select', 'required' => true, 'options' => ['A', 'B']],
            ['key' => 'checked', 'type' => 'checkbox', 'required' => true],
        ];
        $valid = ['number' => '2.5', 'date' => '2024-02-29', 'choice' => 'A', 'checked' => '0'];
        $result = app(SubmissionValidator::class)->validate($fields, $valid);
        $this->assertSame(2.5, $result['number']);
        $this->assertFalse($result['checked']);
        foreach ([['number', 'NaN'], ['number', []], ['number', 11], ['date', '2025-02-29'], ['choice', 'C'], ['checked', 'false']] as [$key, $value]) {
            try {
                app(SubmissionValidator::class)->validate($fields, [...$valid, $key => $value]);
                $this->fail('Malformed field accepted: '.$key);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.'.$key, $exception->errors());
            }
        }
    }

    public function test_conditions_use_typed_equality_and_optional_missing_values_are_deterministic(): void
    {
        $engine = app(WorkflowEngine::class);
        $this->assertTrue($engine->evaluate(2.0, 'eq', 2));
        $this->assertFalse($engine->evaluate('2', 'eq', 2));
        $this->assertFalse($engine->evaluate(false, 'eq', 0));
        $this->assertTrue($engine->evaluate(2.0, 'in', [1, 2]));
        $this->assertFalse($engine->evaluate(null, 'neq', 2));
        $this->assertFalse($engine->evaluate(null, 'gte', 0));
    }

    public function test_notification_read_is_idempotent(): void
    {
        $this->submit();
        $notification = WorkflowNotification::where('user_id', $this->approver->id)->firstOrFail();
        $this->actingAs($this->approver)->post(route('notifications.read', $notification))->assertRedirect();
        $readAt = $notification->fresh()->read_at;
        $this->travel(1)->hour();
        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertTrue($readAt->equalTo($notification->fresh()->read_at));
    }

    public function test_logs_cannot_be_edited(): void
    {
        $instance = $this->submit(10);
        $this->expectException(\LogicException::class);
        $instance->logs()->first()->update(['action' => 'forged']);
    }

    public function test_non_requester_cannot_submit_and_archive_blocks_submission(): void
    {
        $this->actingAs($this->approver)->post(route('requests.store'), ['workflow_id' => $this->workflow->id, 'idempotency_key' => (string) Str::uuid(), 'data' => ['item' => 'Laptop', 'amount' => 100]])->assertForbidden();
        $this->workflow->update(['status' => 'archived']);
        $this->actingAs($this->requester)->post(route('requests.store'), ['workflow_id' => $this->workflow->id, 'idempotency_key' => (string) Str::uuid(), 'data' => ['item' => 'Laptop', 'amount' => 100]])->assertStatus(422);
    }
}
