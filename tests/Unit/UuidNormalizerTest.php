<?php

namespace VentureDrake\LaravelCrm\Tests\Unit;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use VentureDrake\LaravelCrm\Support\UuidNormalizer;

class UuidNormalizerTest extends TestCase
{
    /** @test */
    public function it_leaves_valid_uuid_intact()
    {
        $validUuid = (string) Str::uuid();
        $normalized = UuidNormalizer::ensureValidUuid($validUuid);

        $this->assertEquals(strtolower($validUuid), $normalized);
    }

    /** @test */
    public function it_converts_arbitrary_strings_into_deterministic_uuid_v5()
    {
        $legacyString = 'partner_vendor_external_code_123';
        $uuid1 = UuidNormalizer::ensureValidUuid($legacyString);
        $uuid2 = UuidNormalizer::ensureValidUuid($legacyString);

        $this->assertTrue(Str::isUuid($uuid1));
        $this->assertEquals($uuid1, $uuid2, 'Deterministic hashing must return identical UUID for identical string');
    }

    /** @test */
    public function it_handles_empty_and_null_values()
    {
        $this->assertNull(UuidNormalizer::ensureNullableUuid(null));
        $this->assertNull(UuidNormalizer::ensureNullableUuid(''));
        $this->assertNull(UuidNormalizer::ensureNullableUuid('null'));

        $generated = UuidNormalizer::ensureValidUuid(null);
        $this->assertTrue(Str::isUuid($generated));
    }
}
