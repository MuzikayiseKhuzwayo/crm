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
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        if (Schema::hasTable($prefix.'leads')) {
            Schema::table($prefix.'leads', function (Blueprint $table) {
                if (! Schema::hasColumn($table->getTable(), 'relay_status')) {
                    $table->string('relay_status')->nullable()->default('standby');
                }
                if (! Schema::hasColumn($table->getTable(), 'relay_order')) {
                    $table->integer('relay_order')->nullable()->default(1);
                }
                if (! Schema::hasColumn($table->getTable(), 'relay_activated_at')) {
                    $table->datetime('relay_activated_at')->nullable();
                }
                if (! Schema::hasColumn($table->getTable(), 'relay_fallen_off_at')) {
                    $table->datetime('relay_fallen_off_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        if (Schema::hasTable($prefix.'leads')) {
            Schema::table($prefix.'leads', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn($table->getTable(), 'relay_status')) {
                    $columns[] = 'relay_status';
                }
                if (Schema::hasColumn($table->getTable(), 'relay_order')) {
                    $columns[] = 'relay_order';
                }
                if (Schema::hasColumn($table->getTable(), 'relay_activated_at')) {
                    $columns[] = 'relay_activated_at';
                }
                if (Schema::hasColumn($table->getTable(), 'relay_fallen_off_at')) {
                    $columns[] = 'relay_fallen_off_at';
                }

                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
