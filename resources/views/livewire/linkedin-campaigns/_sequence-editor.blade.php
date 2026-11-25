<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-base font-semibold">Multi-Step Outreach Sequence</h3>
            <p class="text-xs text-base-content/60">
                Order steps from initial connection request to post-acceptance DMs and follow-ups. Variables:
                <code class="font-mono">@{{first_name}}</code>, <code class="font-mono">@{{company}}</code>, <code class="font-mono">@{{personalized_opener}}</code>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-mary-button label="+ Connect" wire:click="addStep('connect')" class="btn-xs btn-outline" type="button" />
            <x-mary-button label="+ DM" wire:click="addStep('dm')" class="btn-xs btn-outline" type="button" />
            <x-mary-button label="+ Follow-up" wire:click="addStep('follow_up')" class="btn-xs btn-primary text-white" type="button" />
        </div>
    </div>

    @error('sequence_steps')
        <div class="text-error text-xs">{{ $message }}</div>
    @enderror

    <div class="space-y-3">
        @forelse($sequence_steps as $index => $step)
            @php
                $stepAction = $step['action'] ?? 'connect';
                $stepBody = (string) ($step['body'] ?? '');
                $stepChars = mb_strlen($stepBody, 'UTF-8');
                $delayHours = (int) ($step['delay_hours'] ?? 0);
                $delaySummary = $delayHours > 0 && $delayHours % 24 === 0
                    ? ($delayHours / 24).' '.(\Illuminate\Support\Str::plural('day', $delayHours / 24))
                    : $delayHours.' '.(\Illuminate\Support\Str::plural('hour', $delayHours));
            @endphp
            <div wire:key="sequence-step-{{ $index }}" class="rounded-lg border border-base-300 bg-base-100 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-200 pb-2">
                    <div class="flex items-center gap-2">
                        <span class="badge badge-neutral badge-sm text-white font-mono">Step {{ $index + 1 }}</span>
                        @if($index > 0)
                            <span class="text-xs text-base-content/60">
                                Wait {{ $delaySummary }} {{ $stepAction === 'dm' && $index === 1 ? 'after acceptance' : 'after previous step' }}
                            </span>
                        @else
                            <span class="text-xs text-base-content/60">Initial outreach touch</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        <x-mary-button
                            icon="o-arrow-up"
                            wire:click="moveStepUp({{ $index }})"
                            :disabled="$index === 0"
                            class="btn-xs btn-square btn-ghost"
                            type="button"
                            tooltip="Move Up"
                        />
                        <x-mary-button
                            icon="o-arrow-down"
                            wire:click="moveStepDown({{ $index }})"
                            :disabled="$index === count($sequence_steps) - 1"
                            class="btn-xs btn-square btn-ghost"
                            type="button"
                            tooltip="Move Down"
                        />
                        <x-mary-button
                            icon="o-trash"
                            wire:click="removeStep({{ $index }})"
                            class="btn-xs btn-square btn-ghost text-error"
                            type="button"
                            tooltip="Remove Step"
                        />
                    </div>
                </div>

                <div class="grid md:grid-cols-3 gap-4">
                    <x-mary-select
                        wire:model.live="sequence_steps.{{ $index }}.action"
                        :options="$actionOptions"
                        label="Action"
                    />

                    <x-mary-input
                        wire:model.live="sequence_steps.{{ $index }}.delay_hours"
                        type="number"
                        min="0"
                        max="2160"
                        label="Wait Delay (hours)"
                        hint="24h = 1 day, 72h = 3 days"
                    />

                    <x-mary-select
                        wire:model.live="sequence_steps.{{ $index }}.template_id"
                        :options="$templates"
                        option-value="id"
                        option-label="name"
                        placeholder="— Inline message below —"
                        placeholder-value=""
                        label="LinkedIn Template (optional)"
                    />
                </div>

                @if(empty($step['template_id']))
                    <div>
                        <x-mary-textarea
                            wire:model.live.debounce.250ms="sequence_steps.{{ $index }}.body"
                            rows="3"
                            label="Inline Message Body"
                            placeholder="Write message or pick a saved template above..."
                        />
                        <div class="flex items-center justify-between mt-1 text-xs tabular-nums">
                            <span class="{{ $stepAction === 'connect' && $stepChars > 300 ? 'text-error font-semibold' : 'text-base-content/60' }}">
                                {{ $stepChars }} {{ $stepAction === 'connect' ? '/ 300' : '/ 8,000' }} characters
                            </span>
                            @if($stepAction === 'connect' && $stepChars > 300)
                                <span class="text-error font-medium">Will be truncated to 300 chars on LinkedIn</span>
                            @endif
                        </div>
                    </div>
                @else
                    @php
                        $selectedTpl = $templates->firstWhere('id', (int) $step['template_id']);
                    @endphp
                    @if($selectedTpl)
                        <div class="rounded bg-base-200/50 p-2.5 text-xs text-base-content/80">
                            <span class="font-semibold">Template preview:</span>
                            <span class="whitespace-pre-wrap">{{ $selectedTpl->body }}</span>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-base-300 p-6 text-center text-sm text-base-content/60">
                No sequence steps yet. Add a Connect or DM step above.
            </div>
        @endforelse
    </div>
</div>
