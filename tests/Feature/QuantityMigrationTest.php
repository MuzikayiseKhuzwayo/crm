<?php

/*
 * change_quantity_to_decimal_on_laravel_crm_tables - the ALTER that widens
 * the six line item quantity columns for existing installs.
 *
 * Nothing else in the suite runs the published stubs: the tests build their
 * schema from TestSchema, which already declares the new column type. This
 * runs the real migration against tables created the old way, with rows
 * already in them, so a change() that silently drops NULLability or fails on
 * existing data is caught here rather than in a customer's database.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recreate one line item table the way it looked before this change, with an
 * integer quantity, and seed it with the two row shapes that exist in the
 * wild.
 */
function seedLegacyQuantityTable(string $table): void
{
    $name = config('laravel-crm.db_table_prefix').$table;

    Schema::dropIfExists($name);

    Schema::create($name, function (Blueprint $blueprint) {
        $blueprint->bigIncrements('id');
        $blueprint->integer('quantity')->nullable();
    });

    DB::table($name)->insert([
        ['quantity' => 7],
        ['quantity' => null],
    ]);
}

function runQuantityMigration(): void
{
    $migration = require __DIR__.'/../../database/updates/2026_08_08_203806_change_quantity_to_decimal_on_laravel_crm_tables.php';

    $migration->up();
}

test('the migration widens every line item quantity column', function () {
    $tables = [
        'quote_products', 'order_products', 'deal_products',
        'invoice_lines', 'purchase_order_lines', 'delivery_products',
    ];

    foreach ($tables as $table) {
        seedLegacyQuantityTable($table);
    }

    runQuantityMigration();

    foreach ($tables as $table) {
        $name = config('laravel-crm.db_table_prefix').$table;

        // Asserted as "no longer an integer" rather than as a literal type:
        // SQLite reports a decimal column as "numeric", MySQL as "decimal".
        // The value assertion below cannot stand in for this - SQLite's
        // INTEGER affinity happily stores 3.5 as a REAL, so it would pass
        // against an unchanged column while MySQL rounded 3.5 to 4.
        expect(Schema::getColumnType($name, 'quantity'))->not->toBe('integer', "column type on $name");

        DB::table($name)->insert(['quantity' => 3.5]);

        expect(DB::table($name)->orderBy('id')->pluck('quantity')->map(fn ($v) => $v === null ? null : (float) $v)->all())
            ->toBe([7.0, null, 3.5], "rows on $name");
    }
});

test('the migration leaves the column nullable', function () {
    // change() rebuilds the whole column definition from the modifiers given,
    // so omitting ->nullable() would emit NOT NULL and break the null
    // quantities LaravelCrmUpdate backfills against.
    seedLegacyQuantityTable('delivery_products');

    runQuantityMigration();

    $name = config('laravel-crm.db_table_prefix').'delivery_products';

    DB::table($name)->insert(['quantity' => null]);

    expect(DB::table($name)->whereNull('quantity')->count())->toBe(2);
});

test('the migration skips a table that is not installed', function () {
    $name = config('laravel-crm.db_table_prefix').'quote_products';

    Schema::dropIfExists($name);

    runQuantityMigration();

    expect(Schema::hasTable($name))->toBeFalse();
});
