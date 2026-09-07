<div>
    <x-mary-card title="Contract Telemetry & Structural Terms" subtitle="Standardized contract attributes to protect margin discipline and SLA compliance." shadow separator class="bg-base-100 mb-5">
        <form wire:submit.prevent="save" class="grid gap-4">
            <div class="grid md:grid-cols-2 gap-4">
                <x-mary-select label="Contract Type" wire:model="contractType" :options="[
                    ['id' => 'direct', 'name' => 'Direct Enterprise'],
                    ['id' => 'pilot', 'name' => 'Pilot / PoC Trial'],
                    ['id' => 'enterprise', 'name' => 'Multi-Year Enterprise'],
                    ['id' => 'channel', 'name' => 'Channel / Partner Sourced'],
                    ['id' => 'joint_venture', 'name' => 'Co-Development / Strategic JV'],
                    ['id' => 'amendment', 'name' => 'Expansion Amendment']
                ]" />

                <x-mary-input label="Commitment Term (Months)" wire:model="termMonths" type="number" min="1" max="120" />
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <x-mary-select label="SLA Commitment Level" wire:model="slaLevel" :options="[
                    ['id' => 'standard', 'name' => 'Standard SLA (99.5%)'],
                    ['id' => 'gold', 'name' => 'Gold Tier (99.9%)'],
                    ['id' => 'mission_critical', 'name' => 'Mission-Critical (99.99%)'],
                    ['id' => 'bespoke', 'name' => 'Bespoke Custom SLA']
                ]" />

                <x-mary-input label="Payment Terms" wire:model="paymentTerms" placeholder="e.g. NET_30, ANNUAL_PREPAID" />
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                <x-mary-input label="Partner Rev-Share (%)" wire:model="partnerRevShare" type="number" step="0.01" min="0" max="100" />
                <x-mary-input label="Annual Escalation (%)" wire:model="escalationPercent" type="number" step="0.01" min="0" max="100" />
                <x-mary-input label="Guaranteed Floor ($)" wire:model="minCommitment" type="number" step="0.01" min="0" />
            </div>

            <div class="grid md:grid-cols-2 gap-4 pt-2">
                <label class="label cursor-pointer justify-start gap-3 border rounded-lg p-3 bg-base-200/50">
                    <input type="checkbox" wire:model="isReferenceable" class="checkbox checkbox-primary" />
                    <span class="label-text font-medium">Legally Cleared Reference Logo</span>
                </label>

                <label class="label cursor-pointer justify-start gap-3 border rounded-lg p-3 bg-base-200/50">
                    <input type="checkbox" wire:model="isDesignPartner" class="checkbox checkbox-primary" />
                    <span class="label-text font-medium">Strategic Co-Design Partner</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 mt-2">
                <x-mary-button label="Update Contract Telemetry" type="submit" class="btn-primary btn-sm" icon="o-check" />
            </div>
        </form>
    </x-mary-card>
</div>
