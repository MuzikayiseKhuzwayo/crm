<div class="crm-content">
    <x-crm-header title="Commercial Intelligence & Telemetry Dashboard" subtitle="Ansoff Matrix commercial telemetry mapped through Pirate Metrics (AARRR)" class="mb-5">
        <x-slot:actions>
            <div class="join">
                <button wire:click="selectQuadrant('market_penetration')" class="btn btn-sm join-item {{ $selectedQuadrant === 'market_penetration' ? 'btn-primary' : 'btn-outline' }}">Market Penetration</button>
                <button wire:click="selectQuadrant('product_development')" class="btn btn-sm join-item {{ $selectedQuadrant === 'product_development' ? 'btn-primary' : 'btn-outline' }}">Product Development</button>
                <button wire:click="selectQuadrant('market_development')" class="btn btn-sm join-item {{ $selectedQuadrant === 'market_development' ? 'btn-primary' : 'btn-outline' }}">Market Development</button>
                <button wire:click="selectQuadrant('diversification')" class="btn btn-sm join-item {{ $selectedQuadrant === 'diversification' ? 'btn-primary' : 'btn-outline' }}">Diversification</button>
            </div>
        </x-slot:actions>
    </x-crm-header>

    {{-- TOP METRICS ROW --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-mary-card class="bg-base-100 shadow">
            <div class="text-sm font-semibold text-base-content/70">Win Rate / Velocity</div>
            <div class="text-2xl font-bold text-primary mt-1">{{ $winRate }}%</div>
            <div class="text-xs text-base-content/60 mt-1">{{ $wonDeals }} won / {{ $totalDeals }} total deals</div>
        </x-mary-card>

        <x-mary-card class="bg-base-100 shadow">
            <div class="text-sm font-semibold text-base-content/70">Channel Partner Pipeline</div>
            <div class="text-2xl font-bold text-secondary mt-1">{{ money($partnerPipeline, 'USD') }}</div>
            <div class="text-xs text-base-content/60 mt-1">{{ $activePartners }} active channel partners</div>
        </x-mary-card>

        <x-mary-card class="bg-base-100 shadow">
            <div class="text-sm font-semibold text-base-content/70">Reference Logos / Co-Design</div>
            <div class="text-2xl font-bold text-accent mt-1">{{ $referenceableLogos }} / {{ $designPartners }}</div>
            <div class="text-xs text-base-content/60 mt-1">Legally cleared references & co-design</div>
        </x-mary-card>

        <x-mary-card class="bg-base-100 shadow">
            <div class="text-sm font-semibold text-base-content/70">Stage-Gate Health</div>
            <div class="text-2xl font-bold {{ $pendingGates > 0 ? 'text-warning' : 'text-success' }} mt-1">{{ $approvedGates }} / {{ $approvedGates + $pendingGates }}</div>
            <div class="text-xs text-base-content/60 mt-1">{{ $pendingGates }} pending clearance gates</div>
        </x-mary-card>
    </div>

    {{-- QUADRANT DRILL-DOWN --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-mary-card title="Quadrant Telemetry: {{ ucwords(str_replace('_', ' ', $selectedQuadrant)) }}" shadow separator class="bg-base-100">
            <div class="grid gap-3">
                <div class="flex justify-between items-center p-3 rounded-lg bg-base-200/50">
                    <span class="font-medium text-sm">Telemetry Events Ingested:</span>
                    <span class="font-bold">{{ $quadrantTelemetry['event_count'] }}</span>
                </div>
                <div class="flex justify-between items-center p-3 rounded-lg bg-base-200/50">
                    <span class="font-medium text-sm">Cumulative Metric Value:</span>
                    <span class="font-bold">{{ number_format($quadrantTelemetry['total_metric_value'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center p-3 rounded-lg bg-base-200/50">
                    <span class="font-medium text-sm">Average Metric Value:</span>
                    <span class="font-bold">{{ number_format($quadrantTelemetry['average_metric_value'], 2) }}</span>
                </div>
            </div>
        </x-mary-card>

        <x-mary-card title="Operational Pillar Clearance Overview" shadow separator class="bg-base-100">
            <ul class="steps steps-vertical w-full">
                <li class="step step-primary" data-content="✓">
                    <div class="text-left ml-2">
                        <strong class="text-sm">1. Contract Telemetry & CRM Integration</strong>
                        <p class="text-xs text-base-content/70">Standardized attributes: terms, SLAs, rev-share, floor</p>
                    </div>
                </li>
                <li class="step step-primary" data-content="✓">
                    <div class="text-left ml-2">
                        <strong class="text-sm">2. Derisking Deal Playbook</strong>
                        <p class="text-xs text-base-content/70">Narrative arc: Problem → Pitfalls → Unique Insight → Execution</p>
                    </div>
                </li>
                <li class="step {{ $pendingGates === 0 ? 'step-primary' : 'step-warning' }}" data-content="{{ $pendingGates === 0 ? '✓' : '!' }}">
                    <div class="text-left ml-2">
                        <strong class="text-sm">3. Cross-Functional Handoff Stage-Gates</strong>
                        <p class="text-xs text-base-content/70">Product capability, Operational capacity, Financial margin clearance</p>
                    </div>
                </li>
            </ul>
        </x-mary-card>
    </div>
</div>
