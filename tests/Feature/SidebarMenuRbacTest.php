<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

test('admin gets all menu groups and items in filtered sidebar', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $allGroups = MenuHelper::getMenuGroups();
    $filteredGroups = MenuHelper::getFilteredMenuGroups($admin);

    expect($filteredGroups)->toHaveCount(count($allGroups));
    expect(array_column($filteredGroups, 'title'))->toEqual(['Dashboard', 'Vendor', 'Access Control']);
});

test('default user role only sees the dashboard group in filtered sidebar', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $filteredGroups = MenuHelper::getFilteredMenuGroups($user);
    $titles = array_column($filteredGroups, 'title');

    expect($titles)->toEqual(['Dashboard']);
    expect($titles)->not->toContain('Vendor');
    expect($titles)->not->toContain('Access Control');
});

test('user with no permissions gets empty sidebar groups', function () {
    $userWithoutPermissions = User::factory()->create();

    $filteredGroups = MenuHelper::getFilteredMenuGroups($userWithoutPermissions);

    expect($filteredGroups)->toBeEmpty();
});

test('menu permissions are derived per action from the menu keys', function () {
    expect(MenuHelper::getAllPermissions())->toEqual([
        'dashboard:dashboard-view',
        'dashboard:dashboard-create',
        'dashboard:dashboard-edit',
        'dashboard:dashboard-delete',
        'vendor:vendor-view',
        'vendor:vendor-create',
        'vendor:vendor-edit',
        'vendor:vendor-delete',
        'access-control:users-view',
        'access-control:users-create',
        'access-control:users-edit',
        'access-control:users-delete',
        'access-control:roles-view',
        'access-control:roles-create',
        'access-control:roles-edit',
        'access-control:roles-delete',
    ]);
});
