<div>
    <x-mary-card title="Operational Clearance Handoff Gates" subtitle="Product, Operations, and Financial margin gates required prior to deal closing." shadow separator class="bg-base-100 mb-5">
        <div class="grid gap-3">
            @forelse($gates as $gate)
                <div class="flex items-center justify-between p-3 rounded-lg border border-base-200 {{ $gate->status === 'approved' ? 'bg-success/10 border-success/30' : ($gate->status === 'rejected' ? 'bg-error/10 border-error/30' : 'bg-base-200/50') }}">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-sm capitalize">{{ str_replace('_', ' ', $gate->gate_type) }}</span>
                        <x-mary-badge :value="ucfirst($gate->status)" class="{{ $gate->status === 'approved' ? 'badge-success text-white' : ($gate->status === 'rejected' ? 'badge-error text-white' : ($gate->status === 'waived' ? 'badge-warning text-white' : 'badge-neutral')) }}" />
                    </div>
                    <div class="flex items-center gap-2">
                        @if($gate->cleared_at)
                            <span class="text-xs text-base-content/70">{{ $gate->cleared_at->diffForHumans() }}</span>
                        @endif
                        <x-mary-button label="Action" wire:click="open('{{ $gate->gate_type }}')" class="btn-xs btn-outline" />
                    </div>
                </div>
            @empty
                <div class="text-sm text-base-content/60 py-2">No clearance gates initialized for this deal.</div>
            @endforelse
        </div>
    </x-mary-card>

    {{-- ACTION MODAL --}}
    @if($showModal)
        <div class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-3">Clearance Gate Action: {{ ucfirst(str_replace('_', ' ', $selectedGate)) }}</h3>
                <div class="grid gap-4 py-2">
                    <x-mary-select label="Decision Action" wire:model.live="action" :options="[
                        ['id' => 'approve', 'name' => 'Approve Gate'],
                        ['id' => 'waive', 'name' => 'Waive with Audit Reason'],
                        ['id' => 'reject', 'name' => 'Reject Gate']
                    ]" />

                    @if($action !== 'approve')
                        <x-mary-textarea label="Audit Reason / Rationale" wire:model.live="reason" placeholder="Document justification for waiving or rejecting this clearance gate..." rows="3" />
                    @endif
                </div>
                <div class="modal-action">
                    <x-mary-button label="Cancel" wire:click="$set('showModal', false)" class="btn-ghost" />
                    <x-mary-button label="Submit Decision" wire:click="submitGate" class="btn-primary" />
                </div>
            </div>
        </div>
    @endif
</div>
