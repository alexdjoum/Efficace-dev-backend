<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Super Admin']);

        $user = \App\Models\User::factory()->create([
            "email" => "tatchumf@gmail.com",

        ]);

        $user->assignRole($role);

        $employee = \App\Models\Employee::factory()->create();

        $employee->user()->save($user);
    }
}