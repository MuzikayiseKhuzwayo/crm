<?php

use VentureDrake\LaravelCrm\Models\Address;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\DeliveryProduct;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\InvoiceLine;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\PurchaseOrderLine;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;
use VentureDrake\LaravelCrm\Support\PdfSampleData;

test('person() returns an unsaved Person with first_name and last_name', function () {
    $person = PdfSampleData::person();

    expect($person)->toBeInstanceOf(Person::class)
        ->and($person->exists)->toBeFalse()
        ->and($person->first_name)->toBe('Jordan')
        ->and($person->last_name)->toBe('Sample');
});

test('organization() returns an unsaved Organization with name', function () {
    $organization = PdfSampleData::organization();

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->exists)->toBeFalse()
        ->and($organization->name)->toBe('Acme Sample Co.');
});

test('address() returns an unsaved Address with line1 city state code country', function () {
    $address = PdfSampleData::address();

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->exists)->toBeFalse()
        ->and($address->line1)->toBe('1 Sample Street')
        ->and($address->city)->toBe('Sydney')
        ->and($address->state)->toBe('NSW')
        ->and($address->code)->toBe('2000')
        ->and($address->country)->toBe('AU');
});

test('invoice() returns an unsaved Invoice with 3 line items + person + organization + address', function () {
    $invoice = PdfSampleData::invoice();

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->exists)->toBeFalse()
        ->and($invoice->invoice_id)->toBe('INV-SAMPLE')
        ->and($invoice->reference)->toBe('REF-INV-001')
        ->and($invoice->currency)->toBe('USD')
        ->and($invoice->person)->toBeInstanceOf(Person::class)
        ->and($invoice->organization)->toBeInstanceOf(Organization::class)
        ->and($invoice->address)->toBeInstanceOf(Address::class)
        ->and($invoice->invoiceLines)->toHaveCount(3);

    foreach ($invoice->invoiceLines as $line) {
        expect($line)->toBeInstanceOf(InvoiceLine::class)
            ->and($line->exists)->toBeFalse();
    }
});

test('order() returns an unsaved Order with 3 line items + person + organization + address', function () {
    $order = PdfSampleData::order();

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->exists)->toBeFalse()
        ->and($order->order_id)->toBe('ORD-SAMPLE')
        ->and($order->currency)->toBe('USD')
        ->and($order->person)->toBeInstanceOf(Person::class)
        ->and($order->organization)->toBeInstanceOf(Organization::class)
        ->and($order->address)->toBeInstanceOf(Address::class)
        ->and($order->orderProducts)->toHaveCount(3);

    foreach ($order->orderProducts as $product) {
        expect($product)->toBeInstanceOf(OrderProduct::class)
            ->and($product->exists)->toBeFalse();
    }
});

test('purchaseOrder() returns an unsaved PurchaseOrder with 3 lines + person + organization + address', function () {
    $purchaseOrder = PdfSampleData::purchaseOrder();

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($purchaseOrder->exists)->toBeFalse()
        ->and($purchaseOrder->purchase_order_id)->toBe('PO-SAMPLE')
        ->and($purchaseOrder->currency)->toBe('USD')
        ->and($purchaseOrder->person)->toBeInstanceOf(Person::class)
        ->and($purchaseOrder->organization)->toBeInstanceOf(Organization::class)
        ->and($purchaseOrder->address)->toBeInstanceOf(Address::class)
        ->and($purchaseOrder->purchaseOrderLines)->toHaveCount(3);

    foreach ($purchaseOrder->purchaseOrderLines as $line) {
        expect($line)->toBeInstanceOf(PurchaseOrderLine::class)
            ->and($line->exists)->toBeFalse();
    }
});

test('delivery() returns an unsaved Delivery with 3 line items + person + organization + address', function () {
    $delivery = PdfSampleData::delivery();

    expect($delivery)->toBeInstanceOf(Delivery::class)
        ->and($delivery->exists)->toBeFalse()
        ->and($delivery->delivery_id)->toBe('DEL-SAMPLE')
        ->and($delivery->person)->toBeInstanceOf(Person::class)
        ->and($delivery->organization)->toBeInstanceOf(Organization::class)
        ->and($delivery->address)->toBeInstanceOf(Address::class)
        ->and($delivery->deliveryProducts)->toHaveCount(3);

    foreach ($delivery->deliveryProducts as $product) {
        expect($product)->toBeInstanceOf(DeliveryProduct::class)
            ->and($product->exists)->toBeFalse()
            ->and($product->orderProduct)->toBeInstanceOf(OrderProduct::class)
            ->and($product->orderProduct->exists)->toBeFalse();
    }
});

test('quote() returns an unsaved Quote with 3 line items + person + organization + address', function () {
    $quote = PdfSampleData::quote();

    expect($quote)->toBeInstanceOf(Quote::class)
        ->and($quote->exists)->toBeFalse()
        ->and($quote->quote_id)->toBe('QT-SAMPLE')
        ->and($quote->title)->toBe('Sample Quote')
        ->and($quote->currency)->toBe('USD')
        ->and($quote->person)->toBeInstanceOf(Person::class)
        ->and($quote->organization)->toBeInstanceOf(Organization::class)
        ->and($quote->address)->toBeInstanceOf(Address::class)
        ->and($quote->quoteProducts)->toHaveCount(3);

    foreach ($quote->quoteProducts as $product) {
        expect($product)->toBeInstanceOf(QuoteProduct::class)
            ->and($product->exists)->toBeFalse();
    }
});

test('sample data methods never persist rows to the database', function () {
    $invoiceCountBefore = Invoice::query()->count();
    $orderCountBefore = Order::query()->count();
    $quoteCountBefore = Quote::query()->count();
    $personCountBefore = Person::query()->count();
    $organizationCountBefore = Organization::query()->count();

    PdfSampleData::invoice();
    PdfSampleData::order();
    PdfSampleData::purchaseOrder();
    PdfSampleData::delivery();
    PdfSampleData::quote();

    expect(Invoice::query()->count())->toBe($invoiceCountBefore)
        ->and(Order::query()->count())->toBe($orderCountBefore)
        ->and(Quote::query()->count())->toBe($quoteCountBefore)
        ->and(Person::query()->count())->toBe($personCountBefore)
        ->and(Organization::query()->count())->toBe($organizationCountBefore);
});
