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
        if (Schema::hasTable(config('laravel-crm.db_table_prefix').'user_invitations')) {
            return;
        }

        Schema::create(config('laravel-crm.db_table_prefix').'user_invitations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('external_id');
            $table->string('code', 64)->unique();
            $table->unsignedBigInteger('team_id')->index()->nullable();
            $table->string('email');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreign('role_id')->references('id')->on('roles');
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->foreign('invited_by')->references('id')->on('users');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('laravel-crm.db_table_prefix').'user_invitations');
    }
};
