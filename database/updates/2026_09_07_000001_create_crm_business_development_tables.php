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

        // Add partner_id to deals if missing
        if (Schema::hasTable($prefix.'deals') && ! Schema::hasColumn($prefix.'deals', 'partner_id')) {
            Schema::table($prefix.'deals', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')->nullable()->index();
            });
        }

        // 1. Contracts Table
        if (! Schema::hasTable($prefix.'contracts')) {
            Schema::create($prefix.'contracts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->index()->nullable();
                $table->unsignedBigInteger('deal_id')->index();
                $table->unsignedBigInteger('organization_id')->index()->nullable();
                $table->string('contract_type')->default('direct');
                $table->integer('term_months')->default(12);
                $table->string('payment_terms')->nullable();
                $table->string('sla_commitment_level')->default('standard');
                $table->boolean('sla_penalty_clause')->default(false);
                $table->decimal('partner_rev_share_percent', 5, 2)->default(0.00);
                $table->decimal('annual_price_escalation_percent', 5, 2)->default(0.00);
                $table->bigInteger('minimum_commitment_amount')->default(0);
                $table->decimal('bespoke_work_ratio', 5, 2)->default(0.00);
                $table->boolean('is_referenceable')->default(false);
                $table->boolean('is_design_partner')->default(false);
                $table->timestamp('kickoff_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('renewed_at')->nullable();
                $table->string('renewal_status')->default('pending');
                $table->unsignedBigInteger('user_created_id')->nullable();
                $table->unsignedBigInteger('user_updated_id')->nullable();
                $table->unsignedBigInteger('user_deleted_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Handoff Gates Table
        if (! Schema::hasTable($prefix.'handoff_gates')) {
            Schema::create($prefix.'handoff_gates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->index()->nullable();
                $table->unsignedBigInteger('deal_id')->index();
                $table->string('gate_type'); // product_capability, operational_capacity, financial_margin, legal_compliance
                $table->string('status')->default('pending'); // pending, approved, rejected, waived
                $table->unsignedBigInteger('cleared_by_user_id')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('user_created_id')->nullable();
                $table->unsignedBigInteger('user_updated_id')->nullable();
                $table->timestamps();
            });
        }

        // 3. Partner Profiles Table
        if (! Schema::hasTable($prefix.'partner_profiles')) {
            Schema::create($prefix.'partner_profiles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->index()->nullable();
                $table->unsignedBigInteger('organization_id')->index();
                $table->string('partner_tier')->default('referral'); // si, var, distributor, referral, technology
                $table->string('status')->default('prospect'); // prospect, onboarding, active, dormant, churned
                $table->string('region_code')->nullable();
                $table->timestamp('recruited_at')->nullable();
                $table->timestamp('first_sale_at')->nullable();
                $table->timestamp('last_deal_at')->nullable();
                $table->bigInteger('total_sourced_pipeline_amount')->default(0);
                $table->unsignedBigInteger('user_created_id')->nullable();
                $table->unsignedBigInteger('user_updated_id')->nullable();
                $table->unsignedBigInteger('user_deleted_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 4. Deal Derisking Table
        if (! Schema::hasTable($prefix.'deal_derisking')) {
            Schema::create($prefix.'deal_derisking', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->index()->nullable();
                $table->unsignedBigInteger('deal_id')->index();
                $table->unsignedBigInteger('competitor_id')->nullable();
                $table->text('problem_statement')->nullable();
                $table->text('pitfalls_identified')->nullable();
                $table->text('unique_insight')->nullable();
                $table->text('execution_plan')->nullable();
                $table->boolean('commercial_thesis_validated')->nullable();
                $table->timestamp('loi_signed_at')->nullable();
                $table->timestamp('pilot_converted_at')->nullable();
                $table->unsignedBigInteger('user_created_id')->nullable();
                $table->unsignedBigInteger('user_updated_id')->nullable();
                $table->timestamps();
            });
        }

        // 5. Telemetry Events Table
        if (! Schema::hasTable($prefix.'telemetry_events')) {
            Schema::create($prefix.'telemetry_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->unsignedBigInteger('team_id')->index()->nullable();
                $table->string('event_name')->index();
                $table->string('entity_type');
                $table->string('entity_id')->index();
                $table->string('ansoff_quadrant')->index(); // market_penetration, product_development, market_development, diversification
                $table->string('pirate_stage')->index(); // acquisition, activation, retention, revenue, referral
                $table->string('metric_key')->index();
                $table->decimal('metric_value', 15, 4)->default(0.0000);
                $table->json('payload')->nullable();
                $table->timestamp('recorded_at')->index();
                $table->timestamps();
            });
        }

        // 6. Processing Performance Logs Table (Dubstrata SYS-001 Checkpoint)
        if (! Schema::hasTable($prefix.'processing_performance_logs')) {
            Schema::create($prefix.'processing_performance_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id')->index();
                $table->string('trace_id')->index();
                $table->string('stage')->index(); // ingestion, validation, gate_check, db_write, graph_sync
                $table->decimal('latency_ms', 10, 3);
                $table->string('status');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
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

        Schema::dropIfExists($prefix.'processing_performance_logs');
        Schema::dropIfExists($prefix.'telemetry_events');
        Schema::dropIfExists($prefix.'deal_derisking');
        Schema::dropIfExists($prefix.'partner_profiles');
        Schema::dropIfExists($prefix.'handoff_gates');
        Schema::dropIfExists($prefix.'contracts');
    }
};
