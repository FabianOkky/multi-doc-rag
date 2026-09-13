<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates a single local demo account so a fresh clone has something to sign
     * in with. It is deliberately the only thing seeded: workspaces and documents
     * are what the app is for, and they are far more interesting when they are
     * your own files.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'fabian@example.com'],
            [
                'name' => 'Fabian Okky',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
