<?php

namespace VentureDrake\LaravelCrm\Traits;

use VentureDrake\LaravelCrm\Support\Quantity;

/**
 * Line item quantities are stored as decimal(15,3) so a product can be sold
 * by weight or volume - 3.5 Kg, 0.25 L.
 *
 * A decimal column reads back as the string "2.000" on MySQL and Postgres
 * (SQLite hands back an int), which would have every `{{ $line->quantity }}`
 * in the templates start printing "2.000" on the two production drivers only.
 * Casting to float makes all three agree and renders 2.0 as "2" and 3.5 as
 * "3.5".
 *
 * The cast is merged in `initialize...()` - called from Eloquent's
 * constructor - rather than declared as a trait `$casts` property, so a model
 * using this trait is still free to declare its own `$casts` without a fatal
 * redeclaration.
 */
trait HasDecimalQuantity
{
    public function initializeHasDecimalQuantity(): void
    {
        // Not 'decimal:3' - that cast returns a string, which is the shape
        // this trait exists to avoid.
        $this->mergeCasts(['quantity' => 'float']);
    }

    /**
     * Round on write, so the value read back matches what the column will
     * hold and every `'quantity' => $input` pass-through in the services
     * normalises for free.
     */
    public function setQuantityAttribute($value): void
    {
        $this->attributes['quantity'] = Quantity::toDecimal($value);
    }
}
