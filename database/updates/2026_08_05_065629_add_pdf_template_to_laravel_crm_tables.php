<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables holding a document that can be downloaded as a PDF, in
     * the same order as PdfTemplateRegistry::DOC_TYPES.
     */
    protected $tables = [
        'invoices',
        'orders',
        'purchase_orders',
        'deliveries',
        'quotes',
    ];

    public function up()
    {
        foreach ($this->tables as $table) {
            $name = config('laravel-crm.db_table_prefix').$table;

            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $blueprint) use ($name) {
                if (! Schema::hasColumn($name, 'pdf_template')) {
                    $blueprint->string('pdf_template')->nullable();
                }
            });
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            $name = config('laravel-crm.db_table_prefix').$table;

            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $blueprint) use ($name) {
                if (Schema::hasColumn($name, 'pdf_template')) {
                    $blueprint->dropColumn('pdf_template');
                }
            });
        }
    }
};
