<?php

namespace Database\Seeders;

use App\Helpers\MenuHelper;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Permissions are derived from MenuHelper::getAllPermissions() and follow
     * the naming convention: {group}:{resource}-{action}
     *
     * To add new permissions, add a `permission_key` to the relevant item
     * in MenuHelper::getMenuGroups(). Do NOT hardcode permissions here.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Derive all permissions from the single source of truth (MenuHelper)
        $permissions = MenuHelper::getAllPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Remove any stale permissions no longer in use
        Permission::whereNotIn('name', $permissions)->delete();

        // Admin: full access to everything
        $admin = Role::firstOrCreate(['name' => config('alh.super_admin_role')]);
        $admin->syncPermissions(Permission::all());

        // User: base role without admin access
        $user = Role::firstOrCreate(['name' => config('alh.default_role')]);
        $user->syncPermissions(['dashboard:dashboard-view']);
    }
}
