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
            $user = User::where('email', $alias.'@gmail.com')->sole();
            $this->assertTrue($user->hasExactRoles([$role]));
            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertNotNull($user->department_id);
        }

        $admin = User::where('email', 'admin@gmail.com')->sole();
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
        $admin = User::factory()->create(['email' => 'admin@gmail.com']);
        $admin->assignRole('Requester');
        $designer = User::factory()->create(['email' => 'designer@gmail.com']);
        $designer->assignRole('Requester');
        $before = $admin->fresh()->getAttributes();
        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertTrue($admin->fresh()->hasExactRoles(['Requester']));
        $this->assertTrue($designer->fresh()->hasExactRoles(['Requester']));
        $this->assertDatabaseCount('workflows', 0);
    }

    public function test_legacy_demo_accounts_are_renamed_in_place_without_resetting_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $before = [];
        foreach (User::all() as $user) {
            $user->update(['email' => str_replace('@gmail.com', '@demo.processflow.test', $user->email), 'password' => Str::random(32)]);
            $before[$user->id] = $user->fresh()->getAttributes();
        }
        $workflow = Workflow::where('code', 'DEMO-PROCUREMENT')->sole();
        $workflowBefore = $workflow->getAttributes();
        $versionsBefore = $workflow->versions()->get()->toArray();

        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('users', 5);
        foreach ($before as $id => $attributes) {
            $user = User::findOrFail($id);
            $this->assertSame(str_replace('@demo.processflow.test', '@gmail.com', $attributes['email']), $user->email);
            unset($attributes['email'], $attributes['updated_at']);
            $after = $user->getAttributes();
            unset($after['email'], $after['updated_at']);
            $this->assertSame($attributes, $after);
            $this->assertTrue($user->hasExactRoles([str_replace('Demo ', '', $user->name)]));
        }
        $this->assertSame($workflowBefore, $workflow->fresh()->getAttributes());
        $this->assertSame($versionsBefore, $workflow->versions()->get()->toArray());
    }

    public function test_legacy_email_collision_rolls_back_all_account_renames(): void
    {
        $this->seed(DemoDataSeeder::class);
        foreach (User::all() as $user) {
            $user->update(['email' => str_replace('@gmail.com', '@demo.processflow.test', $user->email)]);
        }
        $collision = User::factory()->create(['email' => 'approver@gmail.com']);
        $collision->assignRole('Requester');
        $before = User::orderBy('id')->get()->toArray();

        try {
            $this->seed(DemoDataSeeder::class);
            $this->fail('Email collisions must reject migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot safely migrate demo account to approver@gmail.com', $exception->getMessage());
        }

        $this->assertSame($before, User::orderBy('id')->get()->toArray());
        $this->assertTrue($collision->fresh()->hasExactRoles(['Requester']));
    }

    public function test_legacy_accounts_with_changed_identity_are_not_migrated_or_escalated(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach ([['Changed name', 'Super Admin'], ['Demo Super Admin', 'Requester']] as [$name, $role]) {
            $user = User::factory()->create(['email' => 'admin@demo.processflow.test', 'name' => $name]);
            $user->assignRole($role);
            $before = $user->fresh()->getAttributes();

            try {
                $this->seed(DemoDataSeeder::class);
                $this->fail('Changed legacy identities must reject migration.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('identity mismatch', $exception->getMessage());
            }

            $this->assertSame($before, $user->fresh()->getAttributes());
            $this->assertTrue($user->fresh()->hasExactRoles([$role]));
            $this->assertDatabaseMissing('users', ['email' => 'admin@gmail.com']);
            $user->delete();
        }
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

    public function test_existing_account_passwords_are_preserved(): void
    {
        $this->seed(DemoDataSeeder::class);
        $admin = User::where('email', 'admin@gmail.com')->sole();
        $admin->update(['password' => Str::random(32)]);
        $adminBefore = $admin->fresh()->getAttributes();
        $requester = User::where('email', 'requester@gmail.com')->sole();
        $requester->update(['name' => 'Genuine requester', 'password' => Str::random(32)]);
        $requesterBefore = $requester->fresh()->getAttributes();
        $approver = User::where('email', 'approver@gmail.com')->sole();
        $approver->syncRoles(['Requester']);
        $approver->update(['password' => Str::random(32)]);
        $approverBefore = $approver->fresh()->getAttributes();

        $this->seed(DemoDataSeeder::class);

        $admin->refresh();
        $this->assertSame($adminBefore['password'], $admin->password);
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
