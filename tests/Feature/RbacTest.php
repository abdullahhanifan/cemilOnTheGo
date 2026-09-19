<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->seed(RbacSeeder::class);
});

test('guest is redirected to signin when accessing RBAC pages', function () {
    $this->get('/rbac/users')
        ->assertRedirect(route('signin'));

    $this->get('/rbac/roles')
        ->assertRedirect(route('signin'));
});

test('user without access-control permission is blocked from RBAC pages', function () {
    $user = User::factory()->create();
    // Default role only has dashboard access
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user)
        ->get('/rbac/users')
        ->assertStatus(403);

    $this->actingAs($user)
        ->get('/rbac/roles')
        ->assertStatus(403);
});

test('admin can access RBAC pages', function () {
    $admin = User::factory()->create([
        'username' => 'superadmin',
    ]);
    $admin->assignRole(config('alh.super_admin_role'));

    // Make sure Gate before interceptor is loaded (registered in AppServiceProvider)
    $this->actingAs($admin)
        ->get('/rbac/users')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get('/rbac/roles')
        ->assertSuccessful();
});

test('seeder gives admin every permission and user only dashboard view', function () {
    $admin = Role::findByName(config('alh.super_admin_role'));
    $user = Role::findByName(config('alh.default_role'));

    expect($admin->permissions)->toHaveCount(count(MenuHelper::getAllPermissions()));
    expect($user->permissions->pluck('name')->all())->toEqual(['dashboard:dashboard-view']);
});

test('admin can deactivate user and selectedUser is reset to null', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $targetUser = User::factory()->create([
        'name' => 'Target User',
        'username' => 'targetuser',
        'status' => 'active',
    ]);
    $targetUser->assignRole(config('alh.default_role'));

    Livewire::actingAs($admin)
        ->test('rbac.user-delete-modal')
        ->call('openDelete', $targetUser->id, $targetUser->name, $targetUser->username)
        ->assertSet('userId', $targetUser->id)
        ->assertSet('isDeleteOpen', true)
        ->call('deleteUser')
        ->assertSet('userId', 0)
        ->assertSet('isDeleteOpen', false)
        ->assertHasNoErrors();

    // Users are deactivated, not hard-deleted, so their history and role
    // assignments are preserved and the action can be undone later.
    $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'status' => 'inactive']);
});

test('admin cannot deactivate their own account through the delete modal', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole(config('alh.super_admin_role'));

    Livewire::actingAs($admin)
        ->test('rbac.user-delete-modal')
        ->call('openDelete', $admin->id, $admin->name, $admin->username)
        ->call('deleteUser');

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 'active']);
});

test('a role without access-control:users-create cannot call saveUser directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    Livewire::actingAs($user)
        ->test('rbac.user-form-modal')
        ->set('name', 'Intruder')
        ->set('username', 'intruder2')
        ->set('email', 'intruder2@example.test')
        ->set('roleName', config('alh.default_role'))
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('saveUser')
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['username' => 'intruder2']);
});

test('a role without access-control:users-delete cannot call deleteUser directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $targetUser = User::factory()->create(['status' => 'active']);

    Livewire::actingAs($user)
        ->test('rbac.user-delete-modal')
        ->call('openDelete', $targetUser->id, $targetUser->name, $targetUser->username)
        ->call('deleteUser')
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'status' => 'active']);
});

test('a role without access-control:roles-create cannot call saveRole directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    Livewire::actingAs($user)
        ->test('rbac.role-form-modal')
        ->set('name', 'Intruder Role')
        ->call('saveRole')
        ->assertForbidden();

    $this->assertDatabaseMissing('roles', ['name' => 'Intruder Role']);
});

test('a role without access-control:roles-delete cannot call deleteRole directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $targetRole = Role::create(['name' => 'SurvivesTheAttempt']);

    Livewire::actingAs($user)
        ->test('rbac.role-delete-modal')
        ->call('openDelete', $targetRole->id, $targetRole->name, 0)
        ->call('deleteRole')
        ->assertForbidden();

    $this->assertDatabaseHas('roles', ['id' => $targetRole->id]);
});

test('the super admin role cannot be deleted even by an admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $superAdminRole = Role::findByName(config('alh.super_admin_role'));

    Livewire::actingAs($admin)
        ->test('rbac.role-delete-modal')
        ->call('openDelete', $superAdminRole->id, $superAdminRole->name, 0)
        ->call('deleteRole')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id]);
});

test('admin can delete role and selectedRole is reset to null', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $targetRole = Role::create(['name' => 'DeleteMeRole']);

    Livewire::actingAs($admin)
        ->test('rbac.role-delete-modal')
        ->call('openDelete', $targetRole->id, $targetRole->name, 0)
        ->assertSet('roleId', $targetRole->id)
        ->assertSet('isDeleteOpen', true)
        ->call('deleteRole')
        ->assertSet('roleId', 0)
        ->assertSet('isDeleteOpen', false)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('roles', ['id' => $targetRole->id]);
});

test('admin can toggle all permissions in a group', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $accessControlPermissions = [
        'access-control:users-view',
        'access-control:users-create',
        'access-control:users-edit',
        'access-control:users-delete',
        'access-control:roles-view',
        'access-control:roles-create',
        'access-control:roles-edit',
        'access-control:roles-delete',
    ];

    Livewire::actingAs($admin)
        ->test('rbac.role-form-modal')
        ->assertSet('selectedPermissions', [])
        ->call('toggleGroup', 'Access Control')
        ->assertSet('selectedPermissions', $accessControlPermissions)
        ->call('toggleGroup', 'Access Control')
        ->assertSet('selectedPermissions', [])
        ->assertHasNoErrors();
});

test('admin can use user form modal to create and edit user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    Livewire::actingAs($admin)
        ->test('rbac.user-form-modal')
        ->call('openForm')
        ->assertSet('userId', 0)
        ->set('name', 'John Doe')
        ->set('username', 'johndoe')
        ->set('email', 'johndoe@example.test')
        ->set('roleName', config('alh.default_role'))
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertDispatched('user-saved');

    $this->assertDatabaseHas('users', [
        'username' => 'johndoe',
        'email' => 'johndoe@example.test',
    ]);

    $newUser = User::where('username', 'johndoe')->first();

    Livewire::actingAs($admin)
        ->test('rbac.user-form-modal')
        ->call('openForm', $newUser->id)
        ->assertSet('userId', $newUser->id)
        ->assertSet('name', 'John Doe')
        ->set('name', 'John Doe Updated')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertDispatched('user-saved');

    $this->assertDatabaseHas('users', [
        'id' => $newUser->id,
        'name' => 'John Doe Updated',
    ]);
});

test('user form modal requires an email', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    Livewire::actingAs($admin)
        ->test('rbac.user-form-modal')
        ->call('openForm')
        ->set('name', 'No Email')
        ->set('username', 'noemail')
        ->set('roleName', config('alh.default_role'))
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('saveUser')
        ->assertHasErrors(['email' => 'required']);

    $this->assertDatabaseMissing('users', ['username' => 'noemail']);
});

test('admin can view user details using detail modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $targetUser = User::factory()->create([
        'name' => 'Detail User',
        'username' => 'detailuser',
    ]);

    Livewire::actingAs($admin)
        ->test('rbac.user-detail-modal')
        ->assertSet('isDetailOpen', false)
        ->call('openDetail', $targetUser->id)
        ->assertSet('isDetailOpen', true)
        ->assertSet('selectedUser.id', $targetUser->id)
        ->assertHasNoErrors();
});

test('admin can use role form modal to create and edit roles', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    Livewire::actingAs($admin)
        ->test('rbac.role-form-modal')
        ->call('openForm')
        ->assertSet('roleId', 0)
        ->set('name', 'New Test Role')
        ->set('selectedPermissions', ['access-control:users-view'])
        ->call('saveRole')
        ->assertHasNoErrors()
        ->assertDispatched('role-saved');

    $this->assertDatabaseHas('roles', [
        'name' => 'New Test Role',
    ]);

    $newRole = Role::where('name', 'New Test Role')->first();

    Livewire::actingAs($admin)
        ->test('rbac.role-form-modal')
        ->call('openForm', $newRole->id)
        ->assertSet('roleId', $newRole->id)
        ->set('name', 'New Test Role Updated')
        ->call('saveRole')
        ->assertHasNoErrors()
        ->assertDispatched('role-saved');

    $this->assertDatabaseHas('roles', [
        'id' => $newRole->id,
        'name' => 'New Test Role Updated',
    ]);
});
