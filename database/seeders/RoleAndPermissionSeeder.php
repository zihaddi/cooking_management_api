<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Course permissions
            'view courses',
            'create courses',
            'edit courses',
            'delete courses',
            'publish courses',
            'cancel courses',
            
            // Recipe permissions
            'view recipes',
            'create recipes',
            'edit recipes',
            'delete recipes',
            
            // Student permissions
            'view students',
            'create students',
            'edit students',
            'delete students',
            
            // Instructor permissions
            'view instructors',
            'create instructors',
            'edit instructors',
            'delete instructors',
            
            // Registration permissions
            'create registrations',
            'verify registrations',
            'cancel registrations',
            
            // Payment permissions
            'create payments',
            'view payments',
            'verify payments',
            'view payment reports',
            
            // Certificate permissions
            'generate certificates',
            'view certificates',
            
            // Dashboard permissions
            'view dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin role - has all permissions
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        // Admin role
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view courses', 'create courses', 'edit courses', 'publish courses', 'cancel courses',
            'view recipes', 'create recipes', 'edit recipes',
            'view students', 'create students', 'edit students',
            'view instructors', 'create instructors', 'edit instructors',
            'verify registrations',
            'view payments', 'verify payments', 'view payment reports',
            'generate certificates', 'view certificates',
            'view dashboard',
        ]);

        // Course Administrator role
        $courseAdminRole = Role::create(['name' => 'course-administrator']);
        $courseAdminRole->givePermissionTo([
            'view courses', 'create courses', 'edit courses', 'publish courses', 'cancel courses',
            'view recipes', 'create recipes', 'edit recipes',
            'view students',
            'view instructors',
            'verify registrations',
            'view payments', 'verify payments',
            'generate certificates', 'view certificates',
            'view dashboard',
        ]);

        // Instructor role
        $instructorRole = Role::create(['name' => 'instructor']);
        $instructorRole->givePermissionTo([
            'view courses',
            'view recipes', 'create recipes', 'edit recipes',
            'view students',
        ]);

        // Student role
        $studentRole = Role::create(['name' => 'student']);
        $studentRole->givePermissionTo([
            'view courses',
            'view recipes',
            'create payments',
        ]);

        // Create a super admin user
        $user = \App\Models\User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $user->assignRole('super-admin');
    }
}