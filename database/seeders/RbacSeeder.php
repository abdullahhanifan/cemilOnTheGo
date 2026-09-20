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

        // Default role: neutral, with no permissions on purpose. A self-registered
        // account gets this role and can do nothing until an admin grants access.
        $default = Role::firstOrCreate(['name' => config('alh.default_role')]);
        $default->syncPermissions([]);

        // Mitra: reserved for a future partner login. Seeded empty and not used yet:
        // partners are records in the `partners` table, not users with this role.
        $mitra = Role::firstOrCreate(['name' => 'mitra']);
        $mitra->syncPermissions([]);
    }
}
