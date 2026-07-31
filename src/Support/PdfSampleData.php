<?php

namespace VentureDrake\LaravelCrm\Support;

use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Models\Address;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\DeliveryProduct;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\InvoiceLine;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\PurchaseOrderLine;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;

/**
 * Fabricates in-memory model instances suitable for rendering preview PDFs.
 *
 * Every model returned is UNSAVED — no DB writes ever fire. Callers can
 * pass the resulting model straight into a Blade view (via the same
 * mustache expressions the real PDF templates use) to preview a template
 * without touching real data. Relations are hydrated via `setRelation(...)`
 * so `$invoice->person`, `$invoice->invoiceLines`, etc. all resolve to the
 * fabricated in-memory data.
 */
class PdfSampleData
{
    /**
     * A fabricated Person suitable for use as the contact on any doc type.
     */
    public static function person(): Person
    {
        $person = new Person;
        $person->first_name = 'Jordan';
        $person->last_name = 'Sample';

        return $person;
    }

    /**
     * A fabricated Organization suitable for use as the client on any doc.
     */
    public static function organization(): Organization
    {
        $organization = new Organization;
        $organization->name = 'Acme Sample Co.';
        $organization->vat_number = 'VAT-000-SAMPLE';

        return $organization;
    }

    /**
     * A fabricated Product for use as the line-item product relation.
     * Templates render `$line->product->name`, so every fabricated line
     * needs a Product attached via setRelation to keep previews non-blank.
     */
    public static function product(string $name = 'Sample product'): Product
    {
        $product = new Product;
        $product->id = 1;
        $product->name = $name;

        return $product;
    }

    /**
     * A fabricated Address suitable for use as billing/shipping on any doc.
     */
    public static function address(): Address
    {
        $address = new Address;
        $address->line1 = '1 Sample Street';
        $address->line2 = 'Suite 101';
        $address->city = 'Sydney';
        $address->state = 'NSW';
        $address->code = '2000';
        $address->country = 'AU';

        return $address;
    }

    /**
     * A fabricated Invoice with 3 line items and Person/Organization/Address.
     */
    public static function invoice(): Invoice
    {
        $invoice = new Invoice;
        $invoice->invoice_id = 'INV-SAMPLE';
        $invoice->reference = 'REF-INV-001';
        $invoice->issue_date = now();
        $invoice->due_date = now()->addDays(30);
        $invoice->terms = 'Net 30';
        $invoice->currency = 'USD';
        $invoice->subtotal = 2700;
        $invoice->tax = 270;
        $invoice->total = 2970;
        $invoice->amount_paid = 0;
        $invoice->amount_due = 2970;

        $lines = new Collection;
        foreach (self::lineItemSeeds() as $seed) {
            $line = new InvoiceLine;
            $line->name = $seed['name'];
            $line->description = $seed['description'];
            $line->comments = $seed['description'];
            $line->quantity = $seed['quantity'];
            $line->price = $seed['price'];
            $line->tax_amount = $seed['tax_amount'];
            $line->amount = $seed['amount'];
            $line->currency = 'USD';
            $line->product_id = 1;
            $line->setRelation('product', self::product($seed['name']));
            $lines->push($line);
        }

        $invoice->setRelation('person', self::person());
        $invoice->setRelation('organization', self::organization());
        $invoice->setRelation('address', self::address());
        $invoice->setRelation('invoiceLines', $lines);

        return $invoice;
    }

    /**
     * A fabricated Order with 3 line items and Person/Organization/Address.
     */
    public static function order(): Order
    {
        $order = new Order;
        $order->order_id = 'ORD-SAMPLE';
        $order->reference = 'REF-ORD-001';
        $order->currency = 'USD';
        $order->subtotal = 2700;
        $order->discount = 0;
        $order->tax = 270;
        $order->adjustments = 0;
        $order->total = 2970;

        $products = new Collection;
        foreach (self::lineItemSeeds() as $seed) {
            $product = new OrderProduct;
            $product->name = $seed['name'];
            $product->comments = $seed['description'];
            $product->quantity = $seed['quantity'];
            $product->price = $seed['price'];
            $product->amount = $seed['amount'];
            $product->currency = 'USD';
            $product->product_id = 1;
            $product->setRelation('product', self::product($seed['name']));
            $products->push($product);
        }

        $order->setRelation('person', self::person());
        $order->setRelation('organization', self::organization());
        $order->setRelation('address', self::address());
        $order->setRelation('orderProducts', $products);

        return $order;
    }

    /**
     * A fabricated PurchaseOrder with 3 lines and Person/Organization/Address.
     */
    public static function purchaseOrder(): PurchaseOrder
    {
        $purchaseOrder = new PurchaseOrder;
        $purchaseOrder->purchase_order_id = 'PO-SAMPLE';
        $purchaseOrder->reference = 'REF-PO-001';
        $purchaseOrder->issue_date = now();
        $purchaseOrder->delivery_date = now()->addDays(14);
        $purchaseOrder->currency = 'USD';
        $purchaseOrder->subtotal = 2700;
        $purchaseOrder->tax = 270;
        $purchaseOrder->total = 2970;

        $lines = new Collection;
        foreach (self::lineItemSeeds() as $seed) {
            $line = new PurchaseOrderLine;
            $line->name = $seed['name'];
            $line->description = $seed['description'];
            $line->comments = $seed['description'];
            $line->quantity = $seed['quantity'];
            $line->price = $seed['price'];
            $line->tax_amount = $seed['tax_amount'];
            $line->amount = $seed['amount'];
            $line->currency = 'USD';
            $line->product_id = 1;
            $line->setRelation('product', self::product($seed['name']));
            $lines->push($line);
        }

        $purchaseOrder->setRelation('person', self::person());
        $purchaseOrder->setRelation('organization', self::organization());
        $purchaseOrder->setRelation('address', self::address());
        $purchaseOrder->setRelation('purchaseOrderLines', $lines);

        return $purchaseOrder;
    }

    /**
     * A fabricated Delivery with 3 line items and Person/Organization/Address.
     */
    public static function delivery(): Delivery
    {
        $delivery = new Delivery;
        $delivery->delivery_id = 'DEL-SAMPLE';
        $delivery->reference = 'REF-DEL-001';
        $delivery->delivery_expected = now()->addDays(3);

        $products = new Collection;
        foreach (self::lineItemSeeds() as $seed) {
            $orderProduct = new OrderProduct;
            $orderProduct->name = $seed['name'];
            $orderProduct->comments = $seed['description'];
            $orderProduct->quantity = $seed['quantity'];
            $orderProduct->price = $seed['price'];
            $orderProduct->amount = $seed['amount'];
            $orderProduct->currency = 'USD';
            $orderProduct->product_id = 1;
            $orderProduct->setRelation('product', self::product($seed['name']));

            $deliveryProduct = new DeliveryProduct;
            $deliveryProduct->quantity = $seed['quantity'];
            $deliveryProduct->setRelation('orderProduct', $orderProduct);
            $products->push($deliveryProduct);
        }

        $delivery->setRelation('person', self::person());
        $delivery->setRelation('organization', self::organization());
        $delivery->setRelation('address', self::address());
        $delivery->setRelation('deliveryProducts', $products);

        return $delivery;
    }

    /**
     * A fabricated Quote with 3 line items and Person/Organization/Address.
     */
    public static function quote(): Quote
    {
        $quote = new Quote;
        $quote->quote_id = 'QT-SAMPLE';
        $quote->title = 'Sample Quote';
        $quote->reference = 'REF-QT-001';
        $quote->issue_at = now();
        $quote->expire_at = now()->addDays(30);
        $quote->terms = 'Valid for 30 days';
        $quote->currency = 'USD';
        $quote->subtotal = 2700;
        $quote->discount = 0;
        $quote->tax = 270;
        $quote->adjustments = 0;
        $quote->total = 2970;

        $products = new Collection;
        foreach (self::lineItemSeeds() as $seed) {
            $product = new QuoteProduct;
            $product->name = $seed['name'];
            $product->comments = $seed['description'];
            $product->quantity = $seed['quantity'];
            $product->price = $seed['price'];
            $product->amount = $seed['amount'];
            $product->currency = 'USD';
            $product->product_id = 1;
            $product->setRelation('product', self::product($seed['name']));
            $products->push($product);
        }

        $quote->setRelation('person', self::person());
        $quote->setRelation('organization', self::organization());
        $quote->setRelation('address', self::address());
        $quote->setRelation('quoteProducts', $products);

        return $quote;
    }

    /**
     * The 3 fake line-item rows every doc type is populated from.
     *
     * Prices, tax amounts, and totals are expressed in dollars — the model
     * setters (setPriceAttribute / setAmountAttribute / setTaxAmountAttribute)
     * multiply by 100 to store as cents, so the on-model values round-trip
     * through the money() helper for display.
     *
     * @return array<int, array{name:string, description:string, quantity:int, price:int, tax_amount:int, amount:int}>
     */
    protected static function lineItemSeeds(): array
    {
        return [
            [
                'name' => 'Sample product A',
                'description' => 'First fabricated line item — long enough to exercise wrapping.',
                'quantity' => 2,
                'price' => 500,
                'tax_amount' => 100,
                'amount' => 1000,
            ],
            [
                'name' => 'Sample product B',
                'description' => 'Second fabricated line item.',
                'quantity' => 1,
                'price' => 750,
                'tax_amount' => 75,
                'amount' => 750,
            ],
            [
                'name' => 'Sample service',
                'description' => 'Third fabricated line item — a service instead of a product.',
                // Keep price an integer: the money mutators multiply by 100,
                // and money() reads an int as minor units but a float as a
                // major amount — so a decimal price here renders as
                // $31,667.00 instead of $316.67. 5 x 190 keeps the row
                // self-consistent and the 950 amount summing into the 2700
                // subtotal.
                'quantity' => 5,
                'price' => 190,
                'tax_amount' => 95,
                'amount' => 950,
            ],
        ];
    }
}
