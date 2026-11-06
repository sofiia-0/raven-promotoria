<?php

namespace App\Support;

class CanonicalData
{
    public static function hash(array $values): string
    {
        return hash('sha256', json_encode(self::sort($values), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function sort(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::sort($value);
            }
        }

        return $values;
    }
}
