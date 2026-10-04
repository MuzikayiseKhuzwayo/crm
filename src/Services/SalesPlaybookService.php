<?php

namespace VentureDrake\LaravelCrm\Services;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;

class SalesPlaybookService
{
    public const PLAYBOOK_ID = 'DUB-REV-SALES-MOTIONS-V2-002';

    public const PLAYBOOK_NAME = 'Sales Motions V2: Institutional Conversational Playbook & LinkedIn Framework';

    public const TARGET_AUDIENCE = 'Systematic Quants, Quantitative Portfolio Managers, Crypto Prop Traders, Chief Risk Officers';

    public const GUIDING_PRINCIPLE = 'Peer Engineer to Peer Quant';

    /**
     * Complete repository of Institutional Sales Motion V2 templates.
     */
    public function getTemplates(): array
    {
        return [
            'connection_angle_a' => [
                'key' => 'connection_angle_a',
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
                'category' => 'conversation',
                'angle_label' => 'Scenario 3: When They Ask to See Code / Notebook',
                'name' => 'Playbook Scenario 3: Deliver Evaluation Kit Link',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Engaged / Qualified',
                'body' => "Here’s the signed download link for the sample partition and the notebook:\nhttps://dubstrata.com/evaluation-kit\n\nIt contains the 16 channels (OFI, stationarized price series via fractional diff, entropy-weighted consensus Ψ_CSI, and graph belief vectors). The notebook also runs the gradient leakage audit so you can verify zero lookahead directly.\n\nLet me know if any questions come up while running it.",
            ],
            'scenario_4' => [
                'key' => 'scenario_4',
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
                'category' => 'call',
                'angle_label' => 'Call Transition: Booking 15-Minute Technical Sync',
                'name' => 'Playbook Call Transition: Propose 15-Min Technical Sync',
                'due_in_days' => 1,
                'max_chars' => null,
                'target_stage' => 'Call Scheduled',
                'body' => "If you or one of your quant researchers are exploring this, I'm happy to do a quick 15-minute technical sync next week.\n\nI can show you how our Flink SQL pipeline materializes the channels in real time and how other desks ingest the daily Parquet partitions. If there's an alignment, we can spin up sandbox credentials for your research environment.\n\nHow does Wednesday or Thursday afternoon look on your end?",
            ],
            'objection_inhouse' => [
                'key' => 'objection_inhouse',
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
     * Auto-detect the recommended angle for a Lead based on role, headline, or description.
     */
    public function detectRecommendedAngle(Lead $lead): string
    {
        $title = strtolower($lead->title ?? '');
        $description = strtolower($lead->description ?? '');
        $context = strtolower(implode(' ', array_filter([
            $lead->title,
            $lead->description,
            $lead->person?->name,
            $lead->organization?->name,
        ])));

        // Angle C: Quantitative Developers & Data Infrastructure Leads (Title match has highest priority)
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

        // Angle C: Secondary description check for infrastructure builders
        foreach ($infraKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return 'connection_angle_c';
            }
        }

        // Angle B: Systematic Macro & Multi-Strategy PMs (default institutional focus)
        return 'connection_angle_b';
    }

    /**
     * Render and personalize a template for a given Lead.
     */
    public function renderTemplate(string $templateKey, Lead $lead): array
    {
        $templates = $this->getTemplates();

        if (! isset($templates[$templateKey])) {
            $templateKey = 'connection_angle_b';
        }

        $template = $templates[$templateKey];

        // Extract lead parameters
        $firstName = $this->extractFirstName($lead);
        $firmName = $lead->organization?->name ?: 'your firm';
        $focusArea = $this->extractFocusArea($lead);

        // Interpolate tokens
        $renderedBody = str_replace(
            ['{first_name}', '{firm_name}', '{focus_area}'],
            [$firstName, $firmName, $focusArea],
            $template['body']
        );

        // Enforce non-negotiable invariants: strip exclamation marks and classic sales tropes
        $cleanedBody = $this->enforceInvariants($renderedBody);

        $charCount = mb_strlen($cleanedBody);
        $isValidLength = ! $template['max_chars'] || $charCount <= $template['max_chars'];

        return [
            'key' => $template['key'],
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
     * Extract or infer focus area for scenario 1.
     */
    protected function extractFocusArea(Lead $lead): string
    {
        $context = strtolower(implode(' ', array_filter([
            $lead->title,
            $lead->description,
        ])));

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
