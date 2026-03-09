<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ProgramSeeder::class,
            BranchSeeder::class,
        ]);

        User::factory()->admin()->create([
            'name' => 'BCI Admin',
            'email' => 'admin@bridgecollegeinternational.com',
            'cnic' => '35202-9999999-1',
        ]);

        User::factory()->create([
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'cnic' => '35202-7654321-1',
        ]);
    }
}
