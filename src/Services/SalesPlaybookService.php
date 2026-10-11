<?php

namespace VentureDrake\LaravelCrm\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;

class SalesPlaybookService
{
    public const BRAND_DUBSTRATA = 'dubstrata';

    public const BRAND_TECHFUSION = 'techfusion';

    // Dubstrata Playbook Metadata
    public const PLAYBOOK_ID_DUBSTRATA = 'DUB-REV-SALES-MOTIONS-V2-002';

    public const PLAYBOOK_NAME_DUBSTRATA = 'Sales Motions V2: Institutional Conversational Playbook & LinkedIn Framework';

    public const TARGET_AUDIENCE_DUBSTRATA = 'Systematic Quants, Quantitative Portfolio Managers, Crypto Prop Traders, Chief Risk Officers';

    public const GUIDING_PRINCIPLE_DUBSTRATA = 'Peer Engineer to Peer Quant';

    // Techfusion Automata Playbook Metadata
    public const PLAYBOOK_ID_TECHFUSION = 'TFA-REV-SALES-MOTIONS-V2-001';

    public const PLAYBOOK_NAME_TECHFUSION = 'Sales Motions V2: Technical Conversational Playbook & Operator Acquisition Framework';

    public const TARGET_AUDIENCE_TECHFUSION = 'Founders, Chief Technology Officers (CTOs), Chief Operating Officers (COOs), Heads of Operations, Agency Directors, B2B Growth Leaders';

    public const GUIDING_PRINCIPLE_TECHFUSION = 'Senior Systems Engineer to Operator / Technical Founder';

    // Backwards-compatibility aliases
    public const PLAYBOOK_ID = self::PLAYBOOK_ID_DUBSTRATA;

    public const PLAYBOOK_NAME = self::PLAYBOOK_NAME_DUBSTRATA;

    public const TARGET_AUDIENCE = self::TARGET_AUDIENCE_DUBSTRATA;

    public const GUIDING_PRINCIPLE = self::GUIDING_PRINCIPLE_DUBSTRATA;

    /**
     * Get templates for a specific brand or combined repository.
     */
    public function getTemplates(?string $brand = null): array
    {
        if ($brand === self::BRAND_DUBSTRATA) {
            return $this->getDubstrataTemplates();
        }

        if ($brand === self::BRAND_TECHFUSION) {
            return $this->getTechfusionTemplates();
        }

        return array_merge($this->getDubstrataTemplates(), $this->getTechfusionTemplates());
    }

    /**
     * Get templates filtered by brand.
     */
    public function getTemplatesForBrand(string $brand): array
    {
        return $brand === self::BRAND_DUBSTRATA
            ? $this->getDubstrataTemplates()
            : $this->getTechfusionTemplates();
    }

    /**
     * Complete repository of Institutional Sales Motion V2 templates (Dubstrata).
     */
    public function getDubstrataTemplates(): array
    {
        return [
            'connection_angle_a' => [
                'key' => 'connection_angle_a',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'connection',
                'angle_label' => 'Angle A: Crypto Quant Prop & Market Makers',
                'name' => 'LinkedIn Note: Angle A (Crypto Prop / MMs)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Saw your work at {firm_name}. We’ve been working on filtering out on-chain Sybil clusters and wash trading from prediction market CLOBs before feature ingestion. Curious if your desk has looked at prediction data as an orthogonal signal.',
            ],
            'connection_angle_b' => [
                'key' => 'connection_angle_b',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'connection',
                'angle_label' => 'Angle B: Systematic Macro & Multi-Strategy PMs',
                'name' => 'LinkedIn Note: Angle B (Systematic Macro)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Following your work in systematic macro. We’ve been building stationary 16-channel tensors that combine macro event graphs with prediction market microstructure, with strict zero-lookahead bi-temporal stamping. Glad to connect.',
            ],
            'connection_angle_c' => [
                'key' => 'connection_angle_c',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'connection',
                'angle_label' => 'Angle C: Quant Developers & Infrastructure Leads',
                'name' => 'LinkedIn Note: Angle C (Quant Devs / Data Infra)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Saw your focus on data infrastructure at {firm_name}. We recently finished our Flink/Kafka pipeline that packages prediction orderbooks and regulatory gazettes into daily point-in-time Parquet partitions. Always good to connect with other builders.',
            ],
            'scenario_1' => [
                'key' => 'scenario_1',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'conversation',
                'angle_label' => 'Scenario 1: Accepted in Silence (24–48 Hours)',
                'name' => 'Playbook Scenario 1: Accept in Silence (Audit Paper)',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Connected / DM Sent',
                'body' => "Thanks for connecting, {first_name}.\n\nSaw your desk's focus on {focus_area}. We recently finished our out-of-sample forward evaluation on GCP, testing whether prediction market microstructure and causal graph states add orthogonal signal to price models once lookahead bias is eliminated.\n\nWrote up the methodology and data structure here if you're interested: dubstrata.com/research\n\nCurious if your team has explored prediction market data or if the noise/wash-trading has kept you away?",
            ],
            'scenario_2' => [
                'key' => 'scenario_2',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'conversation',
                'angle_label' => 'Scenario 2: Replied with Interest / "What do you provide?"',
                'name' => 'Playbook Scenario 2: Data Engineering Headache & Kit Offer',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Basically, we solve the data engineering headache.\n\nInstead of your team having to build scrapers, deduplicate news, cluster on-chain Sybil wallets, and align timestamps, we package it all into a stationary 16-channel tensor. We stream it via Kafka or dump daily point-in-time Parquet partitions.\n\nWe have a lightweight Jupyter notebook (quant_evaluation_kit.ipynb) and a 30-day sample Parquet partition so quants can inspect the schema and test the information coefficient on their own stack.\n\nHappy to send over the link if you'd like to check it out.",
            ],
            'scenario_3' => [
                'key' => 'scenario_3',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'conversation',
                'angle_label' => 'Scenario 3: When They Ask to See Code / Notebook',
                'name' => 'Playbook Scenario 3: Deliver Evaluation Kit Link',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Here’s the signed download link for the sample partition and the notebook:\nhttps://dubstrata.com/evaluation-kit\n\nIt contains the 16 channels (OFI, stationarized price series via fractional diff, entropy-weighted consensus Ψ_CSI, and graph belief vectors). The notebook also runs the gradient leakage audit so you can verify zero lookahead directly.\n\nLet me know if any questions come up while running it.",
            ],
            'scenario_3_1' => [
                'key' => 'scenario_3_1',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'conversation',
                'angle_label' => 'Scenario 3.1 & 3.2: Post-Evaluation & Canary Shadow Loop',
                'name' => 'Playbook Scenario 3.1 & 3.2: Review Deletion Gain & Deploy Canary Paper-Trader',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Great to see the audit passed on your run, {first_name}.\n\nThree quick observations on your empirical print:\n1. Deletion Gain (+0.011): Confirms genuine causal grounding on Channel 01 (fracdiff price) rather than ungrounded latent overfitting.\n2. Directional Conviction (11.11%): The model is currently flagging sideways volatility clustering with no clear directional edge. In chop zones, it acts as an automated capital brake by staying neutral rather than forcing trades.\n3. Microstructure Weight (83.8%): Orderbook flow (VPIN/OFI) is currently dominating because macro event clocks (Channel 16) are quiet. As scheduled macro announcements approach, weight dynamically reallocates to prediction markets and macro solvency.\n\nMost desks plug this into a 14-day canary shadow-trading loop to observe how it performs when conviction spikes above 60%. Standalone harness (canary_paper_trader.py):\nhttps://dubstrata.com/canary-harness\n\nLet me know if you want to test it against your simulated fills.",
            ],
            'scenario_4' => [
                'key' => 'scenario_4',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'conversation',
                'angle_label' => 'Scenario 4: Handling Mid-Cadence Silence (5–7 Days Later)',
                'name' => 'Playbook Scenario 4: Mid-Cadence Multi-Asset Nudge',
                'due_in_days' => 5,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Hey {first_name} — Quick update on our end: we just expanded our tensor schema from BTC into cross-asset macro coverage (SPY, QQQ, and Crude Oil).\n\nReminded me of our chat. If you'd like to see the updated multi-asset Parquet schema or the notebook, happy to share the link. Hope trading has been going well this week.",
            ],
            'scenario_5' => [
                'key' => 'scenario_5',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'conversation',
                'angle_label' => 'Scenario 5: Graceful Long-Term Disconnect (After 2+ Weeks)',
                'name' => 'Playbook Scenario 5: Graceful Long-Term Disconnect',
                'due_in_days' => 14,
                'max_chars' => null,
                'target_stage' => 'Closed Lost',
                'body' => "Hey {first_name} — I know you're heads-down running strategies and bandwidth is tight, so I'll leave your inbox alone.\n\nI’ll keep you in my network and share our quarterly research dispatches as we publish them. If your desk ever needs clean, point-in-time causal or prediction alt-data feeds down the road, feel free to reach out anytime.\n\nWishing you strong performance this quarter.",
            ],
            'call_transition' => [
                'key' => 'call_transition',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'call',
                'angle_label' => 'Call Transition: Booking 15-Minute Technical Sync',
                'name' => 'Playbook Call Transition: Propose 15-Min Technical Sync',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Call Scheduled',
                'body' => "If you or one of your quant researchers are exploring this, I'm happy to do a quick 15-minute technical sync next week.\n\nI can show you how our Flink SQL pipeline materializes the channels in real time and how other desks ingest the daily Parquet partitions. If there's an alignment, we can spin up sandbox credentials for your research environment.\n\nHow does Wednesday or Thursday afternoon look on your end?",
            ],
            'technical_sync_debrief' => [
                'key' => 'technical_sync_debrief',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'call',
                'angle_label' => 'Debrief: 15-Minute Technical Sync & Canary Demo',
                'name' => 'Playbook Call: 15-Minute Post-Eval Debrief & Canary Demo',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Call Scheduled',
                'body' => "15-Minute Technical Sync Agenda & Talk Track (sales-motions-v2.md Sec 7.1):\n• Min 00–03: Acknowledge & Validate Audit Printouts (Deletion Gain > 0, Stationarity, zero-lookahead on {firm_name}'s stack).\n• Min 03–07: Contextualize Conviction (11.11% chop neutral brake vs >75% directional trend).\n• Min 07–11: Demonstrate Canary Paper-Trader Harness (canary_paper_trader.py, q10 stop, q90 target).\n• Min 11–15: Define 30-Day Sandbox Scope & Close ($15k–$35k).",
            ],
            'sandbox_sow' => [
                'key' => 'sandbox_sow',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'proposal',
                'angle_label' => 'Proposal: Deliver 30-Day Paid Sandbox SOW ($15k–$35k)',
                'name' => 'Playbook SOW: Deliver 30-Day Paid Sandbox SOW ($15k–$35k)',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Proposal Sent',
                'body' => "Deliver 30-Day Paid Evaluation Statement of Work (SOW) ($15,000–$35,000 for {firm_name}):\n• Provision dedicated high-throughput API credentials (120 req/min).\n• Deliver 12 months rolling historical Parquet lake access (dubstrata_btc_5m).\n• Provide sub-second WebSocket stream credentials (WSS /api/v1/ws/tensor).\n• Provision Tier-2 partner access to HRX Terminal (partners.dubstrata.com/terminal) for CRO fiduciary clearance.\n• Note: 100% of Sandbox SOW fee is credited toward the first year of the annual enterprise contract.",
            ],
            'cro_clearance' => [
                'key' => 'cro_clearance',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'proposal',
                'angle_label' => 'CRO Gate: Provision HRX Terminal for Fiduciary Clearance',
                'name' => 'Playbook CRO: Provision HRX Terminal Access for Fiduciary Clearance',
                'due_in_days' => 3,
                'max_chars' => null,
                'target_stage' => 'Proposal Sent',
                'body' => "For {firm_name}'s risk committee and fiduciary sign-off:\nProvision Tier-2 partner access to our HRX Command Center at partners.dubstrata.com/terminal.\nDecomposes every forecast into 4 observable quadrants (Microstructure, Prediction Markets, Causal Graph, Macro Solvency) with cryptographic SHA-256 audit proofs and deletion gain verification (+0.1956).",
            ],
            'production_contract' => [
                'key' => 'production_contract',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'contract',
                'angle_label' => 'Contract: Execute Annual Enterprise Production Contract',
                'name' => 'Playbook Contract: Execute Annual Enterprise Production Contract ($120k–$240k/yr)',
                'due_in_days' => 7,
                'max_chars' => null,
                'target_stage' => 'Closed Won',
                'body' => "Commercial Conversion & Production Contract Execution for {firm_name} (sales-motions-v2.md Sec 7.6):\n• Review Canary Shadow Performance against benchmark (DHR ≥ 65%, PF ≥ 2.0).\n• Select Production Tier: Tier A ($35k-$60k/yr Kafka/Parquet lake), Tier B ($120k/yr FM tensor/API/WebSocket), or Tier C ($240k/yr Enterprise).\n• Execute standard ASC 606 enterprise software license agreement with monthly/quarterly wire terms in USD/USDC.",
            ],
            'onboarding_production' => [
                'key' => 'onboarding_production',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'contract',
                'angle_label' => 'Onboarding: Deploy Dedicated Production Feed & Support',
                'name' => 'Playbook Onboarding: Deploy Dedicated Production Feed & Support',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Closed Won',
                'body' => "Production Onboarding & Infrastructure Provisioning for {firm_name}:\n• Configure production Kafka consumer group and allocate dedicated cloud partition lake.\n• Establish shared private engineering communication channel with Desk Lead at {firm_name}.\n• Schedule monthly quantitative review and model parameter recalibration sync.",
            ],
            'objection_inhouse' => [
                'key' => 'objection_inhouse',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'objection',
                'angle_label' => 'Objection 1: "We build all our data ingestion & models in-house."',
                'name' => 'Playbook Objection: In-House Ingestion (The Fuel)',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => 'Makes complete sense—most systematic funds prefer internal modeling. That’s actually why we don’t sell a black-box model. We deliver "The Fuel." Desks license our Kafka topic or daily Parquet lake so their researchers get stationary, point-in-time causal features without spending 9 months building web scrapers and wallet clustering algorithms. Happy to share our data dictionary if you want to see how we structure the channels.',
            ],
            'objection_wash_trading' => [
                'key' => 'objection_wash_trading',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'objection',
                'angle_label' => 'Objection 2: "Prediction markets (Polymarket) are full of noise & wash trading."',
                'name' => 'Playbook Objection: Sybil Clustering & Shannon Entropy',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => '100% agreed—that\'s the biggest issue with raw prediction APIs. If you take Polymarket prices at face value, you\'re trading on retail sentiment and wash-trading whales. We contract correlated Polygon CTF wallets into single Sybil clusters and weight consensus using Shannon entropy (Ψ_CSI). It turns noisy orderbook sweeps into a clean institutional conviction metric. We detail the exact math in the research report.',
            ],
            'objection_pricing' => [
                'key' => 'objection_pricing',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'objection',
                'angle_label' => 'Objection 3: "How much does it cost?"',
                'name' => 'Playbook Objection: Pricing & 30-Day Sandbox SOW',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Proposal Sent',
                'body' => 'Our annual data streaming contracts start at $35k/year for daily Parquet and Kafka feeds. That said, we don’t bill desks until they’ve verified the signal on their own backtesters. We usually set up a 30-day evaluation sandbox with historical sample partitions so your team can test the Information Coefficient first. Happy to send the evaluation kit over if that’s of interest.',
            ],
            'objection_equities' => [
                'key' => 'objection_equities',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'objection',
                'angle_label' => 'Objection 4: "We don\'t trade crypto. We only trade equities & commodities."',
                'name' => 'Playbook Objection: Cross-Asset Equities & Sovereign Gazettes',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => 'Understood. While our Phase 2 forward audit was conducted on digital assets, our pipeline connects to sovereign gazettes (SEC EDGAR, Federal Register) and commodity logistics graphs, generating tensors for SPY, QQQ, and Crude Oil. If macro event-risk or regulatory shock modeling is relevant to your desk, happy to keep in touch on that front.',
            ],
            'objection_lookahead' => [
                'key' => 'objection_lookahead',
                'brand' => self::BRAND_DUBSTRATA,
                'category' => 'objection',
                'angle_label' => 'Objection 5: "How do you guarantee zero lookahead bias in backtests?"',
                'name' => 'Playbook Objection: Zero Lookahead & Bi-Temporal Stamping',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => 'Every record in our Parquet lake enforces strict bi-temporal stamping: Valid Time (t_v, when the event occurred) and Transaction Time (t_t, when it was ingested into the datastore). In backtesting, queries are hard-filtered so models only observe data where t_t ≤ T_sim. In our live GCP audit, our gradient leakage score at the barrier was 18.20x, well below the 60x leakage threshold. You can verify this directly in the evaluation notebook.',
            ],
        ];
    }

    /**
     * Complete repository of Operator Acquisition Framework V2 templates (Techfusion Automata).
     */
    public function getTechfusionTemplates(): array
    {
        return [
            'tfa_connection_angle_a' => [
                'key' => 'tfa_connection_angle_a',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'connection',
                'angle_label' => 'Angle A: Inbound Sales & Lead Velocity (Founders & Sales Leaders)',
                'name' => 'LinkedIn Note: Angle A (Lead Velocity & Speed-to-Lead)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Noticed your team’s expansion at {firm_name}. We build sub-45s speed-to-lead pipelines that ingest, enrich, and dispatch custom WhatsApp/email teardowns before cold drop-off. Curious if lead response latency is something your sales team is actively optimizing right now.',
            ],
            'tfa_connection_angle_b' => [
                'key' => 'tfa_connection_angle_b',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'connection',
                'angle_label' => 'Angle B: Document Intake, Logistics & OMS (COOs & Operations)',
                'name' => 'LinkedIn Note: Angle B (Vision OMS & Document Intake)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Saw your operational focus at {firm_name}. We recently engineered a Vision OMS pipeline that parses multi-page supplier POs into structured Supabase records with zero dropped items, replacing manual data entry. Curious how your team currently handles non-standard PDF intake.',
            ],
            'tfa_connection_angle_c' => [
                'key' => 'tfa_connection_angle_c',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'connection',
                'angle_label' => 'Angle C: Technical Founders & CTOs (Brittle Zapier / Make Replacement)',
                'name' => 'LinkedIn Note: Angle C (Fault-Tolerant Pipelines & DLQ)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Following your technical build at {firm_name}. We build fault-tolerant workflow pipelines using self-hosted n8n, Pydantic schemas, and automated Dead-Letter Queue failovers to eliminate silent no-code breakages. Always good to connect with other systems engineers.',
            ],
            'tfa_connection_angle_d' => [
                'key' => 'tfa_connection_angle_d',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'connection',
                'angle_label' => 'Angle D: Marketing & Content Scaling (CMOs & Growth Leads)',
                'name' => 'LinkedIn Note: Angle D (Autonomous Marketing Syndication)',
                'due_in_days' => 2,
                'max_chars' => 300,
                'target_stage' => 'Connected / DM Sent',
                'body' => 'Hi {first_name} — Great momentum at {firm_name}. We’ve been deploying autonomous syndication pipelines that turn 1 core breakdown into platform-native LinkedIn/X formats and trigger micro-budget ad boosts behind top 10% organic winners. Glad to connect and share notes.',
            ],
            'tfa_scenario_1' => [
                'key' => 'tfa_scenario_1',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 1: Accepted in Silence (24–48 Hours)',
                'name' => 'Playbook Scenario 1: Accept in Silence (Logistics / Document Intake Case Study)',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Connected / DM Sent',
                'body' => "Thanks for connecting, {first_name}.\n\nSaw your operational focus at {firm_name}. We recently deployed an automated intake pipeline for an international logistics client ([Dave Leng / Ulysses Group]) that parses messy supplier POs and syncs them directly into their database with zero manual keying—saving roughly 15 hours a week.\n\nMapped out the architecture flow and error-handling schema here if you're interested: techfusion-alchemy.xyz#solutions\n\nCurious if your team is currently handling document intake and CRM syncing manually, or if you already have custom scripts running?",
            ],
            'tfa_scenario_2' => [
                'key' => 'tfa_scenario_2',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 2: Replied with Interest / "What exactly do you do?"',
                'name' => 'Playbook Scenario 2: Data Plumbing & Loom Teardown Offer',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Basically, we solve dirty data plumbing and repetitive manual toil for scaling businesses.\n\nInstead of your team wasting hours manually qualifying inbound leads, copy-pasting data between CRMs, or typing purchase orders out of PDFs, we engineer autonomous, fault-tolerant pipelines.\n\nWe don't use fragile Zapier templates. Everything is built on self-hosted n8n, Supabase PostgreSQL, and deterministic JSON schemas with automated dead-letter queue alerts to Slack when an upstream API hiccups.\n\nHappy to show you an architecture diagram of how we structure pipelines, or record a quick 90-second Loom teardown if there's a specific bottleneck slowing your team down right now.",
            ],
            'tfa_scenario_3' => [
                'key' => 'tfa_scenario_3',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 3: Reliability & Hallucinations ("Is this just Zapier?")',
                'name' => 'Playbook Scenario 3: Three Engineering Invariants (Schemas, DLQs & POPIA/GDPR)',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "That exact fragility is why we don't build on standard Zapier or prompt-and-pray AI.\n\nWe operate on three engineering invariants:\n1. Deterministic Contracts: We enforce strict Pydantic / JSON schema validation with zero LLM temperature. If an upstream payload deviates or misses a required field, it doesn't hallucinate—it traps the error.\n2. Dead-Letter Queue (DLQ) Failovers: When an external API (like HubSpot, Stripe, or WhatsApp) returns a 429 rate limit or 500 error, payloads are queued in Redis/PostgreSQL with exponential retries and instant incident alerts dispatched to your Slack.\n3. Data Privacy & Statutory Protection: Under POPIA (Act 4 of 2013) and GDPR, client payloads are processed ephemerally. Zero customer data is ever used to train AI models, and all API keys live in hardware-encrypted secret vaults.\n\nHappy to share our technical SOW template and architecture breakdown if you want to inspect how we sandbox staging.",
            ],
            'tfa_scenario_3_1' => [
                'key' => 'tfa_scenario_3_1',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 3.1: Deliver Architecture Blueprint (5 Stages)',
                'name' => 'Playbook Scenario 3.1: Deliver Architecture Blueprint (5 Stages)',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Here is the interactive architecture breakdown showing the 5 stages of our pipeline infrastructure:\ntechfusion-alchemy.xyz#architecture\n\nIt details the end-to-end ingestion conduit: from raw webhook validation, through schema extraction and CRM bi-directional sync, to the dead-letter queue failover mechanism.\n\nIf you have a specific workflow that's currently bottlenecking your operations—like inbound speed-to-lead or manual invoice processing—I can draft a custom flowchart for your stack.",
            ],
            'tfa_scenario_3_2' => [
                'key' => 'tfa_scenario_3_2',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 3.2: Transition to 5-Day Paid Pilot Sprint (R15,000 / $850)',
                'name' => 'Playbook Scenario 3.2: Offer 5-Day Paid Pilot Sprint (R15,000 / $850)',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "We generally don't ask teams to sign big multi-month contracts upfront.\n\nWe offer a 5-Day Paid Pilot Sprint (R15,000 / ~$850). We isolate your single highest-friction bottleneck—whether that's sub-45s lead triage, purchase order extraction, or multi-app synchronization—and deploy a fully production-tested, self-contained pipeline within 5 business days.\n\nIt includes full mutual NDA, deterministic schemas, Slack incident alerting, and a 14-day defect warranty. If you later decide to expand into a core pipeline overhaul, 100% of the pilot fee is credited toward the build.",
            ],
            'tfa_scenario_4' => [
                'key' => 'tfa_scenario_4',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 4: Handling Mid-Cadence Silence (5–7 Days Later)',
                'name' => 'Playbook Scenario 4: Mid-Cadence Speed-to-Lead Nudge',
                'due_in_days' => 5,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Hey {first_name} — Quick update on our end: we just open-sourced our staging validation framework for multi-channel WhatsApp and email speed-to-lead pipelines, hitting consistent sub-45s response times without human intervention.\n\nReminded me of our chat regarding {firm_name}'s inbound flow. If you'd like to take a look at the flowchart or see the payload schema, happy to send it over. Hope operations are running smoothly this week.",
            ],
            'tfa_scenario_5' => [
                'key' => 'tfa_scenario_5',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'conversation',
                'angle_label' => 'Scenario 5: Graceful Long-Term Disconnect (After 2+ Weeks)',
                'name' => 'Playbook Scenario 5: Graceful Long-Term Disconnect',
                'due_in_days' => 14,
                'max_chars' => null,
                'target_stage' => 'Closed Lost',
                'body' => "Hey {first_name} — I know you're deep in execution and calendar bandwidth is tight, so I'll leave your inbox alone.\n\nI'll keep you in my network and share our quarterly workflow engineering breakdowns as we publish them. If your team ever hits a wall with brittle no-code automations or needs a resilient, production-grade pipeline built down the road, feel free to reach out anytime.\n\nWishing you and {firm_name} continued momentum this quarter.",
            ],
            'tfa_call_transition' => [
                'key' => 'tfa_call_transition',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'call',
                'angle_label' => 'Call Transition: Booking 30-Minute Architecture Session',
                'name' => 'Playbook Call Transition: Propose 30-Min Architecture Whiteboard Session',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Call Scheduled',
                'body' => "If your team is evaluating this right now, I'm happy to do a quick 30-minute technical architecture session next week.\n\nWe won't do a generic sales demo. We'll pull up a virtual whiteboard, map out your current manual workflow step-by-step, identify the highest-friction bottleneck, and sketch out the exact webhook and schema architecture to automate it.\n\nYou can grab a slot directly on my calendar here:\nhttps://calendly.com/khuzwayomuzikayise/automated-growth-systems-consultation-30-minutes\n\nHow does Wednesday or Thursday afternoon look on your side?",
            ],
            'tfa_architecture_session' => [
                'key' => 'tfa_architecture_session',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'call',
                'angle_label' => 'Whiteboard: 30-Minute Architecture Session & Flowchart',
                'name' => 'Playbook Call: 30-Minute Whiteboard Session & Custom Flowchart',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Call Scheduled',
                'body' => "30-Minute Architecture Session Agenda & Talk Track (sales-motions-v2.md Sec 7):\n• Min 00–05: Identify current manual toil and calculate Cost-of-Toil equation for {firm_name}.\n• Min 05–15: Whiteboard current data bottlenecks and error drop-offs.\n• Min 15–25: Map target architecture (Trigger Ingestion -> Structured Schema -> CRM/DB Sync -> DLQ Alerts).\n• Min 25–30: Scope 5-Day Paid Pilot Sprint (R15,000 / $850) with 100% rollover credit.",
            ],
            'tfa_pilot_sow' => [
                'key' => 'tfa_pilot_sow',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'proposal',
                'angle_label' => 'Proposal: Deliver 5-Day Paid Pilot Sprint SOW (R15,000 / $850)',
                'name' => 'Playbook SOW: Deliver 5-Day Paid Pilot Sprint SOW (R15,000 / $850) & Mutual NDA',
                'due_in_days' => 2,
                'max_chars' => null,
                'target_stage' => 'Proposal Sent',
                'body' => "Dispatch 5-Day Paid Pilot Sprint SOW & Mutual NDA for {firm_name} (sales-motions-v2.md Sec 7.1):\n• Pre-populate Section 1 (Bottleneck) and Section 2 (4-stage pipeline architecture).\n• Define Milestone Schedule: M1 Schema & Secrets (Days 1–2), M2 Staging Sandbox 10-payload test (Days 3–4), M3 Production Cutover & Loom Teardown (Day 5).\n• Fixed investment: R15,000 (~$850) with 50% upfront deposit and 50% on M3 sign-off.\n• Include 100% rollover credit clause toward Core Pipeline Build and 14-day statutory warranty.",
            ],
            'tfa_sprint_handover' => [
                'key' => 'tfa_sprint_handover',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'contract',
                'angle_label' => 'Sprint Handover: 5-Day Sprint Cutover & 14-Day Delivery Warranty',
                'name' => 'Playbook Sprint: Deploy 5-Day Sprint Handover & 14-Day Delivery Warranty',
                'due_in_days' => 5,
                'max_chars' => null,
                'target_stage' => 'Closed Won',
                'body' => "Execute 5-Day Sprint Handover Protocol for {firm_name} (sales-motions-v2.md Sec 7.2 & 7.3):\n• Day 5 Production Cutover: live DNS/webhook cutover, end-to-end verification.\n• Deliver 10-minute recorded Loom walkthrough and schema dictionary.\n• Start 14-day defect warranty clock (CPA Sec 54/56 zero-charge bug remediation).\n• Schedule Day 10 Operational Review to present Core Pipeline Expansion Roadmap.",
            ],
            'tfa_core_conversion' => [
                'key' => 'tfa_core_conversion',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'contract',
                'angle_label' => 'Conversion: Convert to Core Pipeline Build or Retainer',
                'name' => 'Playbook Contract: Convert to Core Pipeline Build (R50,000+ / $2,800+) or Fractional Lead',
                'due_in_days' => 10,
                'max_chars' => null,
                'target_stage' => 'Closed Won',
                'body' => "Day 10 Operational Review & Core Expansion for {firm_name} (sales-motions-v2.md Sec 7.3):\n• Review production metrics: total records processed, error rate (< 0.1%), hours saved.\n• Present Core Pipeline Expansion Roadmap (e.g. secondary accounting sync, marketing syndication).\n• Apply 100% of R15,000 ($850) Pilot Sprint fee as credit toward Core Pipeline Build (R50k+ / $2,800+) or monthly Fractional Automation Lead retainer.",
            ],
            'tfa_objection_zapier' => [
                'key' => 'tfa_objection_zapier',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 1: "We already use Zapier / Make.com in-house."',
                'name' => 'Playbook Objection: Zapier / Make vs Self-Hosted n8n',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Makes complete sense—Zapier is great for simple one-to-one triggers. Where teams usually run into walls is when workflows scale: payload timeouts, rate limits, silent dropped records when an API field changes, and no centralized dead-letter queue. That's why we engineer on self-hosted n8n with PostgreSQL state persistence and Pydantic validation. You get full source ownership, zero per-task SaaS tax, and automatic incident alerts when an external API hiccups. Happy to show you a comparison architecture of how we handle failovers.",
            ],
            'tfa_objection_hallucination' => [
                'key' => 'tfa_objection_hallucination',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 2: "AI models hallucinate. We can\'t trust an LLM with records."',
                'name' => 'Playbook Objection: AI Hallucinations vs Deterministic Schemas',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "100% agree—letting a raw LLM generate unconstrained text for database ingestion is a recipe for data corruption. That's why we never do 'prompt-and-pray.' We use deterministic JSON Schema output enforcement with temperature set to zero, validated against strict Pydantic contracts. If an incoming invoice or email doesn't conform to the exact required schema, the pipeline doesn't guess—it flags the record into a human-in-the-loop exception queue and pings Slack. In our Henry Cumines deployment, that architecture processed thousands of line items with zero dropped or corrupted orders.",
            ],
            'tfa_objection_pricing' => [
                'key' => 'tfa_objection_pricing',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 3: "How much does it cost?"',
                'name' => 'Playbook Objection: Commercial Pricing (R15k Pilot & Rollover Credit)',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Proposal Sent',
                'body' => "We keep our commercial models transparent and milestone-based:\n1. The 5-Day Pilot Sprint: R15,000 (~$850 fixed). 1 self-contained workflow deployed to production in 5 business days, backed by a 14-day warranty.\n2. Core Pipeline Build: R50,000+ (~$2,800+). Comprehensive multi-system overhaul connecting CRMs, databases, and custom Python microservices.\n3. Fractional Automation Lead: Monthly retainer for scaling teams needing continuous pipeline engineering, capped at 3 clients concurrently.\n\nCrucially, 100% of the Pilot Sprint fee is credited toward a Core Build if you decide to expand. We can map out your highest-ROI bottleneck on a quick 30-minute architecture session first so you know the exact scope.",
            ],
            'tfa_objection_popia_gdpr' => [
                'key' => 'tfa_objection_popia_gdpr',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 4: "Can\'t share customer data (POPIA / GDPR)."',
                'name' => 'Playbook Objection: POPIA Act 4 & GDPR Compliance',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "We take statutory compliance very seriously. Techfusion Automata operates under South Africa's POPIA (Act 4 of 2013) and GDPR standards. First, we operate under a signed Mutual NDA before any discovery. Second, our API agreements enforce zero data retention for model training—your data is never stored or used by OpenAI or Anthropic. Third, all API keys and database credentials live in encrypted secret vaults (Supabase Vault / AWS KMS), and sensitive PII can be tokenized or masked before ingestion.",
            ],
            'tfa_objection_burned_agency' => [
                'key' => 'tfa_objection_burned_agency',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 5: "Burned by an \'AI agency\' before with broken templates."',
                'name' => 'Playbook Objection: Systems Engineering vs No-Code Agency Hype',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "You're not alone—the market is flooded with marketers white-labeling generic software and calling themselves an AI agency. I'm an Electrical & Computer Engineer from UCT and a published causal ML researcher on SSRN. We build real software systems with custom Python, version-controlled n8n workflows, Docker containers, and rigorous unit testing. You work directly with me—no junior account managers, no off-the-shelf Zapier templates, and every deployment comes with a formal 14-day defect warranty written into the SOW.",
            ],
            'tfa_objection_no_time' => [
                'key' => 'tfa_objection_no_time',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 6: "We don\'t have time for a 3-month IT overhaul right now."',
                'name' => 'Playbook Objection: Isolated 5-Day Sprint vs 3-Month IT Overhaul',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "That's exactly why we don't do 3-month overhauls. Large enterprise IT projects suffer from scope creep and delayed ROI. Our 5-Day Pilot Sprint is designed for zero operational disruption. We take one specific, well-defined friction point—like inbound lead response or vendor invoice parsing—build and test it in an isolated staging sandbox, and cut over to production on Day 5. Your team doesn't have to change their existing tools; the automation works silently in the background.",
            ],
            'tfa_objection_api_downtime' => [
                'key' => 'tfa_objection_api_downtime',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 7: "What happens when an upstream API changes or goes down?"',
                'name' => 'Playbook Objection: Dead-Letter Queues & Automated Slack Alerting',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Every external API (HubSpot, Stripe, WhatsApp, Gmail) will eventually experience downtime or schema changes. That's why every workflow we build includes an automated Dead-Letter Queue (DLQ). If an API returns a 429 rate limit or 500 error, the payload is safely persisted in a PostgreSQL/Redis queue with exponential backoff retries. If it fails 3 consecutive times, an instant incident webhook fires into your engineering Slack channel with the exact payload diff. No lost leads, no missing transactions.",
            ],
            'tfa_objection_roi' => [
                'key' => 'tfa_objection_roi',
                'brand' => self::BRAND_TECHFUSION,
                'category' => 'objection',
                'angle_label' => 'Objection 8: "How do you prove ROI before we start?"',
                'name' => 'Playbook Objection: Cost-of-Toil Equation & Latency Proof',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "We calculate the Cost-of-Toil equation during our 30-minute architecture session:\nMonthly Cost of Toil = (Hours Spent / Wk) × 4.33 × (Blended Hourly Rate).\nFor example, for an operations team spending 15 hours/week manual keying at $30/hr, that's $1,950/month in pure waste. A R15,000 ($850) pilot sprint pays for itself in less than two weeks. For inbound sales, reducing response latency from 2 hours to under 45 seconds routinely lifts conversion rates by 200–300% based on lead response telemetry.",
            ],
        ];
    }

    /**
     * Auto-detect the brand (Dubstrata or Techfusion Automata) for a Lead based on owner, email, or labels.
     */
    public function detectBrand(Lead $lead): string
    {
        // 1. Check lead owner user
        $owner = $lead->ownerUser ?: ($lead->user_owner_id ? User::find($lead->user_owner_id) : null);
        $ownerName = strtolower($owner?->name ?? '');
        $ownerEmail = strtolower($owner?->email ?? '');

        if (str_contains($ownerName, 'dubstrata') || str_contains($ownerEmail, 'dubstrata')) {
            return self::BRAND_DUBSTRATA;
        }

        if (str_contains($ownerName, 'techfusion') || str_contains($ownerEmail, 'techfusion') || str_contains($ownerName, 'alchemy') || str_contains($ownerEmail, 'alchemy')) {
            return self::BRAND_TECHFUSION;
        }

        // 2. Check assigned user
        $assigned = $lead->assignedToUser ?: ($lead->user_assigned_id ? User::find($lead->user_assigned_id) : null);
        $assignedName = strtolower($assigned?->name ?? '');
        $assignedEmail = strtolower($assigned?->email ?? '');

        if (str_contains($assignedName, 'dubstrata') || str_contains($assignedEmail, 'dubstrata')) {
            return self::BRAND_DUBSTRATA;
        }

        if (str_contains($assignedName, 'techfusion') || str_contains($assignedEmail, 'techfusion') || str_contains($assignedName, 'alchemy') || str_contains($assignedEmail, 'alchemy')) {
            return self::BRAND_TECHFUSION;
        }

        // 3. Check labels
        if ($lead->relationLoaded('labels') || $lead->labels()->exists()) {
            foreach ($lead->labels as $label) {
                $labelName = strtolower($label->name ?? '');
                if (str_contains($labelName, 'dubstrata')) {
                    return self::BRAND_DUBSTRATA;
                }
                if (str_contains($labelName, 'techfusion') || str_contains($labelName, 'alchemy') || str_contains($labelName, 'automata')) {
                    return self::BRAND_TECHFUSION;
                }
            }
        }

        // 4. Check lead title / description keywords
        $context = strtolower(($lead->title ?? '').' '.($lead->description ?? ''));
        if (str_contains($context, 'quant') || str_contains($context, 'crypto prop') || str_contains($context, 'portfolio manager') || str_contains($context, 'cro') || str_contains($context, 'dubstrata')) {
            return self::BRAND_DUBSTRATA;
        }

        return self::BRAND_TECHFUSION;
    }

    public function getPlaybookId(string $brand): string
    {
        return $brand === self::BRAND_DUBSTRATA
            ? self::PLAYBOOK_ID_DUBSTRATA
            : self::PLAYBOOK_ID_TECHFUSION;
    }

    public function getPlaybookName(string $brand): string
    {
        return $brand === self::BRAND_DUBSTRATA
            ? self::PLAYBOOK_NAME_DUBSTRATA
            : self::PLAYBOOK_NAME_TECHFUSION;
    }

    public function getTargetAudience(string $brand): string
    {
        return $brand === self::BRAND_DUBSTRATA
            ? self::TARGET_AUDIENCE_DUBSTRATA
            : self::TARGET_AUDIENCE_TECHFUSION;
    }

    public function getGuidingPrinciple(string $brand): string
    {
        return $brand === self::BRAND_DUBSTRATA
            ? self::GUIDING_PRINCIPLE_DUBSTRATA
            : self::GUIDING_PRINCIPLE_TECHFUSION;
    }

    /**
     * Auto-detect the recommended angle for a Lead based on brand, role, headline, or description.
     */
    public function detectRecommendedAngle(Lead $lead): string
    {
        $brand = $this->detectBrand($lead);

        if ($brand === self::BRAND_DUBSTRATA) {
            return $this->detectDubstrataRecommendedAngle($lead);
        }

        return $this->detectTechfusionRecommendedAngle($lead);
    }

    /**
     * Auto-detect the recommended angle for Dubstrata leads.
     */
    public function detectDubstrataRecommendedAngle(Lead $lead): string
    {
        $title = strtolower($lead->title ?? '');
        $description = strtolower($lead->description ?? '');

        // Angle C: Quantitative Developers & Data Infrastructure Leads
        $infraKeywords = ['developer', 'engineer', 'infra', 'pipeline', 'architect', 'flink', 'kafka', 'systems', 'platform', 'devops', 'c++', 'python', 'rust', 'software'];
        foreach ($infraKeywords as $keyword) {
            if (str_contains($title, $keyword)) {
                return 'connection_angle_c';
            }
        }

        // Angle A: Crypto Quant Prop Desks & Market Makers
        $cryptoKeywords = ['crypto', 'prop', 'market maker', 'token', 'digital asset', 'web3', 'polymarket', 'on-chain', 'defi', 'dex', 'cex', 'arbitrage'];
        foreach ($cryptoKeywords as $keyword) {
            if (str_contains($title, $keyword) || str_contains($description, $keyword)) {
                return 'connection_angle_a';
            }
        }

        foreach ($infraKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return 'connection_angle_c';
            }
        }

        // Angle B: Systematic Macro & Multi-Strategy PMs (default institutional focus)
        return 'connection_angle_b';
    }

    /**
     * Auto-detect the recommended angle for Techfusion Automata leads.
     */
    public function detectTechfusionRecommendedAngle(Lead $lead): string
    {
        $title = strtolower($lead->title ?? '');
        $description = strtolower($lead->description ?? '');
        $context = $title.' '.$description;

        // Angle B: Document Intake, Logistics & OMS (COOs, Logistics, Operations)
        $opsKeywords = ['coo', 'operations', 'logistics', 'procurement', 'supply chain', 'intake', 'warehouse', 'order', 'fulfillment', 'freight'];
        foreach ($opsKeywords as $kw) {
            if (str_contains($context, $kw)) {
                return 'tfa_connection_angle_b';
            }
        }

        // Angle C: Technical Founders & CTOs (CTO, Engineering, Systems, Zapier, Make)
        $techKeywords = ['cto', 'engineer', 'developer', 'systems', 'platform', 'infrastructure', 'zapier', 'make', 'n8n', 'pipeline', 'python', 'software'];
        foreach ($techKeywords as $kw) {
            if (str_contains($context, $kw)) {
                return 'tfa_connection_angle_c';
            }
        }

        // Angle D: Marketing & Content Scaling (CMO, Content, Marketing, Growth)
        $mktKeywords = ['cmo', 'marketing', 'content', 'growth', 'brand', 'social', 'media', 'creative', 'acquisition'];
        foreach ($mktKeywords as $kw) {
            if (str_contains($context, $kw)) {
                return 'tfa_connection_angle_d';
            }
        }

        // Angle A: Inbound Sales & Lead Velocity (B2B Founders, Agency Owners, Sales Leads)
        return 'tfa_connection_angle_a';
    }

    /**
     * Render and personalize a template for a given Lead.
     */
    public function renderTemplate(string $templateKey, Lead $lead): array
    {
        $templates = $this->getTemplates();

        if (! isset($templates[$templateKey])) {
            $brand = $this->detectBrand($lead);
            $templateKey = $brand === self::BRAND_DUBSTRATA ? 'connection_angle_b' : 'tfa_connection_angle_a';
        }

        $template = $templates[$templateKey];
        $brand = $template['brand'] ?? $this->detectBrand($lead);

        // Extract lead parameters
        $firstName = $this->extractFirstName($lead);
        $firmName = $lead->organization?->name ?: 'your firm';
        $focusArea = $this->extractFocusArea($lead, $brand);

        // Interpolate tokens
        $renderedBody = str_replace(
            ['{first_name}', '{firm_name}', '{company}', '{focus_area}'],
            [$firstName, $firmName, $firmName, $focusArea],
            $template['body']
        );

        // Enforce non-negotiable invariants: strip exclamation marks and classic sales tropes
        $cleanedBody = $this->enforceInvariants($renderedBody);

        $charCount = mb_strlen($cleanedBody);
        $isValidLength = ! $template['max_chars'] || $charCount <= $template['max_chars'];

        return [
            'key' => $template['key'],
            'brand' => $template['brand'] ?? $brand,
            'category' => $template['category'],
            'angle_label' => $template['angle_label'],
            'name' => $template['name'],
            'due_in_days' => $template['due_in_days'],
            'max_chars' => $template['max_chars'],
            'char_count' => $charCount,
            'is_valid_length' => $isValidLength,
            'target_stage' => $template['target_stage'],
            'body' => $cleanedBody,
        ];
    }

    /**
     * Create a pre-filled Task for the lead from a playbook template.
     */
    public function createPlaybookTask(Lead $lead, string $templateKey, ?int $userId = null): Task
    {
        $rendered = $this->renderTemplate($templateKey, $lead);

        $task = Task::create([
            'external_id' => Uuid::uuid4()->toString(),
            'name' => $rendered['name'],
            'description' => $rendered['body'],
            'taskable_type' => get_class($lead),
            'taskable_id' => $lead->id,
            'due_at' => now()->addDays($rendered['due_in_days']),
            'user_owner_id' => $userId ?: (auth()->id() ?: ($lead->user_owner_id ?: 1)),
            'user_assigned_id' => $userId ?: (auth()->id() ?: ($lead->user_assigned_id ?: 1)),
        ]);

        $lead->activities()->create([
            'causeable_type' => auth()->user() ? auth()->user()->getMorphClass() : null,
            'causeable_id' => auth()->id(),
            'timelineable_type' => $lead->getMorphClass(),
            'timelineable_id' => $lead->id,
            'recordable_type' => $task->getMorphClass(),
            'recordable_id' => $task->id,
        ]);

        return $task;
    }

    /**
     * Auto-create and assign the next sales playbook tasks upon task completion.
     *
     * @return Collection<int, Task>
     */
    public function autoCreateNextTasksForLead(Lead $lead, Task $completedTask): Collection
    {
        $templateKeys = $this->getNextTemplatesForCompletedTask($completedTask, $lead);

        if (empty($templateKeys)) {
            return collect();
        }

        $assignedUserId = $completedTask->user_assigned_id
            ?: $completedTask->user_owner_id
            ?: $lead->user_assigned_id
            ?: $lead->user_owner_id
            ?: (auth()->id() ?: 1);

        $ownerUserId = $completedTask->user_owner_id
            ?: $lead->user_owner_id
            ?: $assignedUserId;

        $createdTasks = collect();

        foreach ($templateKeys as $templateKey) {
            $rendered = $this->renderTemplate($templateKey, $lead);

            // Deduplication: ensure no task with the identical name already exists on this lead
            $alreadyExists = Task::where(function ($q) use ($lead) {
                $q->where('taskable_type', $lead->getMorphClass())
                    ->orWhere('taskable_type', Lead::class);
            })
                ->where('taskable_id', $lead->id)
                ->where('name', $rendered['name'])
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $task = Task::create([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => $rendered['name'],
                'description' => $rendered['body'],
                'taskable_type' => $lead->getMorphClass(),
                'taskable_id' => $lead->id,
                'due_at' => now()->addDays($rendered['due_in_days']),
                'user_owner_id' => $ownerUserId,
                'user_assigned_id' => $assignedUserId,
            ]);

            $lead->activities()->create([
                'causeable_type' => auth()->user() ? auth()->user()->getMorphClass() : null,
                'causeable_id' => auth()->id() ?: $ownerUserId,
                'timelineable_type' => $lead->getMorphClass(),
                'timelineable_id' => $lead->id,
                'recordable_type' => $task->getMorphClass(),
                'recordable_id' => $task->id,
            ]);

            $createdTasks->push($task);
        }

        return $createdTasks;
    }

    /**
     * Determine next template keys based on completed task and lead brand / pipeline stage.
     */
    public function getNextTemplatesForCompletedTask(Task $completedTask, Lead $lead): array
    {
        $brand = $this->detectBrand($lead);

        if ($brand === self::BRAND_DUBSTRATA) {
            return $this->getDubstrataNextTemplates($completedTask, $lead);
        }

        return $this->getTechfusionNextTemplates($completedTask, $lead);
    }

    /**
     * Next templates for Dubstrata institutional quant motion.
     */
    public function getDubstrataNextTemplates(Task $completedTask, Lead $lead): array
    {
        $taskName = strtolower($completedTask->name ?? '');
        $stageOrder = $lead->pipelineStage?->order ?? 1;

        // 1. Scenario 4 (Mid-Cadence Multi-Asset Nudge completed -> Graceful disconnect in 2+ weeks)
        if ($this->matchesKeywords($taskName, ['scenario 4', 'mid-cadence', 'multi-asset nudge'])) {
            return ['scenario_5'];
        }

        // 2. Connection Request / Outreach completed -> Scenario 1 (Audit paper) + Scenario 4 (Nudge)
        if ($this->matchesKeywords($taskName, [
            'connection request', 'connection note', 'angle a', 'angle b', 'angle c',
            'linkedin note', 'send a connection', 'connect with prospect',
        ]) || $stageOrder === 1) {
            return ['scenario_1', 'scenario_4'];
        }

        // 3. Scenario 1 / First touch / Introductory DM completed -> Scenario 3 (Kit link) + Call Transition
        if ($this->matchesKeywords($taskName, [
            'scenario 1', 'accept in silence', 'audit paper', 'introductory dm',
            'intro dm', 'first contact', 'outreach', 'first touch',
        ]) || $stageOrder === 2) {
            return ['scenario_3', 'call_transition'];
        }

        // 4. Evaluation Kit / Canary / Discovery completed -> Scenario 3.1 & Debrief Call
        if ($this->matchesKeywords($taskName, [
            'scenario 2', 'scenario 3', 'evaluation kit', 'quant_evaluation_kit',
            'deliver evaluation kit', 'offer kit', 'discovery', 'conduct discovery',
            'discovery meeting', 'discovery call', 'qualif',
        ]) || $stageOrder === 3) {
            return ['scenario_3_1', 'technical_sync_debrief'];
        }

        // 5. Call Scheduled / Technical Sync / Debrief completed -> Sandbox SOW + CRO Clearance
        if ($this->matchesKeywords($taskName, [
            'call transition', 'technical sync', 'post-eval debrief', 'schedule call',
            'schedule discovery', 'schedule pitch', 'schedule demo', 'book call',
            'schedule meeting', 'call scheduled', 'debrief', 'presentation',
        ]) || $stageOrder === 4) {
            return ['sandbox_sow', 'cro_clearance'];
        }

        // 6. Sandbox SOW / Proposal / Quote completed -> Annual Production Contract
        if ($this->matchesKeywords($taskName, [
            'sandbox sow', 'sow', 'proposal', 'send proposal', 'prepare & send',
            'quote', 'send quote', 'contract terms', 'review contract',
        ]) || $stageOrder === 5) {
            return ['production_contract'];
        }

        // 7. Annual Production Contract / Closed Won completed -> Onboarding & Production Deployment
        if ($this->matchesKeywords($taskName, [
            'closed won', 'deal won', 'close deal', 'annual enterprise production contract',
            'production contract', 'sign contract', 'contract signed', 'license activation',
            'payment received', 'closed',
        ]) || $stageOrder === 6) {
            return ['onboarding_production'];
        }

        return [];
    }

    /**
     * Next templates for Techfusion Automata operator acquisition motion.
     */
    public function getTechfusionNextTemplates(Task $completedTask, Lead $lead): array
    {
        $taskName = strtolower($completedTask->name ?? '');
        $stageOrder = $lead->pipelineStage?->order ?? 1;

        // 1. Scenario 4 (Mid-Cadence Speed-to-Lead Nudge completed -> Graceful disconnect in 2+ weeks)
        if ($this->matchesKeywords($taskName, ['scenario 4', 'mid-cadence', 'speed-to-lead nudge', 'nudge'])) {
            return ['tfa_scenario_5'];
        }

        // 2. Connection Request / Outreach completed -> Scenario 1 (Case Study) + Scenario 4 (Nudge)
        if ($this->matchesKeywords($taskName, [
            'connection request', 'connection note', 'angle a', 'angle b', 'angle c', 'angle d',
            'linkedin note', 'send a connection', 'connect with prospect',
        ]) || $stageOrder === 1) {
            return ['tfa_scenario_1', 'tfa_scenario_4'];
        }

        // 3. Scenario 1 / First touch / Introductory DM completed -> Architecture Blueprint + 30-Min Whiteboard Call
        if ($this->matchesKeywords($taskName, [
            'scenario 1', 'accept in silence', 'case study', 'introductory dm',
            'intro dm', 'first contact', 'outreach', 'first touch',
        ]) || $stageOrder === 2) {
            return ['tfa_scenario_3_1', 'tfa_call_transition'];
        }

        // 4. Architecture Blueprint / Reliability / Loom teardown completed -> 5-Day Pilot Offer + Whiteboard Session
        if ($this->matchesKeywords($taskName, [
            'scenario 2', 'scenario 3', 'blueprint', 'architecture blueprint',
            'deliver architecture', 'loom teardown', 'data plumbing', 'discovery',
            'conduct discovery', 'discovery meeting', 'discovery call', 'qualif',
        ]) || $stageOrder === 3) {
            return ['tfa_scenario_3_2', 'tfa_architecture_session'];
        }

        // 5. Call Scheduled / 30-Min Whiteboard Session completed -> 5-Day Paid Pilot Sprint SOW
        if ($this->matchesKeywords($taskName, [
            'call transition', 'whiteboard', 'architecture session', 'schedule call',
            'schedule discovery', 'schedule pitch', 'schedule demo', 'book call',
            'schedule meeting', 'call scheduled', 'presentation',
        ]) || $stageOrder === 4) {
            return ['tfa_pilot_sow'];
        }

        // 6. Pilot SOW / Proposal completed -> 5-Day Sprint Cutover & 14-Day Delivery Warranty
        if ($this->matchesKeywords($taskName, [
            'pilot sow', 'sow', 'proposal', 'send proposal', 'prepare & send',
            'quote', 'send quote', 'contract terms', 'review contract',
        ]) || $stageOrder === 5) {
            return ['tfa_sprint_handover'];
        }

        // 7. Sprint Handover / Closed Won completed -> Core Pipeline Build Conversion or Fractional Retainer
        if ($this->matchesKeywords($taskName, [
            'closed won', 'deal won', 'close deal', 'sprint handover', 'delivery warranty',
            'handover', 'sign contract', 'contract signed', 'license activation',
            'payment received', 'closed',
        ]) || $stageOrder === 6) {
            return ['tfa_core_conversion'];
        }

        return [];
    }

    /**
     * Extract or infer first name.
     */
    public function extractFirstName(Lead $lead): string
    {
        if ($lead->person) {
            if (! empty($lead->person->first_name)) {
                return trim($lead->person->first_name);
            }
            if (! empty($lead->person->name)) {
                $parts = preg_split('/\s+/', trim($lead->person->name));

                return $parts[0] ?: 'there';
            }
        }

        // Try parsing from title if formatted like "First Last - Role"
        if (! empty($lead->title) && str_contains($lead->title, '-')) {
            $parts = explode('-', $lead->title);
            $namePart = trim($parts[0]);
            $subParts = preg_split('/\s+/', $namePart);
            if (! empty($subParts[0]) && ! in_array(strtolower($subParts[0]), ['lead', 'prospect', 'new', 'interested'])) {
                return $subParts[0];
            }
        }

        return 'there';
    }

    /**
     * Extract or infer focus area.
     */
    protected function extractFocusArea(Lead $lead, string $brand): string
    {
        $context = strtolower(implode(' ', array_filter([
            $lead->title,
            $lead->description,
        ])));

        if ($brand === self::BRAND_DUBSTRATA) {
            if (str_contains($context, 'crypto') || str_contains($context, 'digital asset')) {
                return 'crypto prop and prediction market microstructure';
            }

            if (str_contains($context, 'infra') || str_contains($context, 'data engineering') || str_contains($context, 'flink')) {
                return 'real-time streaming infrastructure and alternative data feeds';
            }

            if (str_contains($context, 'commodit') || str_contains($context, 'oil') || str_contains($context, 'energy')) {
                return 'commodity logistics and cross-asset event risk';
            }

            if (str_contains($context, 'risk') || str_contains($context, 'cro')) {
                return 'systematic event risk and tail-risk hedging';
            }

            return 'systematic macro strategies and quantitative risk';
        }

        // Techfusion focus areas
        if (str_contains($context, 'logistics') || str_contains($context, 'supply') || str_contains($context, 'intake') || str_contains($context, 'po')) {
            return 'supplier PO processing and automated document intake';
        }

        if (str_contains($context, 'sales') || str_contains($context, 'lead') || str_contains($context, 'growth') || str_contains($context, 'agency')) {
            return 'sub-45s speed-to-lead triage and automated enrichment';
        }

        if (str_contains($context, 'marketing') || str_contains($context, 'content') || str_contains($context, 'media')) {
            return 'autonomous multi-channel content syndication';
        }

        return 'fault-tolerant workflow pipelines and eliminating manual toil';
    }

    /**
     * Check if text contains any of the given keywords.
     */
    public function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enforce non-negotiable playbook communication invariants:
     * 1. Never use exclamation marks.
     * 2. Never use canned sales tropes.
     */
    protected function enforceInvariants(string $text): string
    {
        // Replace exclamation marks with periods or empty
        $text = str_replace('!', '.', $text);

        // Clean double periods
        $text = preg_replace('/\.{2,}/', '.', $text);

        return trim($text);
    }
}
