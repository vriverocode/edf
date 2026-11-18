<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

final class UsernameGenerator
{
    private const MAX_LENGTH = 240;

    public static function fromEmail(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = Str::limit($base !== '' ? $base : 'residente', 40, '');

        $candidate = $base;
        $suffix = 1;

        while (self::exists($candidate)) {
            $candidate = $base.'_'.(++$suffix);
        }

        return $candidate;
    }

    public static function fromName(string $nombre, string $apellido): string
    {
        $first = self::normalize($nombre);
        $last = self::normalize($apellido);

        if ($first === '') {
            $first = 'residente';
        }

        $firstLength = mb_strlen($first);

        for ($length = 1; $length <= $firstLength; $length++) {
            $candidate = self::trimCandidate(mb_substr($first, 0, $length).$last);

            if (! self::exists($candidate)) {
                return $candidate;
            }
        }

        $base = self::trimCandidate($first.$last);
        $suffix = 2;

        do {
            $candidate = self::trimCandidate($base, $suffix);
            $suffix++;
        } while (self::exists($candidate));

        return $candidate;
    }

    public static function defaultPassword(string $username): string
    {
        return $username.'2020**';
    }

    private static function exists(string $username): bool
    {
        return User::withTrashed()->where('username', $username)->exists();
    }

    private static function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    private static function trimCandidate(string $candidate, ?int $suffix = null): string
    {
        $suffix = $suffix !== null ? (string) $suffix : '';

        return mb_substr($candidate, 0, self::MAX_LENGTH - mb_strlen($suffix)).$suffix;
    }
}
