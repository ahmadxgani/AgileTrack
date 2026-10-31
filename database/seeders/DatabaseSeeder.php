<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        // Development-only administrator so a fresh clone can sign in and
        // manage accounts. Never seed this in production.
        User::factory()->administrator()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
