<?php

namespace App\Support;

final class Permissions
{
    public static function modules(): array
    {
        return config('permissions.modules', []);
    }

    public static function special(): array
    {
        return config('permissions.special', []);
    }

    /** @return list<string> */
    public static function all(): array
    {
        $keys = [];

        foreach (self::modules() as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $keys[] = "{$module}.{$action}";
            }
        }

        return array_merge($keys, array_keys(self::special()));
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /** Expands '*' and 'module.*' patterns into concrete, valid keys. */
    public static function expand(array $patterns): array
    {
        $all = self::all();
        $out = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                $out = array_merge($out, $all);
            } elseif (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($all as $key) {
                    if (str_starts_with($key, $prefix)) {
                        $out[] = $key;
                    }
                }
            } elseif (in_array($pattern, $all, true)) {
                $out[] = $pattern;
            }
        }

        return array_values(array_unique($out));
    }
}
