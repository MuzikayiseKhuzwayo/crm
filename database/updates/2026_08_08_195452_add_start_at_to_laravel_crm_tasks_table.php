<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'tasks')) {
            return;
        }

        Schema::table(config('laravel-crm.db_table_prefix').'tasks', function (Blueprint $table) {
            if (! Schema::hasColumn(config('laravel-crm.db_table_prefix').'tasks', 'start_at')) {
                $table->datetime('start_at')->after('description')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'tasks')) {
            return;
        }

        Schema::table(config('laravel-crm.db_table_prefix').'tasks', function (Blueprint $table) {
            if (Schema::hasColumn(config('laravel-crm.db_table_prefix').'tasks', 'start_at')) {
                $table->dropColumn('start_at');
            }
        });
    }
};
