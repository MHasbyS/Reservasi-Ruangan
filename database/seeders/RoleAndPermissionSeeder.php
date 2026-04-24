<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //List
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions =[
            'manage users',
            'manage rooms',
            'manage schedule',
            'approve reservation',
            'reject reservation',
            'request reservation',
            'view reservation',
            'cancel own reservation'
        ];

        foreach ($permissions as $permission){
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        //roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $karyawan = Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'api']);

        //permission to role
        $admin->givePermissionTo([
            'manage users',
            'manage rooms',
            'manage schedule',
            'approve reservation',
            'reject reservation',
        ]);

        $karyawan->givePermissionTo([
            'request reservation',
            'view reservation',
            'cancel own reservation'
        ]);

    }
}
