<div class="crm-content">
    <x-mary-header title="{{ $campaign->name }}" subtitle="{{ ucfirst(str_replace('_', ' ', $campaign->status)) }} · {{ count($campaign->sequence_steps ?? []) }} steps · {{ $campaign->timezone }}">
        <x-slot:actions>
            @can('update', $campaign)
                <x-mary-button
                    label="Bulk Enroll Leads"
                    wire:click="openEnrollModal"
                    icon="o-user-plus"
                    class="btn-primary text-white"
                    responsive
                />

                @if($campaign->isCircuitBroken())
                    <x-mary-button
                        label="Reset & Resume"
                        wire:click="resetCircuitBreaker(true)"
                        icon="o-play"
                        class="btn-error text-white"
                        spinner="resetCircuitBreaker"
                        responsive
                    />
                @elseif($campaign->status !== \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_COMPLETED)
                    <x-mary-button
                        wire:click="toggleStatus"
                        :label="$campaign->isActive() ? 'Pause' : 'Activate'"
                        :icon="$campaign->isActive() ? 'o-pause' : 'o-play'"
                        :class="$campaign->isActive() ? 'btn-warning' : 'btn-success text-white'"
                        spinner="toggleStatus"
                        responsive
                    />
                @endif
            @endcan

            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back')) }}" link="{{ route('laravel-crm.linkedin-campaigns.index') }}" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Circuit Breaker Banner --}}
    @if($campaign->isCircuitBroken())
        <div role="alert" class="alert alert-error mb-5 shadow">
            <x-mary-icon name="o-shield-exclamation" class="w-6 h-6 shrink-0" />
            <div class="flex-1">
                <h3 class="font-bold">Circuit Breaker Tripped</h3>
                <div class="text-xs">
                    Reason: <span class="font-mono font-semibold">{{ $campaign->circuit_broken_reason ?? 'unknown' }}</span>
                    @if($campaign->circuit_broken_at)
                        · {{ $campaign->circuit_broken_at->diffForHumans() }}
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <x-mary-button label="Reset (Keep Paused)" wire:click="resetCircuitBreaker(false)" class="btn-sm btn-ghost border border-current" />
                <x-mary-button label="Reset & Resume" wire:click="resetCircuitBreaker(true)" icon="o-play" class="btn-sm btn-neutral text-white" />
            </div>
        </div>
    @endif

    {{-- Funnel Metrics --}}
    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
        <x-mary-stat title="Enrolled" :value="$campaign->total_recipients" icon="o-users" class="border border-base-200" />
        <x-mary-stat title="Connects Sent" :value="$campaign->connects_sent_count" icon="o-paper-airplane" class="border border-base-200" />
        <x-mary-stat title="Accepted" :value="$campaign->accepted_count" :description="$campaign->acceptanceRate().'% acceptance'" icon="o-check-circle" class="border border-base-200" />
        <x-mary-stat title="DMs Sent" :value="$campaign->dms_sent_count" icon="o-chat-bubble-left" class="border border-base-200" />
        <x-mary-stat title="Replied" :value="$campaign->replied_count" :description="$campaign->replyRate().'% reply rate'" icon="o-sparkles" class="border border-base-200" />
    </div>

    {{-- Section Tabs --}}
    <div role="tablist" class="tabs tabs-boxed bg-base-200/70 p-1 mb-5 inline-flex flex-wrap gap-1">
        <button
            type="button"
            role="tab"
            wire:click="$set('activeTab', 'recipients')"
            class="tab {{ $activeTab === 'recipients' ? 'tab-active bg-base-100 font-semibold shadow-sm' : '' }}"
        >
            Recipients Queue ({{ $campaign->total_recipients }})
        </button>
        <button
            type="button"
            role="tab"
            wire:click="$set('activeTab', 'sequence')"
            class="tab {{ $activeTab === 'sequence' ? 'tab-active bg-base-100 font-semibold shadow-sm' : '' }}"
        >
            Sequence Editor ({{ count($sequence_steps) }})
        </button>
        <button
            type="button"
            role="tab"
            wire:click="$set('activeTab', 'settings')"
            class="tab {{ $activeTab === 'settings' ? 'tab-active bg-base-100 font-semibold shadow-sm' : '' }}"
        >
            Safety & Schedule
        </button>
        <button
            type="button"
            role="tab"
            wire:click="$set('activeTab', 'extension')"
            class="tab {{ $activeTab === 'extension' ? 'tab-active bg-base-100 font-semibold shadow-sm' : '' }}"
        >
            Chrome Extension API
        </button>
    </div>

    {{-- Tab 1: Recipients Queue --}}
    @if($activeTab === 'recipients')
        <x-mary-card shadow>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div class="w-full sm:w-64">
                        <x-mary-input wire:model.live.debounce.300ms="recipientSearch" placeholder="Search lead, slug, opener..." icon="o-magnifying-glass" clearable />
                    </div>
                    <div class="w-full sm:w-48">
                        <x-mary-select wire:model.live="recipientStatus" :options="$recipientStatuses" placeholder="All statuses" placeholder-value="" />
                    </div>
                </div>
                @can('update', $campaign)
                    <x-mary-button label="Bulk Enroll Leads" wire:click="openEnrollModal" icon="o-user-plus" class="btn-sm btn-primary text-white" />
                @endcan
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Lead / Contact</th>
                            <th>Status</th>
                            <th>Step</th>
                            <th>Personalized Opener</th>
                            <th>Next Action</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipients as $recipient)
                            @php
                                $person = $recipient->person ?? $recipient->lead?->person;
                                $organization = $recipient->lead?->organization ?? $person?->organization;
                                $displayName = trim((string) ($person?->name ?: $recipient->lead?->title ?: $recipient->normalized_linkedin_slug));
                                $statusBadge = match ($recipient->status) {
                                    'queued' => 'badge-neutral text-white',
                                    'leased' => 'badge-info text-white',
                                    'connect_sent' => 'badge-primary text-white',
                                    'connected', 'in_sequence' => 'badge-accent text-white',
                                    'replied', 'completed' => 'badge-success text-white',
                                    'skipped' => 'badge-warning',
                                    'failed' => 'badge-error text-white',
                                    default => 'badge-ghost',
                                };
                            @endphp
                            <tr wire:key="recipient-{{ $recipient->id }}">
                                <td>
                                    <div class="font-medium">{{ $displayName }}</div>
                                    <div class="text-xs text-base-content/60 flex flex-wrap items-center gap-2">
                                        @if($organization)
                                            <span>{{ $organization->name }}</span>
                                        @endif
                                        <a href="{{ $recipient->linkedin_url }}" target="_blank" rel="noopener noreferrer" class="link link-primary font-mono">
                                            /in/{{ $recipient->normalized_linkedin_slug }}
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <x-mary-badge :value="ucfirst(str_replace('_', ' ', $recipient->status))" :class="$statusBadge" />
                                    @if($recipient->last_error_code)
                                        <div class="text-xs text-error font-mono mt-0.5" title="{{ $recipient->last_error_message }}">
                                            {{ $recipient->last_error_code }}
                                        </div>
                                    @endif
                                </td>
                                <td class="tabular-nums">
                                    {{ $recipient->current_step }} / {{ count($campaign->sequence_steps ?? []) }}
                                </td>
                                <td class="max-w-xs">
                                    @if($editingRecipientId === $recipient->id)
                                        <div class="flex flex-col gap-1.5">
                                            <textarea
                                                wire:model="editingOpener"
                                                rows="2"
                                                class="textarea textarea-bordered textarea-xs w-full"
                                                placeholder="Custom opener for @{{personalized_opener}}..."
                                            ></textarea>
                                            <div class="flex gap-1">
                                                <button type="button" wire:click="saveOpener" class="btn btn-xs btn-primary text-white">Save</button>
                                                <button type="button" wire:click="cancelEditingOpener" class="btn btn-xs btn-ghost">Cancel</button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-xs {{ $recipient->personalized_opener ? 'text-base-content' : 'text-base-content/40 italic' }} line-clamp-2">
                                                {{ $recipient->personalized_opener ?: 'No custom opener' }}
                                            </span>
                                            @can('update', $campaign)
                                                <button
                                                    type="button"
                                                    wire:click="startEditingOpener({{ $recipient->id }})"
                                                    class="btn btn-xs btn-ghost shrink-0"
                                                    title="Edit personalized opener"
                                                >
                                                    <x-mary-icon name="o-pencil" class="w-3.5 h-3.5" />
                                                </button>
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                                <td class="text-xs tabular-nums text-base-content/70">
                                    @if($recipient->next_action_at)
                                        {{ $recipient->next_action_at->diffForHumans() }}
                                    @elseif($recipient->replied_at)
                                        Replied {{ $recipient->replied_at->diffForHumans() }}
                                    @elseif($recipient->completed_at)
                                        Done {{ $recipient->completed_at->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-1 justify-end">
                                        <x-mary-button
                                            icon="o-eye"
                                            wire:click="openMessagePreview({{ $recipient->id }})"
                                            class="btn-xs btn-square btn-outline"
                                            tooltip="Preview Rendered Message"
                                        />
                                        @can('update', $campaign)
                                            @if(in_array($recipient->status, ['failed', 'skipped'], true))
                                                <x-mary-button
                                                    icon="o-arrow-path"
                                                    wire:click="retryRecipient({{ $recipient->id }})"
                                                    class="btn-xs btn-square btn-info text-white"
                                                    tooltip="Retry Lead"
                                                />
                                            @elseif(! in_array($recipient->status, ['replied', 'completed'], true))
                                                <x-mary-button
                                                    icon="o-forward"
                                                    wire:click="skipRecipient({{ $recipient->id }})"
                                                    class="btn-xs btn-square btn-warning"
                                                    tooltip="Skip Lead"
                                                />
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-base-content/60">
                                    No recipients match this filter yet. Click <strong>Bulk Enroll Leads</strong> to add Apify/LinkedIn leads.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $recipients->links() }}
            </div>
        </x-mary-card>
    @endif

    {{-- Tab 2: Sequence Editor --}}
    @if($activeTab === 'sequence')
        <x-mary-form wire:submit="saveSequence">
            <x-mary-card shadow>
                @include('laravel-crm::livewire.linkedin-campaigns._sequence-editor')
                <div class="mt-5 flex justify-end">
                    <x-mary-button label="Save Sequence" type="submit" class="btn-primary text-white" spinner="saveSequence" />
                </div>
            </x-mary-card>
        </x-mary-form>
    @endif

    {{-- Tab 3: Safety & Schedule --}}
    @if($activeTab === 'settings')
        <x-mary-form wire:submit="saveSettings">
            <x-mary-card shadow title="Campaign Safety Caps & Working Schedule">
                <div class="mb-5">
                    <x-mary-input wire:model="name" label="{{ ucfirst(__('laravel-crm::lang.name')) }}" required />
                </div>
                @include('laravel-crm::livewire.linkedin-campaigns._safety-schedule-form')
                <div class="mt-5 flex justify-end">
                    <x-mary-button label="Save Safety & Schedule" type="submit" class="btn-primary text-white" spinner="saveSettings" />
                </div>
            </x-mary-card>
        </x-mary-form>
    @endif

    {{-- Tab 4: Chrome Extension API Token --}}
    @if($activeTab === 'extension')
        <x-mary-card shadow title="Connect Local Chrome Extension">
            @include('laravel-crm::livewire.linkedin-campaigns._api-token-panel')
        </x-mary-card>
    @endif

    {{-- Bulk Enroll Leads Modal --}}
    <x-mary-modal wire:model="showEnrollModal" title="Bulk Enroll Leads into {{ $campaign->name }}" subtitle="Pre-filtered to leads with a LinkedIn profile URL on Lead or Contact Person" box-class="max-w-3xl">
        <div class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-3">
                <x-mary-select
                    wire:model.live="enrollLeadSourceId"
                    :options="$leadSources"
                    option-value="id"
                    option-label="name"
                    placeholder="All Lead Sources (e.g. Apify / LinkedIn)"
                    placeholder-value=""
                    label="Lead Source"
                />
                <x-mary-select
                    wire:model.live="enrollLabelId"
                    :options="$labels"
                    option-value="id"
                    option-label="name"
                    placeholder="All Labels"
                    placeholder-value=""
                    label="Label"
                />
                <x-mary-select
                    wire:model.live="enrollRelayStatus"
                    :options="$relayStatuses"
                    placeholder="Any Relay Status"
                    placeholder-value=""
                    label="Relay Status"
                />
                <x-mary-input
                    wire:model.live.debounce.300ms="enrollSearch"
                    label="Search"
                    placeholder="Title, company, or LinkedIn slug..."
                    icon="o-magnifying-glass"
                    clearable
                />
            </div>

            @if($enrollmentPreview)
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-center">
                        <div class="text-2xl font-bold tabular-nums text-success">{{ $enrollmentPreview['enrollable_count'] }}</div>
                        <div class="text-xs text-base-content/70">Valid /in/ URLs Ready</div>
                    </div>
                    <div class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-center">
                        <div class="text-2xl font-bold tabular-nums text-warning">{{ $enrollmentPreview['duplicate_count'] }}</div>
                        <div class="text-xs text-base-content/70">Will Be Deduplicated</div>
                    </div>
                    <div class="rounded-lg border border-base-300 bg-base-200/50 p-3 text-center">
                        <div class="text-2xl font-bold tabular-nums">{{ $enrollmentPreview['invalid_url_count'] }}</div>
                        <div class="text-xs text-base-content/70">Non-/in/ URLs Skipped</div>
                    </div>
                </div>

                @if(! empty($enrollmentPreview['sample_leads']))
                    <div class="rounded-lg border border-base-300 overflow-hidden">
                        <div class="bg-base-200/60 px-3 py-1.5 text-xs font-semibold text-base-content/70">
                            Preview (showing {{ count($enrollmentPreview['sample_leads']) }} of {{ $enrollmentPreview['total_matched'] }} matching leads)
                        </div>
                        <div class="divide-y divide-base-200 max-h-48 overflow-y-auto">
                            @foreach($enrollmentPreview['sample_leads'] as $sample)
                                <div class="flex items-center justify-between gap-2 px-3 py-2 text-xs">
                                    <div class="truncate">
                                        <span class="font-medium">{{ $sample['title'] }}</span>
                                        <span class="text-base-content/50 font-mono ml-1">{{ $sample['linkedin_url'] }}</span>
                                    </div>
                                    @if($sample['status'] === 'ready')
                                        <span class="badge badge-success badge-xs text-white shrink-0">Ready</span>
                                    @elseif($sample['status'] === 'duplicate')
                                        <span class="badge badge-warning badge-xs shrink-0">Duplicate</span>
                                    @else
                                        <span class="badge badge-ghost badge-xs shrink-0">Invalid URL</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.cancel')) }}" wire:click="$set('showEnrollModal', false)" />
            <x-mary-button
                label="Enroll {{ $enrollmentPreview['enrollable_count'] ?? 0 }} Leads"
                wire:click="enrollFilteredLeads"
                icon="o-user-plus"
                class="btn-primary text-white"
                :disabled="($enrollmentPreview['enrollable_count'] ?? 0) === 0"
                spinner="enrollFilteredLeads"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Message Preview Modal --}}
    <x-mary-modal wire:model="showMessagePreviewModal" title="Message Preview — {{ $previewRecipientName }}" subtitle="{{ $previewStepLabel }}">
        <div class="space-y-3">
            <div class="rounded-lg border border-base-300 bg-base-200/40 p-4 whitespace-pre-wrap text-sm">
                {{ $previewRenderedBody !== '' ? $previewRenderedBody : 'No message body configured for this step.' }}
            </div>
            <div class="flex items-center justify-between text-xs tabular-nums text-base-content/60">
                <span>{{ mb_strlen($previewRenderedBody, 'UTF-8') }} / {{ number_format($previewMaxLength) }} characters</span>
                @if($previewTruncated)
                    <span class="badge badge-warning badge-xs">Truncated to fit LinkedIn limit</span>
                @endif
            </div>
        </div>
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.close')) }}" wire:click="$set('showMessagePreviewModal', false)" />
        </x-slot:actions>
    </x-mary-modal>
</div>
