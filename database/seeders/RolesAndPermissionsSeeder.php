<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $reader = ['comments.create'];
        $author = [...$reader, 'admin.access', 'articles.create', 'articles.edit-own'];
        $editor = [...$author, 'articles.review', 'categories.manage'];
        $moderator = [...$reader, 'admin.access', 'comments.manage'];
        $admin = [...$editor, ...$moderator, 'articles.publish', 'data.manage', 'sources.view', 'sources.manage', 'audit.view', 'system.view'];
        $superAdmin = [...$admin, 'users.manage', 'roles.manage', 'settings.manage'];
        $definitions = [
            'reader' => ['Pembaca', $reader],
            'author' => ['Penulis', $author],
            'editor' => ['Editor', $editor],
            'moderator' => ['Moderator', $moderator],
            'admin' => ['Administrator', $admin],
            'super_admin' => ['Super Administrator', $superAdmin],
        ];

        foreach (array_unique($superAdmin) as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        foreach ($definitions as $name => [$label, $permissions]) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label]);
            $role->permissions()->sync(Permission::whereIn('name', array_unique($permissions))->pluck('id'));
        }
    }
}
