<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\Workflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actor(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function workflow(User $owner, array $attributes = []): Workflow
    {
        return Workflow::create([...['name' => 'Procurement', 'code' => 'PROC', 'owner_id' => $owner->id, 'status' => 'draft'], ...$attributes]);
    }

    public function test_designer_can_create_update_duplicate_archive_and_delete_drafts(): void
    {
        $designer = $this->actor('Workflow Designer');
        $this->actingAs($designer)->post(route('workflows.store'), ['name' => 'Procurement', 'code' => 'PROC'])->assertRedirect();
        $workflow = Workflow::firstOrFail();
        $this->assertSame($designer->id, $workflow->owner_id);
        $this->put(route('workflows.update', $workflow), ['name' => 'Updated', 'code' => 'PROC'])->assertRedirect();
        $this->post(route('workflows.duplicate', $workflow), ['name' => 'Copy', 'code' => 'COPY'])->assertRedirect();
        $copy = Workflow::where('code', 'COPY')->firstOrFail();
        $this->delete(route('workflows.destroy', $copy))->assertRedirect();
        $this->assertModelMissing($copy);
        $this->post(route('workflows.archive', $workflow))->assertRedirect();
        $this->assertSame('archived', $workflow->fresh()->status);
        $this->delete(route('workflows.destroy', $workflow))->assertForbidden();
        $this->put(route('workflows.update', $workflow), ['name' => 'No', 'code' => 'PROC'])->assertForbidden();
    }

    public function test_designer_is_owner_scoped_auditor_read_only_and_admin_can_manage_all(): void
    {
        $designer = $this->actor('Workflow Designer');
        $other = $this->actor('Workflow Designer');
        $workflow = $this->workflow($other);
        $this->actingAs($designer)->get(route('workflows.index'))->assertInertia(fn (Assert $page) => $page->component('workflows/index', false)->has('workflows.data', 0));
        $this->get(route('workflows.edit', $workflow))->assertForbidden();
        $this->put(route('workflows.update', $workflow), ['name' => 'No', 'code' => 'PROC'])->assertForbidden();
        $this->post(route('workflows.duplicate', $workflow), ['name' => 'No', 'code' => 'NO'])->assertForbidden();
        $this->actingAs($this->actor('Auditor'))->get(route('workflows.index'))->assertInertia(fn (Assert $page) => $page->component('workflows/index', false)->has('workflows.data', 1)->where('can.create', false));
        $this->delete(route('workflows.destroy', $workflow))->assertForbidden();
        $this->actingAs($this->actor('Super Admin'))->put(route('workflows.update', $workflow), ['name' => 'Admin', 'code' => 'PROC'])->assertRedirect();
        $this->actingAs($this->actor('Requester'))->get(route('workflows.index'))->assertForbidden();
    }

    public function test_workflow_validation_rejects_duplicate_code_and_owner_or_status_injection(): void
    {
        $designer = $this->actor('Workflow Designer');
        $workflow = $this->workflow($designer);
        $this->actingAs($designer)->post(route('workflows.store'), ['name' => 'Duplicate', 'code' => $workflow->code])->assertSessionHasErrors('code');
        $this->post(route('workflows.store'), ['name' => 'Injected', 'code' => 'INJECT', 'status' => 'archived', 'owner_id' => 999])->assertSessionHasErrors(['status', 'owner_id']);
        $this->post(route('workflows.store'), ['name' => '', 'code' => 'bad code'])->assertSessionHasErrors(['name', 'code']);
    }

    public function test_admin_user_crud_roles_departments_and_self_protection(): void
    {
        $admin = $this->actor('Super Admin');
        $department = Department::create(['name' => 'IT']);
        $data = ['name' => 'Employee', 'email' => 'employee@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'roles' => ['Requester'], 'department_id' => $department->id];
        $this->actingAs($admin)->post(route('users.store'), $data)->assertRedirect();
        $employee = User::where('email', $data['email'])->firstOrFail();
        $this->assertTrue($employee->hasRole('Requester'));
        $this->assertSame($department->id, $employee->department_id);
        $this->put(route('users.update', $employee), [...$data, 'roles' => ['Approver'], 'password' => null, 'password_confirmation' => null])->assertRedirect();
        $this->assertTrue($employee->fresh()->hasRole('Approver'));
        $this->delete(route('users.destroy', $admin))->assertForbidden();
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'roles' => ['Requester']])->assertSessionHasErrors('roles');
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('user');
        $this->assertAuthenticatedAs($admin);
        $this->delete(route('users.destroy', $employee))->assertRedirect();
        $this->assertModelMissing($employee);
    }

    public function test_admin_can_remove_other_admin_but_cannot_demote_self(): void
    {
        $admin = $this->actor('Super Admin');
        $other = $this->actor('Super Admin');
        $this->actingAs($admin)->put(route('users.update', $other), ['name' => $other->name, 'email' => $other->email, 'roles' => ['Requester']])->assertRedirect();
        $this->assertFalse($other->fresh()->hasRole('Super Admin'));
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'roles' => ['Requester']])->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->hasRole('Super Admin'));
        $other->assignRole('Super Admin');
        $this->delete(route('users.destroy', $other))->assertRedirect();
        $this->assertModelMissing($other);
        $this->assertSame(1, User::role('Super Admin')->count());
    }

    public function test_user_validation_and_index_contract(): void
    {
        $admin = $this->actor('Super Admin');
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Invalid', 'email' => $admin->email, 'password' => 'password', 'password_confirmation' => 'different', 'roles' => ['Unknown'], 'department_id' => 999])->assertSessionHasErrors(['email', 'password', 'roles.0', 'department_id']);
        $this->get(route('users.index'))->assertInertia(fn (Assert $page) => $page->component('users/index', false)->has('users.data', 1)->has('roles', 5)->has('departments', 0)->missing('users.data.0.password'));
    }

    public function test_non_admin_cannot_manage_users_and_owners_cannot_be_deleted(): void
    {
        $designer = $this->actor('Workflow Designer');
        $this->actingAs($designer)->get(route('users.index'))->assertForbidden();
        $this->post(route('users.store'), [])->assertForbidden();
        $this->workflow($designer);
        $this->actingAs($this->actor('Super Admin'))->delete(route('users.destroy', $designer))->assertSessionHasErrors('user');
        $this->assertModelExists($designer);
    }

    public function test_registration_grants_only_requester_even_with_injected_roles(): void
    {
        $this->post(route('register'), ['name' => 'New', 'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'roles' => ['Super Admin'], 'department_id' => 999])->assertRedirect(route('dashboard'));
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(['Requester'], $user->getRoleNames()->all());
        $this->assertNull($user->department_id);
        $this->assertCount(0, $user->getAllPermissions());
    }

    public function test_registration_works_before_roles_are_seeded(): void
    {
        Role::query()->delete();
        $this->post(route('register'), ['name' => 'New', 'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->hasRole('Requester'));
    }

    public function test_dashboard_counts_are_scoped_and_admin_counts_are_private(): void
    {
        $designer = $this->actor('Workflow Designer');
        $this->workflow($designer);
        $this->workflow($this->actor('Workflow Designer'), ['code' => 'OTHER', 'status' => 'archived']);
        $this->actingAs($designer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('dashboard', false)->where('stats.workflows.total', 1)->where('stats.workflows.draft', 1)->missing('stats.users')->missing('stats.departments'));
        $this->actingAs($this->actor('Super Admin'))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('dashboard', false)->where('stats.workflows.total', 2)->where('stats.users', 3)->where('stats.departments', 0));
        $this->actingAs($this->actor('Requester'))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('dashboard', false)->where('stats.workflows.total', 0));
    }

    public function test_departments_are_admin_only_and_deletion_clears_user_department(): void
    {
        $this->actingAs($this->actor('Requester'))->post(route('departments.store'), ['name' => 'IT'])->assertForbidden();
        $admin = $this->actor('Super Admin');
        $this->actingAs($admin)->post(route('departments.store'), ['name' => 'IT'])->assertRedirect();
        $department = Department::firstOrFail();
        $admin->update(['department_id' => $department->id]);
        $this->get(route('departments.index'))->assertInertia(fn (Assert $page) => $page->component('departments/index', false)->has('departments.data', 1)->where('departments.data.0.users_count', 1));
        $this->post(route('departments.store'), ['name' => 'IT'])->assertSessionHasErrors('name');
        $this->delete(route('departments.destroy', $department))->assertRedirect();
        $this->assertNull($admin->fresh()->department_id);
    }
}
