<?php

/*
 * Support\Quantity - the normalising and comparison rules for line item
 * quantities, now that they are decimal(15,3) rather than integers.
 */

use VentureDrake\LaravelCrm\Support\Quantity;

test('to float reads the string shape a decimal column returns', function () {
    // MySQL and Postgres hand back "3.500" for a decimal column. SQLite never
    // does, so nothing else in the suite exercises this shape.
    expect(Quantity::toFloat('3.500'))->toBe(3.5);
});

test('to float normalises ints, floats, null and blank', function () {
    expect(Quantity::toFloat(2))->toBe(2.0)
        ->and(Quantity::toFloat(3.5))->toBe(3.5)
        ->and(Quantity::toFloat(null))->toBe(0.0)
        ->and(Quantity::toFloat(''))->toBe(0.0)
        ->and(Quantity::toFloat('not a number'))->toBe(0.0);
});

test('to decimal preserves null but rounds a value to 3 places', function () {
    expect(Quantity::toDecimal(null))->toBeNull()
        ->and(Quantity::toDecimal(''))->toBeNull()
        ->and(Quantity::toDecimal(3.5555))->toBe(3.556)
        ->and(Quantity::toDecimal('2'))->toBe(2.0);
});

test('round never returns null', function () {
    expect(Quantity::round(null))->toBe(0.0)
        ->and(Quantity::round(3.5555))->toBe(3.556);
});

test('is positive ignores binary float dust', function () {
    // What 1.1 - 0.7 - 0.4 actually leaves behind.
    expect(Quantity::isPositive(5.55e-17))->toBeFalse()
        ->and(Quantity::isPositive(0.001))->toBeTrue()
        ->and(Quantity::isPositive(0))->toBeFalse()
        ->and(Quantity::isPositive(-1))->toBeFalse();
});

test('is zero ignores binary float dust in either direction', function () {
    expect(Quantity::isZero(5.55e-17))->toBeTrue()
        ->and(Quantity::isZero(-5.55e-17))->toBeTrue()
        ->and(Quantity::isZero(0.001))->toBeFalse();
});

test('equals compares within the smallest storable difference', function () {
    expect(Quantity::equals(0.1 + 0.2, 0.3))->toBeTrue()
        ->and(Quantity::equals(3.5, '3.500'))->toBeTrue()
        ->and(Quantity::equals(3.5, 3.501))->toBeFalse();
});

test('greater than does not trip on dust', function () {
    expect(Quantity::greaterThan(0.1 + 0.2, 0.3))->toBeFalse()
        ->and(Quantity::greaterThan(2.501, 2.5))->toBeTrue()
        ->and(Quantity::greaterThan(2.5, 2.5))->toBeFalse();
});

test('format drops trailing zeros', function () {
    expect(Quantity::format(2.0))->toBe('2')
        ->and(Quantity::format('3.500'))->toBe('3.5')
        ->and(Quantity::format(0.25))->toBe('0.25')
        ->and(Quantity::format(0))->toBe('0')
        ->and(Quantity::format(null))->toBe('0');
});
