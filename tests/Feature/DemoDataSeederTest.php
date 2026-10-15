<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_only_creates_roles_permissions_and_departments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('departments', 3);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workflows', 0);
    }

    public function test_demo_seed_is_idempotent_and_does_not_reset_changed_data(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (['admin' => 'Super Admin', 'designer' => 'Workflow Designer', 'requester' => 'Requester', 'approver' => 'Approver', 'auditor' => 'Auditor'] as $alias => $role) {
            $user = User::where('email', $alias.'@demo.processflow.test')->sole();
            $this->assertTrue($user->hasExactRoles([$role]));
            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertNotNull($user->department_id);
        }

        $admin = User::where('email', 'admin@demo.processflow.test')->sole();
        $admin->syncRoles(['Requester']);
        $admin->update(['name' => 'Changed', 'password' => Str::random(32)]);
        $before = $admin->fresh()->getAttributes();
        $workflow = Workflow::where('code', 'DEMO-PROCUREMENT')->sole();
        $workflow->update(['name' => 'Changed workflow', 'status' => 'archived']);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('workflows', 3);
        $this->assertDatabaseCount('departments', 3);
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertTrue($admin->fresh()->hasExactRoles(['Requester']));
        $this->assertSame('Changed workflow', $workflow->fresh()->name);
        $this->assertSame(['archived', 'draft'], Workflow::distinct()->orderBy('status')->pluck('status')->all());
    }

    public function test_colliding_accounts_and_workflows_are_not_modified_or_escalated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['email' => 'admin@demo.processflow.test']);
        $admin->assignRole('Requester');
        $designer = User::factory()->create(['email' => 'designer@demo.processflow.test']);
        $designer->assignRole('Requester');
        $before = $admin->fresh()->getAttributes();
        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertTrue($admin->fresh()->hasExactRoles(['Requester']));
        $this->assertTrue($designer->fresh()->hasExactRoles(['Requester']));
        $this->assertDatabaseCount('workflows', 0);
    }

    public function test_colliding_workflow_code_is_left_untouched(): void
    {
        $owner = User::factory()->create();
        $workflow = Workflow::create(['code' => 'DEMO-PROCUREMENT', 'name' => 'Existing', 'owner_id' => $owner->id, 'status' => 'draft']);
        $before = $workflow->fresh()->getAttributes();
        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, $workflow->fresh()->getAttributes());
        $this->assertDatabaseCount('workflows', 3);
    }

    public function test_demo_seed_rejects_production_before_writing_data(): void
    {
        $environment = app()->environment();
        app()->instance('env', 'production');

        try {
            app(DemoDataSeeder::class)->run();
            $this->fail('Production seeding must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Demo data is only allowed in local or testing environments.', $exception->getMessage());
            $this->assertDatabaseCount('roles', 0);
            $this->assertDatabaseCount('users', 0);
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_demo_seed_uses_literal_password_without_configuration(): void
    {
        foreach ([null, '', '   ', Str::random(32)] as $password) {
            config(['demo.password' => $password]);
            $this->seed(DemoDataSeeder::class);

            $this->assertDatabaseCount('users', 5);
            foreach (User::all() as $user) {
                $this->assertTrue(Hash::check('password', $user->password));
            }
        }
    }

    public function test_only_matching_seeded_accounts_have_their_password_reset(): void
    {
        $this->seed(DemoDataSeeder::class);
        $admin = User::where('email', 'admin@demo.processflow.test')->sole();
        $admin->update(['password' => Str::random(32)]);
        $adminBefore = $admin->fresh()->getAttributes();
        $requester = User::where('email', 'requester@demo.processflow.test')->sole();
        $requester->update(['name' => 'Genuine requester', 'password' => Str::random(32)]);
        $requesterBefore = $requester->fresh()->getAttributes();
        $approver = User::where('email', 'approver@demo.processflow.test')->sole();
        $approver->syncRoles(['Requester']);
        $approver->update(['password' => Str::random(32)]);
        $approverBefore = $approver->fresh()->getAttributes();

        $this->seed(DemoDataSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertNotSame('password', $admin->password);
        $this->assertTrue($admin->hasExactRoles(['Super Admin']));
        unset($adminBefore['password'], $adminBefore['updated_at']);
        $adminAfter = $admin->getAttributes();
        unset($adminAfter['password'], $adminAfter['updated_at']);
        $this->assertSame($adminBefore, $adminAfter);
        $this->assertSame($requesterBefore, $requester->fresh()->getAttributes());
        $this->assertTrue($requester->fresh()->hasExactRoles(['Requester']));
        $this->assertSame($approverBefore, $approver->fresh()->getAttributes());
        $this->assertTrue($approver->fresh()->hasExactRoles(['Requester']));
    }
}
