<?php

namespace VentureDrake\LaravelCrm\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Support\Quantity;

/**
 * Reject a line item quantity larger than the amount still outstanding on
 * the order line it draws down.
 *
 * The Order to Invoice and Order to Delivery forms used to pick quantity
 * from a `<select>` built by an integer loop over the remainder. That
 * dropdown was the only guard against over-invoicing and it lived entirely
 * in the browser; a decimal quantity cannot come from a dropdown, so the
 * cap needs enforcing on submit as well as in ModelProducts::clampQuantity().
 *
 * The remainder is recomputed here from the order line and the records
 * already drawn against it. It deliberately does not read the row's
 * `quantity_max`: that is a public Livewire property, so it round-trips
 * through the browser and a caller can raise it in the payload - a cap
 * taken from it would be no cap at all.
 *
 * A plain `lte:products.*.quantity_max` string rule is not usable here
 * either - validateLte fails spuriously when the compared value is null,
 * which is the common case on a quote or order form where nothing is being
 * drawn down.
 */
class QuantityWithinRemaining implements ValidationRule
{
    /**
     * @param  array  $products  the component's line items, keyed by row index
     * @param  class-string|null  $drawdownModel  the model the lines are written to - InvoiceLine or
     *                                            DeliveryProduct. Null on the forms that draw nothing
     *                                            down (quote, order, purchase order, deal), where the
     *                                            rule is a no-op.
     * @param  string|null  $drawdownRelation  the parent relation on that model, so a line belonging to
     *                                         a soft deleted invoice or delivery does not hold quantity
     *                                         hostage
     * @param  string|null  $drawdownKey  the row key holding the id of the record being edited, whose
     *                                    own quantity must not count against itself
     */
    public function __construct(
        protected array $products = [],
        protected ?string $drawdownModel = null,
        protected ?string $drawdownRelation = null,
        protected ?string $drawdownKey = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $index = explode('.', $attribute)[1] ?? null;
        $row = $this->products[$index] ?? null;

        if (! is_array($row) || $value === null || $value === '') {
            return;
        }

        $max = $this->remaining($row);

        if ($max === null) {
            return;
        }

        if (Quantity::greaterThan($value, $max)) {
            $fail(ucfirst(__('laravel-crm::lang.quantity_exceeds_remaining', [
                'remaining' => Quantity::format($max),
            ])));
        }
    }

    /**
     * What is left on the order line this row draws down, or null when the
     * row draws down nothing and so has no cap.
     */
    protected function remaining(array $row): ?float
    {
        if ($this->drawdownModel === null || empty($row['order_product_id'])) {
            return null;
        }

        if (! $orderProduct = OrderProduct::find($row['order_product_id'])) {
            return null;
        }

        $drawn = $this->drawdownModel::query()
            ->where('order_product_id', $orderProduct->id)
            ->when($this->drawdownRelation, fn ($query, $relation) => $query->whereHas($relation))
            ->when(
                $this->drawdownKey && ! empty($row[$this->drawdownKey]),
                fn ($query) => $query->whereKeyNot($row[$this->drawdownKey])
            )
            ->sum('quantity');

        return max(0.0, Quantity::round(Quantity::toFloat($orderProduct->quantity) - Quantity::toFloat($drawn)));
    }
}
