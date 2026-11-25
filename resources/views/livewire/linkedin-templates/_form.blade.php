@php
    $charCount = $this->characterCount;
    $maxLength = $this->maxLength;
    $isConnectNote = $type === \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_CONNECT_NOTE;
    $isOverLimit = $charCount > $maxLength;
    $isNearLimit = $isConnectNote && $charCount >= 260 && ! $isOverLimit;
    $preview = $this->renderedPreview;
@endphp

<div class="grid gap-5">
    <div class="grid lg:grid-cols-2 gap-5">
        <x-mary-input wire:model="name" label="{{ ucfirst(__('laravel-crm::lang.name')) }}" placeholder="e.g. Apify CTO Connection Note" required />
        <x-mary-select wire:model.live="type" :options="$types" label="{{ ucfirst(__('laravel-crm::lang.type')) }}" />
    </div>

    <div>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <label class="text-sm font-medium">{{ ucfirst(__('laravel-crm::lang.linkedin_variables')) }}</label>
            <span class="text-xs text-base-content/60">Click a chip to insert at the end of your message</span>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($variables as $token => $hint)
                <button
                    type="button"
                    wire:click="insertVariable('{{ $token }}')"
                    title="{{ $hint }}"
                    class="btn btn-xs btn-outline font-mono normal-case"
                >
                    {{ $token }}
                </button>
            @endforeach
        </div>
    </div>

    <div>
        <x-mary-textarea
            wire:model.live.debounce.200ms="body"
            rows="6"
            label="{{ ucfirst(__('laravel-crm::lang.body')) }}"
            placeholder="Hi @{{first_name}}, @{{personalized_opener}} Would love to connect!"
        />

        <div class="flex flex-wrap items-center justify-between gap-2 mt-2 text-xs tabular-nums">
            <div class="flex items-center gap-2">
                <span class="{{ $isOverLimit ? 'text-error font-semibold' : ($isNearLimit ? 'text-warning font-semibold' : 'text-base-content/70') }}">
                    {{ $charCount }} / {{ number_format($maxLength) }} {{ __('laravel-crm::lang.characters') }}
                </span>

                @if($isConnectNote)
                    @if($isOverLimit)
                        <span class="badge badge-error badge-xs text-white">Exceeds 300-char LinkedIn invite limit</span>
                    @elseif($isNearLimit)
                        <span class="badge badge-warning badge-xs">Approaching 300-char limit (variables may expand)</span>
                    @else
                        <span class="text-base-content/50">LinkedIn connection notes are capped at 300 chars</span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-base-300 bg-base-200/40 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-base-content/60">
                Sample Rendered Preview
            </span>
            <span class="text-xs tabular-nums {{ $preview['truncated'] ? 'text-warning font-semibold' : 'text-base-content/60' }}">
                {{ $preview['original_length'] }} / {{ number_format($preview['max_length']) }} chars
                @if($preview['truncated'])
                    (truncated to fit limit)
                @endif
            </span>
        </div>
        <div class="whitespace-pre-wrap text-sm text-base-content">{{ $preview['body'] !== '' ? $preview['body'] : 'Start typing above to preview how your template renders for a sample lead.' }}</div>
    </div>
</div>
