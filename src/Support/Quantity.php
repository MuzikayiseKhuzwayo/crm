<?php

namespace VentureDrake\LaravelCrm\Support;

class Quantity
{
    /**
     * Decimal places a line item quantity is stored to - decimal(15,3).
     */
    const SCALE = 3;

    /**
     * Half the smallest storable unit. Two quantities closer together than
     * this cannot be told apart once stored, so for our purposes they are
     * the same number.
     */
    const EPSILON = 0.0005;

    /**
     * Normalise a quantity to a float.
     *
     * A decimal column hands PHP the string "3.500" on MySQL and Postgres
     * (SQLite returns an int), and the number inputs post strings too, so
     * quantities arrive in every shape and need normalising rather than
     * casting.
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
     * Normalise a quantity to the value stored in the database, preserving
     * null - a null quantity is not the same as a quantity of zero.
     */
    public static function toDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::round($value);
    }

    /**
     * Round to the stored scale. Anything finer than 3dp cannot be kept.
     */
    public static function round($value): float
    {
        return round(self::toFloat($value), self::SCALE);
    }

    /**
     * Two quantities are equal when they are closer than the smallest
     * difference the column can record.
     *
     * Subtracting quantities accumulates binary float error - 1.1 less 0.7
     * less 0.4 leaves 1.11e-16, not 0 - so drawdown totals have to be
     * compared with a tolerance rather than with == or > 0.
     */
    public static function equals($a, $b): bool
    {
        return abs(self::toFloat($a) - self::toFloat($b)) < self::EPSILON;
    }

    public static function isPositive($value): bool
    {
        return self::toFloat($value) >= self::EPSILON;
    }

    public static function isZero($value): bool
    {
        return abs(self::toFloat($value)) < self::EPSILON;
    }

    public static function greaterThan($a, $b): bool
    {
        return (self::toFloat($a) - self::toFloat($b)) >= self::EPSILON;
    }

    /**
     * Render a quantity for a hint, a validation message or a max
     * attribute - trailing zeros dropped, so 2.0 reads "2" and 3.500
     * reads "3.5".
     */
    public static function format($value): string
    {
        $rounded = self::round($value);

        return rtrim(rtrim(number_format($rounded, self::SCALE, '.', ''), '0'), '.') ?: '0';
    }
}
