<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kept as its own migration even though the create above now ships both
 * columns: a host that ran the create before those columns were added to it
 * still needs them, and Schema::hasColumn makes this a no-op for everyone else.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'user_invitations')) {
            return;
        }

        Schema::table(config('laravel-crm.db_table_prefix').'user_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn(config('laravel-crm.db_table_prefix').'user_invitations', 'last_sent_at')) {
                $table->timestamp('last_sent_at')->nullable()->after('accepted_at');
            }

            if (! Schema::hasColumn(config('laravel-crm.db_table_prefix').'user_invitations', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'user_invitations')) {
            return;
        }

        Schema::table(config('laravel-crm.db_table_prefix').'user_invitations', function (Blueprint $table) {
            if (Schema::hasColumn(config('laravel-crm.db_table_prefix').'user_invitations', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn(config('laravel-crm.db_table_prefix').'user_invitations', 'last_sent_at')) {
                $table->dropColumn('last_sent_at');
            }
        });
    }
};
