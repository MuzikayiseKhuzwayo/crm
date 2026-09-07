<?php

namespace VentureDrake\LaravelCrm\Support;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class UuidNormalizer
{
    /**
     * Ensure any identifier is converted into a valid RFC 4122 UUID.
     * Prevents PostgreSQL 22P02 (invalid input syntax for type uuid) errors.
     *
     * @param  mixed  $value
     */
    public static function ensureValidUuid($value): string
    {
        if (empty($value)) {
            return (string) Str::uuid();
        }

        $stringValue = trim((string) $value);

        if (Str::isUuid($stringValue)) {
            return strtolower($stringValue);
        }

        // Generate deterministic UUID v5 from string input to ensure reproducibility
        return (string) Uuid::uuid5(Uuid::NAMESPACE_OID, $stringValue);
    }

    /**
     * Ensure identifier is valid UUID or null if empty.
     *
     * @param  mixed  $value
     */
    public static function ensureNullableUuid($value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return self::ensureValidUuid($value);
    }
}
