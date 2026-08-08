<?php

namespace VentureDrake\LaravelCrm\Support;

class Money
{
    /**
     * Normalise a user supplied money value to a float.
     *
     * Money inputs are masked in the UI ($money) so they arrive as formatted
     * strings such as "$1,234.56" — arithmetic on those raises "A non-numeric
     * value encountered" (or a TypeError on an empty string) in PHP 8.
     */
    public static function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /**
     * Normalise a user supplied money value to the integer (cents) representation
     * stored in the database.
     */
    public static function toInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(self::toFloat($value) * 100);
    }
}
