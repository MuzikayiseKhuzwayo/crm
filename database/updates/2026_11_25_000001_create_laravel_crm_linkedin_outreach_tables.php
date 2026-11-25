<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        if (! Schema::hasTable($prefix.'linkedin_templates')) {
            Schema::create($prefix.'linkedin_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->string('name');
                $table->string('type')->default('connect_note')->index();
                $table->text('body');
                $table->unsignedBigInteger('user_created_id')->nullable();
                $table->unsignedBigInteger('user_updated_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable($prefix.'linkedin_campaigns')) {
            Schema::create($prefix.'linkedin_campaigns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->string('name');
                $table->string('status')->default('draft')->index();
                $table->json('sequence_steps')->nullable();
                $table->unsignedInteger('daily_connect_limit')->default(15);
                $table->unsignedInteger('daily_dm_limit')->default(30);
                $table->unsignedInteger('weekly_connect_limit')->default(80);
                $table->unsignedInteger('min_delay_seconds')->default(180);
                $table->unsignedInteger('max_delay_seconds')->default(420);
                $table->json('active_days')->nullable();
                $table->unsignedTinyInteger('active_start_hour')->default(8);
                $table->unsignedTinyInteger('active_end_hour')->default(18);
                $table->string('timezone')->default('Africa/Johannesburg');
                $table->timestamp('circuit_broken_at')->nullable();
                $table->string('circuit_broken_reason')->nullable();
                $table->timestamp('last_worker_heartbeat_at')->nullable();
                $table->unsignedInteger('total_recipients')->default(0);
                $table->unsignedInteger('connects_sent_count')->default(0);
                $table->unsignedInteger('accepted_count')->default(0);
                $table->unsignedInteger('dms_sent_count')->default(0);
                $table->unsignedInteger('replied_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedBigInteger('user_owner_id')->nullable();
                $table->unsignedBigInteger('user_created_id')->nullable();
                $table->unsignedBigInteger('user_updated_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable($prefix.'linkedin_campaign_recipients')) {
            Schema::create($prefix.'linkedin_campaign_recipients', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->unsignedBigInteger('linkedin_campaign_id')->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedBigInteger('person_id')->nullable()->index();
                $table->string('linkedin_url')->index();
                $table->string('normalized_linkedin_slug')->nullable()->index();
                $table->text('personalized_opener')->nullable();
                $table->string('status')->default('queued')->index();
                $table->unsignedTinyInteger('current_step')->default(0);
                $table->timestamp('next_action_at')->nullable()->index();
                $table->string('lease_token')->nullable()->index();
                $table->timestamp('leased_at')->nullable();
                $table->timestamp('lease_expires_at')->nullable()->index();
                $table->string('worker_id')->nullable();
                $table->unsignedTinyInteger('attempt_count')->default(0);
                $table->string('last_error_code')->nullable();
                $table->text('last_error_message')->nullable();
                $table->timestamp('connect_sent_at')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('last_dm_sent_at')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['linkedin_campaign_id', 'status', 'next_action_at'], 'crm_lcr_campaign_status_next_idx');

                $table->foreign('linkedin_campaign_id', 'crm_lcr_campaign_id_fk')
                    ->references('id')
                    ->on($prefix.'linkedin_campaigns')
                    ->cascadeOnDelete();

                $table->foreign('lead_id', 'crm_lcr_lead_id_fk')
                    ->references('id')
                    ->on($prefix.'leads')
                    ->nullOnDelete();

                $table->foreign('person_id', 'crm_lcr_person_id_fk')
                    ->references('id')
                    ->on($prefix.'people')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable($prefix.'linkedin_outreach_events')) {
            Schema::create($prefix.'linkedin_outreach_events', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->unsignedBigInteger('linkedin_campaign_id')->index('crm_loe_campaign_id_idx');
                $table->unsignedBigInteger('linkedin_campaign_recipient_id')->nullable()->index('crm_loe_recipient_id_idx');
                $table->string('action')->index();
                $table->unsignedTinyInteger('step')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->foreign('linkedin_campaign_id', 'crm_loe_campaign_id_fk')
                    ->references('id')
                    ->on($prefix.'linkedin_campaigns')
                    ->cascadeOnDelete();

                $table->foreign('linkedin_campaign_recipient_id', 'crm_loe_recipient_id_fk')
                    ->references('id')
                    ->on($prefix.'linkedin_campaign_recipients')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        Schema::dropIfExists($prefix.'linkedin_outreach_events');
        Schema::dropIfExists($prefix.'linkedin_campaign_recipients');
        Schema::dropIfExists($prefix.'linkedin_campaigns');
        Schema::dropIfExists($prefix.'linkedin_templates');
    }
};
