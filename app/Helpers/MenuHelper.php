<?php

namespace App\Helpers;

use App\Models\User;

class MenuHelper
{
    public static function getMenuGroups(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'items' => [
                    [
                        'icon' => 'dashboard',
                        'name' => 'Dashboard',
                        'path' => '/dashboard',
                        'permission_key' => 'dashboard:dashboard',
                    ],
                ],
            ],
            [
                'title' => 'Toko',
                'items' => [
                    [
                        'icon' => 'store',
                        'name' => 'Daftar Toko',
                        'path' => '/store',
                        'permission_key' => 'store:store',
                    ],
                ],
            ],
            [
                'title' => 'Access Control',
                'items' => [
                    [
                        'icon' => 'user-management',
                        'name' => 'User Management',
                        'path' => '/rbac/users',
                        'permission_key' => 'access-control:users',
                    ],
                    [
                        'icon' => 'role-management',
                        'name' => 'Role Management',
                        'path' => '/rbac/roles',
                        'permission_key' => 'access-control:roles',
                    ],
                ],
            ],
        ];
    }

    /**
     * Derive permission groups from the menu structure.
     *
     * Returns a nested array keyed by group title → item name → action → permission name.
     * Only menu items that carry a `permission_key` are included.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function getPermissionGroups(): array
    {
        $actions = ['view', 'create', 'edit', 'delete'];
        $groups = [];

        foreach (self::getMenuGroups() as $group) {
            foreach ($group['items'] as $item) {
                $key = $item['permission_key'] ?? null;
                if (! $key) {
                    continue;
                }

                $groupTitle = $group['title'];
                $groups[$groupTitle][$item['name']] = array_combine(
                    $actions,
                    array_map(fn (string $action) => "{$key}-{$action}", $actions)
                );
            }
        }

        return $groups;
    }

    /**
     * Return a flat list of every permission name derived from the menu structure.
     *
     * Useful for seeding the permissions table without duplicating the list elsewhere.
     *
     * @return list<string>
     */
    public static function getAllPermissions(): array
    {
        $permissions = [];

        foreach (self::getPermissionGroups() as $resources) {
            foreach ($resources as $actions) {
                foreach ($actions as $permissionName) {
                    $permissions[] = $permissionName;
                }
            }
        }

        return $permissions;
    }

    public static function isActive($path)
    {
        return request()->is(ltrim($path, '/'));
    }

    /**
     * Check if a user has access to a specific menu item.
     */
    public static function hasAccess(array $item, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        $key = $item['permission_key'] ?? null;
        if (! $key) {
            return true;
        }

        return $user->can($key) || $user->can("{$key}-view");
    }

    /**
     * Filter menu groups and items based on user RBAC permissions.
     *
     * Items without permission access are removed.
     * Groups with no accessible items are also omitted.
     */
    public static function getFilteredMenuGroups(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return [];
        }

        $filteredGroups = [];

        foreach (self::getMenuGroups() as $group) {
            $filteredItems = [];

            foreach ($group['items'] as $item) {
                if (! self::hasAccess($item, $user)) {
                    continue;
                }

                if (isset($item['subItems'])) {
                    $filteredSubItems = [];
                    foreach ($item['subItems'] as $subItem) {
                        if (self::hasAccess($subItem, $user)) {
                            $filteredSubItems[] = $subItem;
                        }
                    }
                    if (! empty($filteredSubItems)) {
                        $item['subItems'] = $filteredSubItems;
                        $filteredItems[] = $item;
                    }
                } else {
                    $filteredItems[] = $item;
                }
            }

            if (! empty($filteredItems)) {
                $group['items'] = $filteredItems;
                $filteredGroups[] = $group;
            }
        }

        return $filteredGroups;
    }

    /**
     * Map a collection or array of permission names to their Menu Group Title and Menu Item Name.
     *
     * @param  iterable<string>  $permissionNames
     * @return array<string, array<string, list<string>>> GroupTitle => [ItemName => [actions...]]
     */
    public static function groupPermissionsByMenu(iterable $permissionNames): array
    {
        $permissionNames = is_array($permissionNames) ? $permissionNames : iterator_to_array($permissionNames);
        $permMap = [];

        foreach (self::getPermissionGroups() as $groupTitle => $items) {
            foreach ($items as $itemName => $actions) {
                foreach ($actions as $action => $permName) {
                    $permMap[$permName] = [
                        'group' => $groupTitle,
                        'item' => $itemName,
                        'action' => $action,
                    ];
                }
            }
        }

        $result = [];
        foreach ($permissionNames as $permName) {
            if (isset($permMap[$permName])) {
                $info = $permMap[$permName];
                $result[$info['group']][$info['item']][] = $info['action'];
            } else {
                [$module, $rest] = array_pad(explode(':', $permName, 2), 2, $permName);
                $lastDash = strrpos($rest, '-');
                if ($lastDash !== false) {
                    $feature = substr($rest, 0, $lastDash);
                    $action = substr($rest, $lastDash + 1);
                } else {
                    $feature = $rest;
                    $action = $rest;
                }
                $groupName = str_replace(['-', '_'], ' ', ucfirst($module));
                $featureName = str_replace(['-', '_'], ' ', ucfirst($feature));
                $result[$groupName][$featureName][] = $action;
            }
        }

        return $result;
    }
}
