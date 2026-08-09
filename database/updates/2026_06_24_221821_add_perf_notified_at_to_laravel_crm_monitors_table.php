<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'monitors')) {
            return;
        }

        Schema::table(config('laravel-crm.db_table_prefix').'monitors', function (Blueprint $table) {
            if (! Schema::hasColumn(config('laravel-crm.db_table_prefix').'monitors', 'perf_notified_at')) {
                $table->timestamp('perf_notified_at')->nullable()->after('notified_at');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'monitors')) {
            return;
        }

        Schema::table(config('laravel-crm.db_table_prefix').'monitors', function (Blueprint $table) {
            if (Schema::hasColumn(config('laravel-crm.db_table_prefix').'monitors', 'perf_notified_at')) {
                $table->dropColumn('perf_notified_at');
            }
        });
    }
};
