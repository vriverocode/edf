<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

final class UsernameGenerator
{
    public static function fromEmail(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = Str::limit($base !== '' ? $base : 'residente', 40, '');

        $candidate = $base;
        $suffix = 1;

        while (User::withTrashed()->where('username', $candidate)->exists()) {
            $candidate = $base.'_'.(++$suffix);
        }

        return $candidate;
    }
}
