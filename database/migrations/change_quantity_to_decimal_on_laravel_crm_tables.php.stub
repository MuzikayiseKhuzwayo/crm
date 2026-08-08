<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The line item tables whose quantity is entered by hand on a form.
     *
     * decimal(15,3) strictly contains the old INT range, so every existing
     * row widens losslessly and the ALTER cannot fail on data.
     */
    protected $tables = [
        'quote_products',
        'order_products',
        'deal_products',
        'invoice_lines',
        'purchase_order_lines',
        'delivery_products',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->changeQuantity(function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Going back to an integer column truncates any decimal already
     * entered - there is no lossless inverse.
     */
    public function down(): void
    {
        $this->changeQuantity(function (Blueprint $table) {
            $table->integer('quantity')->nullable()->change();
        });
    }

    /**
     * `->nullable()` has to be restated on every change(): the grammars
     * rebuild the whole column definition from the modifiers given, so
     * leaving it off emits NOT NULL. Null quantities exist in the wild -
     * LaravelCrmUpdate backfills deliveryProducts()->whereNull('quantity').
     */
    protected function changeQuantity(Closure $change): void
    {
        foreach ($this->tables as $table) {
            $name = config('laravel-crm.db_table_prefix').$table;

            if (! Schema::hasTable($name) || ! Schema::hasColumn($name, 'quantity')) {
                continue;
            }

            Schema::table($name, $change);
        }
    }
};
