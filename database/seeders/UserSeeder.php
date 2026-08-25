<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Default seeder — creates a demo user for development.
 * Customize or remove for production.
 */
final class UserSeeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
        ]);
    }
}
