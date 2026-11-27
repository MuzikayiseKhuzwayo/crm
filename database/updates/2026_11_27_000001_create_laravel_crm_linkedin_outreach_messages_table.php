<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        if (! Schema::hasTable($prefix.'linkedin_outreach_messages')) {
            Schema::create($prefix.'linkedin_outreach_messages', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('team_id')->nullable()->index('crm_lom_team_id_idx');
                $table->unsignedBigInteger('linkedin_campaign_id')->index('crm_lom_campaign_id_idx');
                $table->unsignedBigInteger('linkedin_campaign_recipient_id')->index('crm_lom_recipient_id_idx');
                $table->string('direction', 20)->index('crm_lom_direction_idx');
                $table->unsignedTinyInteger('step')->nullable();
                $table->string('source', 32)->default('worker');
                $table->text('body_snippet');
                $table->char('content_hash', 64);
                $table->timestamp('occurred_at')->index('crm_lom_occurred_at_idx');
                $table->timestamps();

                $table->unique(
                    ['linkedin_campaign_recipient_id', 'direction', 'content_hash'],
                    'crm_lom_recip_dir_hash_uq'
                );

                $table->foreign('linkedin_campaign_id', 'crm_lom_campaign_id_fk')
                    ->references('id')
                    ->on($prefix.'linkedin_campaigns')
                    ->cascadeOnDelete();

                $table->foreign('linkedin_campaign_recipient_id', 'crm_lom_recipient_id_fk')
                    ->references('id')
                    ->on($prefix.'linkedin_campaign_recipients')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        Schema::dropIfExists($prefix.'linkedin_outreach_messages');
    }
};
