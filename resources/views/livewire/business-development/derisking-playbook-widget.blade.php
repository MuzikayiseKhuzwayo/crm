<div>
    <x-mary-card title="Derisking Deal Playbook" subtitle="Problem → Pitfalls → Unique Insight → Execution (Outcome-first narrative arc)" shadow separator class="bg-base-100 mb-5">
        <form wire:submit.prevent="save" class="grid gap-4">
            <x-mary-textarea label="1. Problem Statement (Acute Commercial Need)" wire:model="problemStatement" placeholder="What core customer pain point or market friction is this deal addressing?" rows="2" />
            
            <x-mary-textarea label="2. Pitfalls Identified (Competitive & Legacy Failure Modes)" wire:model="pitfallsIdentified" placeholder="Why do legacy competitors fail or custom bespoke engineering traps emerge?" rows="2" />
            
            <x-mary-textarea label="3. Unique Insight (Differentiated Mechanism)" wire:model="uniqueInsight" placeholder="What distinct structural or architectural insight unlocks this deal?" rows="2" />
            
            <x-mary-textarea label="4. Execution Plan (Operational Delivery & Milestones)" wire:model="executionPlan" placeholder="What specific rollout, milestones, and handoffs de-risk adoption?" rows="2" />

            <div class="flex justify-end gap-2 mt-2">
                <x-mary-button label="Save Derisking Narrative" type="submit" class="btn-primary btn-sm" icon="o-check" />
            </div>
        </form>
    </x-mary-card>
</div>
