<?php

namespace VentureDrake\LaravelCrm\Livewire;

use Livewire\Component;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\TaxRate;
use VentureDrake\LaravelCrm\Support\Money;

class ModelProducts extends Component
{
    public ?string $creating = null;

    public $model = null;

    public ?string $from;

    public array $products = [];

    public $sub_total = 0;

    public $discount = 0;

    public $tax = 0;

    public $adjustment = 0;

    public $total = 0;

    public bool $dynamicProducts = false;

    public bool $showCreateProduct = false;

    protected $listeners = [
        'product-created' => '$refresh',
    ];

    public function mount()
    {
        if (! $this->model) {
            $this->add();
        } else {
            switch (class_basename($this->model)) {
                case 'Quote':
                    foreach ($this->model->quoteProducts as $quoteProduct) {
                        $this->products[] = [
                            'quote_product_id' => $quoteProduct->id,
                            'id' => $quoteProduct->product_id,
                            'name' => $quoteProduct->product->name,
                            'quantity' => $quoteProduct->quantity,
                            'unit_price' => $quoteProduct->price / 100,
                            'tax_rate' => $quoteProduct->tax_rate,
                            'tax_amount' => $quoteProduct->tax_amount / 100,
                            'amount' => $quoteProduct->amount / 100,
                            'comments' => $quoteProduct->comments,
                        ];
                    }

                    break;

                case 'Order':
                    foreach ($this->model->orderProducts as $orderProduct) {
                        if ($this->creating == 'Invoice' && $this->from == 'Order') {
                            $quantities = [];
                            $quantityRemaining = $orderProduct->quantity;

                            foreach ($this->model->invoices as $invoice) {
                                if ($invoiceProduct = $invoice->invoiceLines()->where('order_product_id', $orderProduct->id)->first()) {
                                    $quantityRemaining -= $invoiceProduct->quantity;
                                }
                            }

                            for ($i = 0; $i <= $quantityRemaining; $i++) {
                                $quantities[] = [
                                    'id' => $i,
                                    'name' => $i,
                                ];
                            }
                        } elseif ($this->creating == 'Delivery' && $this->from == 'Order') {
                            $quantities = [];
                            $quantityRemaining = $orderProduct->quantity;

                            foreach ($this->model->deliveries as $delivery) {
                                if ($deliveryProduct = $delivery->deliveryProducts()->where('order_product_id', $orderProduct->id)->first()) {
                                    $quantityRemaining -= $deliveryProduct->quantity;
                                }
                            }

                            for ($i = 0; $i <= $quantityRemaining; $i++) {
                                $quantities[] = [
                                    'id' => $i,
                                    'name' => $i,
                                ];
                            }
                        } elseif ($this->creating == 'PurchaseOrder' && $this->from == 'Order') {
                            $quantityRemaining = $orderProduct->quantity;

                            /*foreach ($this->model->purchaseOrders as $purchaseOrder) {
                                 if ($purchaseOrderProduct = $purchaseOrder->purchaseOrderLines()->where('order_product_id', $orderProduct->id)->first()) {
                                     $quantityRemaining -= $purchaseOrderProduct->quantity;
                                 }
                            }*/
                        }

                        $this->products[] = [
                            'order_product_id' => $orderProduct->id,
                            'id' => $orderProduct->product_id,
                            'name' => $orderProduct->product->name,
                            'quantities' => $quantities ?? [],
                            'quantity' => $quantityRemaining ?? $orderProduct->quantity,
                            'unit_price' => $orderProduct->price / 100,
                            'tax_rate' => $orderProduct->tax_rate,
                            'tax_amount' => $orderProduct->tax_amount / 100,
                            'amount' => $orderProduct->amount / 100,
                            'comments' => $orderProduct->comments,
                        ];
                    }
                    break;

                case 'Invoice':
                    foreach ($this->model->invoiceLines as $invoiceLine) {
                        $this->products[] = [
                            'invoice_line_id' => $invoiceLine->id,
                            'id' => $invoiceLine->product_id,
                            'name' => $invoiceLine->product->name,
                            'quantity' => $invoiceLine->quantity,
                            'unit_price' => $invoiceLine->price / 100,
                            'tax_rate' => $invoiceLine->tax_rate,
                            'tax_amount' => $invoiceLine->tax_amount / 100,
                            'amount' => $invoiceLine->amount / 100,
                            'comments' => $invoiceLine->comments,
                        ];
                    }
                    break;

                case 'Delivery':
                    foreach ($this->model->deliveryProducts as $deliveryProduct) {
                        $this->products[] = [
                            'delivery_product_id' => $deliveryProduct->id,
                            'id' => $deliveryProduct->orderProduct->product_id,
                            'name' => $deliveryProduct->orderProduct->product->name,
                            'quantity' => $deliveryProduct->quantity,
                        ];
                    }

                    break;

                case 'PurchaseOrder':
                    foreach ($this->model->purchaseOrderLines as $purchaseOrderLine) {
                        $this->products[] = [
                            'purchase_order_line_id' => $purchaseOrderLine->id,
                            'id' => $purchaseOrderLine->product_id,
                            'name' => $purchaseOrderLine->name,
                            'quantity' => $purchaseOrderLine->quantity,
                            'unit_price' => $purchaseOrderLine->price / 100,
                            'tax_rate' => $purchaseOrderLine->tax_rate,
                            'tax_amount' => $purchaseOrderLine->tax_amount / 100,
                            'amount' => $purchaseOrderLine->amount / 100,
                            'comments' => $purchaseOrderLine->comments,
                        ];
                    }
                    break;
            }

            $this->recalculateTotals();

            $this->dispatchProductsUpdated();
        }

        $this->dynamicProducts = (app('laravel-crm.settings')->get('dynamic_products')) ? true : false;
    }

    public function updatedProducts($value, $key)
    {
        $updating = explode('.', $key);

        // Anything shorter than "{index}.{attribute}" replaces rows wholesale rather
        // than editing one, so there is no single line to recalculate - the totals are
        // still summed from whatever the rows now hold.
        if (count($updating) > 1) {
            $index = $updating[0];

            if ($updating[1] == 'id') {
                if ($product = Product::find($value)) {
                    $this->products[$index]['unit_price'] = (($product->getDefaultPrice()->unit_price ?? 0) / 100);

                    $this->recalculateProduct($index, $product);
                }
            } elseif ($product = Product::find($this->products[$index]['id'] ?? null)) {
                $this->recalculateProduct($index, $product);
            }
        }

        $this->recalculateTotals();

        $this->dispatchProductsUpdated();
    }

    /**
     * Recalculate a single line from its unit price, quantity and tax rate.
     */
    protected function recalculateProduct($index, Product $product): void
    {
        $taxRate = $this->resolveTaxRate($product);
        $quantity = (int) ($this->products[$index]['quantity'] ?? 1);
        $unitPrice = Money::toFloat($this->products[$index]['unit_price'] ?? 0);

        $this->products[$index]['tax_rate'] = $taxRate;
        $this->products[$index]['tax_amount'] = round(($unitPrice * $quantity) * ($taxRate / 100), 2);
        $this->products[$index]['amount'] = round($unitPrice * $quantity, 2);
    }

    protected function resolveTaxRate(Product $product): float
    {
        if ($product->taxRate) {
            return (float) $product->taxRate->rate;
        }

        if ($product->tax_rate) {
            return (float) $product->tax_rate;
        }

        if ($defaultTaxRate = TaxRate::where('default', 1)->first()) {
            return (float) $defaultTaxRate->rate;
        }

        return (float) app('laravel-crm.settings')->get('tax_rate', 0);
    }

    /**
     * Sum the totals from the lines themselves.
     *
     * The line values come back from the masked inputs as formatted strings
     * ("14,000.00"), so they are normalised rather than cast.
     */
    protected function recalculateTotals(): void
    {
        $subTotal = 0;
        $tax = 0;

        foreach ($this->products as $product) {
            $subTotal += Money::toFloat($product['amount'] ?? 0);
            $tax += Money::toFloat($product['tax_amount'] ?? 0);
        }

        $this->sub_total = round($subTotal, 2);
        $this->tax = round($tax, 2);
        $this->total = round($subTotal + $tax, 2);
    }

    protected function dispatchProductsUpdated(): void
    {
        $this->dispatch('model-products-updated', products: $this->products, sub_total: $this->sub_total, tax: $this->tax, total: $this->total);
    }

    public function add()
    {
        $this->products[] = [
            'id' => null,
            'name' => null,
            'quantity' => 1,
            'unit_price' => null,
            'tax_rate' => null,
            'tax_amount' => null,
            'amount' => null,
            'comments' => null,
        ];

        $this->dispatchProductsUpdated();
    }

    public function remove($index)
    {
        unset($this->products[$index]);
        $this->products = array_values($this->products);

        $this->recalculateTotals();

        $this->dispatchProductsUpdated();
    }

    public function render()
    {
        return view('laravel-crm::livewire.model-products');
    }
}
