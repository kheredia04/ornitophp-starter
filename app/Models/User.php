<?php

declare(strict_types=1);

namespace App\Models;

use Ornito\Model;

/**
 * The User model — ships with the auth system.
 * Extend it with your own columns as your app grows.
 */
final class User extends Model
{
    protected static string $table = 'users';

    /**
     * Find a user by their email address.
     */
    public static function findByEmail(string $email): ?array
    {
        $results = self::where('email', $email);

        return $results[0] ?? null;
    }
}
