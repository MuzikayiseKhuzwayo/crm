<?php

use VentureDrake\LaravelCrm\Support\Money;

test('money converts plain numeric values', function () {
    expect(Money::toFloat(12.5))->toBe(12.5);
    expect(Money::toFloat(12))->toBe(12.0);
    expect(Money::toFloat('12.50'))->toBe(12.5);
    expect(Money::toFloat('-4.25'))->toBe(-4.25);
});

test('money converts formatted values from the money mask', function () {
    expect(Money::toFloat('1,234.56'))->toBe(1234.56);
    expect(Money::toFloat('$1,234.56'))->toBe(1234.56);
    expect(Money::toFloat('1 234.56'))->toBe(1234.56);
});

test('money treats empty and unparseable values as zero', function () {
    expect(Money::toFloat(null))->toBe(0.0);
    expect(Money::toFloat(''))->toBe(0.0);
    expect(Money::toFloat('abc'))->toBe(0.0);
});

test('money converts to integer cents', function () {
    expect(Money::toInteger('1,234.56'))->toBe(123456);
    expect(Money::toInteger('$1,234.56'))->toBe(123456);
    expect(Money::toInteger(99.99))->toBe(9999);
    expect(Money::toInteger(0))->toBe(0);
});

test('money returns null for empty values', function () {
    expect(Money::toInteger(null))->toBeNull();
    expect(Money::toInteger(''))->toBeNull();
});
