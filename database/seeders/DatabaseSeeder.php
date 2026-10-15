<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        foreach (['IT', 'Finance', 'Operations'] as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
