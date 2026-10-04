<div class="crm-content">
    {{-- HEADER --}}
    <x-crm-header title="{{ $lead->title }}" class="mb-5" progress-indicator >
        {{-- BADGES --}}
        <x-slot:badges>
            @if($lead->pipelineStage)
                <x-mary-badge :value="$lead->pipelineStage->name" class="badge badge-neutral text-white" />
            @endif
        </x-slot:badges>
            
        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back_to_leads')) }}" link="{{ url(route('laravel-crm.leads.index')) }}" icon="fas.angle-double-left" class="btn-sm btn-outline" responsive />
            @hasdealsenabled
            @can('edit crm leads')
                | <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.convert')) }}" link="{{ route('laravel-crm.deals.create', ['model' => 'lead', 'id' => $lead->id]) }}" class="btn-sm btn-success text-white" responsive  />
            @endcan
            @endhasdealsenabled
            | <livewire:crm-activity-menu /> |
            @can('edit crm leads')
                <x-mary-button link="{{ url(route('laravel-crm.leads.edit', $lead)) }}" icon="o-pencil-square" class="btn-sm btn-square btn-outline" responsive />
            @endcan
            @can('delete crm leads')
                <x-mary-button onclick="modalDeleteLead{{ $lead->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner />
                <x-crm-delete-confirm model="lead" id="{{ $lead->id }}" />
            @endcan
        </x-slot:actions>
    </x-crm-header>

    {{-- ACCOUNT OUTREACH INTELLIGENCE & COLLISION BANNER --}}
    @php
        $companySummary = $this->companyOutreach;
    @endphp
    @if($companySummary)
        <div class="mb-5 p-4 rounded-xl border {{ $companySummary['banner_class'] }} flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs">
            <div class="flex items-start gap-3">
                <x-mary-icon :name="$companySummary['icon']" class="w-6 h-6 shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm tracking-wide uppercase">{{ $companySummary['headline'] }}</span>
                        <x-mary-badge :value="$companySummary['badge_label']" :class="$companySummary['badge_class'].' text-white text-xs'" />
                    </div>
                    <p class="text-xs opacity-90 leading-relaxed">{{ $companySummary['description'] }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                @if($lead->organization)
                    @if($companySummary['status'] === 'disqualified')
                        <x-mary-button label="Clear Do Not Contact" wire:click="clearCompanyDisqualified" icon="o-check" class="btn-xs btn-outline bg-base-100" spinner="clearCompanyDisqualified" />
                    @else
                        <x-mary-button label="Mark Company as Disqualified" wire:click="markCompanyDisqualified" icon="o-no-symbol" class="btn-xs btn-outline btn-error bg-base-100" spinner="markCompanyDisqualified" />
                    @endif
                    <x-mary-button label="View Company" link="{{ route('laravel-crm.organizations.show', $lead->organization) }}" icon="o-arrow-top-right-on-square" class="btn-xs btn-outline bg-base-100" />
                @endif
            </div>

            @if($lead->organization && $companySummary['total_leads_count'] > 1)
                <div class="w-full pt-3 mt-1 border-t border-base-content/10 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold uppercase tracking-wider text-[11px] opacity-80 flex items-center gap-1">
                            <x-mary-icon name="o-arrow-path-rounded-square" class="w-4 h-4 shrink-0" style="width:16px;height:16px;" />
                            Account Outreach Relay:
                        </span>
                        @php
                            $badge = $lead->relay_badge;
                        @endphp
                        <x-mary-badge :value="$badge['label']" :class="$badge['class'].' badge-sm'" />
                        @if($lead->relay_activated_at && $lead->relay_status === 'active')
                            <span class="text-base-content/70 text-[11px]">
                                (Active {{ $lead->relay_activated_at->diffForHumans() }})
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @if($lead->relay_status === 'active')
                            <x-mary-button label="Pass to Next Colleague" wire:click="rotateRelay('passed by rep')" icon="o-arrow-right-circle" class="btn-xs btn-outline bg-base-100" spinner="rotateRelay" />
                        @elseif(empty($lead->relay_status))
                            <x-mary-button label="Initialize Relay Basket" wire:click="initializeRelayQueue" icon="o-queue-list" class="btn-xs btn-outline bg-base-100" spinner="initializeRelayQueue" />
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- PIPELINE STAGE PROGRESSION & QUICK TASK AUTOMATION BAR --}}
    <x-mary-card shadow class="mb-5 border border-base-300">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-mary-icon name="o-funnel" class="w-5 h-5 text-primary shrink-0" />
                    <span class="font-bold text-sm uppercase tracking-wider text-base-content/80">Pipeline Stage:</span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach($this->pipelineStages as $stage)
                        @php
                            $isCurrent = $lead->pipeline_stage_id == $stage->id;
                        @endphp
                        <button wire:click="updateStage({{ $stage->id }})" 
                                class="btn btn-xs rounded-full font-semibold transition-all {{ $isCurrent ? 'btn-primary text-white shadow-xs' : 'btn-outline border-base-300 text-base-content/70 hover:btn-neutral' }}">
                            @if($isCurrent)
                                <x-mary-icon name="o-check-circle" class="w-3.5 h-3.5 mr-0.5 shrink-0" style="width:14px;height:14px;" />
                            @endif
                            <span>{{ $stage->name }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @php
                $pb = $this->playbook;
                $recommendedKey = $pb['recommended_angle'];
                $recommendedTemplate = $pb['templates'][$recommendedKey];
            @endphp
            <div class="pt-3 border-t border-base-200 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold uppercase tracking-wider text-xs flex items-center gap-1.5 text-base-content/80">
                        <x-mary-icon name="o-sparkles" class="w-4 h-4 text-warning shrink-0" />
                        Sales Playbook V2:
                    </span>
                    <x-mary-badge :value="'🎯 Auto-Detected: '.$recommendedTemplate['angle_label']" class="badge-warning text-neutral-900 font-bold text-xs" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-mary-button 
                        label="+ Connect (Auto Angle)" 
                        wire:click="createStageTask('{{ $recommendedKey }}')" 
                        icon="o-user-plus" 
                        class="btn-xs btn-primary text-white" 
                        spinner="createStageTask" 
                    />
                    <x-mary-button 
                        label="+ Scenario 1 (Audit)" 
                        wire:click="createStageTask('scenario_1')" 
                        icon="o-document-text" 
                        class="btn-xs btn-outline btn-info" 
                        spinner="createStageTask" 
                    />
                    <x-mary-button 
                        label="+ Scenario 2 (Offer Kit)" 
                        wire:click="createStageTask('scenario_2')" 
                        icon="o-beaker" 
                        class="btn-xs btn-outline btn-secondary" 
                        spinner="createStageTask" 
                    />
                    <x-mary-button 
                        label="+ 15-Min Sync Call" 
                        wire:click="createStageTask('call_transition')" 
                        icon="o-phone" 
                        class="btn-xs btn-outline btn-warning" 
                        spinner="createStageTask" 
                    />
                    <x-mary-button 
                        label="Playbook & Objections ({{ count($pb['templates']) }})" 
                        onclick="modalPlaybook{{ $lead->id }}.showModal()" 
                        icon="o-book-open" 
                        class="btn-xs btn-outline btn-neutral" 
                    />
                </div>
            </div>
        </div>
    </x-mary-card>

    <div class="grid lg:grid-cols-2 gap-5 items-start">
        <div class="grid gap-y-5">
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.details')) }}" shadow separator>
                <div class="grid gap-y-3">
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.created')) }}</strong>
                        <span>
                        {{ $lead->created_at->diffForHumans() }}
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.number')) }}</strong>
                        <span>
                        {{ $lead->lead_id }}
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.value')) }}</strong>
                        <span>
                       {{ money($lead->amount, $lead->currency) }}
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.description')) }}</strong>
                        <span>
                        {{ $lead->description }}
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.lead_source')) }}</strong>
                        <span>
                        {{ $lead->leadSource->name ?? '-' }}
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.labels')) }}</strong>
                        <span>
                        @foreach($lead->labels as $label)
                            <x-mary-badge :value="$label->name" class="badge-sm text-white" :style="'border-color: #'.$label->hex.'; background-color: #'.$label->hex" />
                        @endforeach
                    </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <strong>{{ ucfirst(__('laravel-crm::lang.owner')) }}</strong>
                        <span>
                        @if( $lead->ownerUser)<a href="{{ route('laravel-crm.users.show', $lead->ownerUser) }}" class="link link-hover link-primary">{{ $lead->ownerUser->name ?? null }}</a> @else  {{ ucfirst(__('laravel-crm::lang.unallocated')) }} @endif
                        </span>
                    </div>
                    <x-crm-custom-field-values :model="$lead"/>
                </div>
            </x-mary-card>
            <x-crm-custom-field-values :model="$lead" :group="true" />
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.contact')) }}" shadow separator>
                <div class="grid gap-y-5">
                    <div class="flex flex-row gap-5">
                        <x-mary-icon name="fas.user-circle" />
                        <span>
                        @if($lead->person)<a href="{{ route('laravel-crm.people.show',$lead->person) }}" class="link link-hover link-primary">{{ $lead->person->name }}</a>@endif
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <x-mary-icon name="fas.envelope" />
                        <span>
                        @if($email)
                        <a href="mailto:{{ $email->address }}">{{ $email->address }}</a> ({{ ucfirst($email->type) }})
                        @endif
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <x-mary-icon name="fas.phone" />
                        <span>
                        @if($phone)
                        <a href="tel:{{ $phone->number }}">{{ $phone->number }}</a> ({{ ucfirst($phone->type) }})
                        @endif
                        </span>
                    </div>
                    @if($lead->linkedin || $lead->twitter || $lead->website || ($lead->person && ($lead->person->linkedin || $lead->person->twitter || $lead->person->website)))
                        @php
                            $linkedin = $lead->linkedin ?: $lead->person?->linkedin;
                            $twitter = $lead->twitter ?: $lead->person?->twitter;
                            $website = $lead->website ?: $lead->person?->website;
                        @endphp
                        <div class="pt-3 border-t border-base-200">
                            <span class="text-xs font-bold text-base-content/70 block mb-2">Social & Web Links:</span>
                            <div class="flex flex-wrap gap-2">
                                @if($linkedin)
                                    <a href="{{ str_starts_with($linkedin, 'http') ? $linkedin : 'https://'.$linkedin }}" target="_blank" class="btn btn-xs btn-outline btn-primary gap-1">
                                        <x-mary-icon name="o-link" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>LinkedIn Profile</span>
                                    </a>
                                @endif
                                @if($twitter)
                                    <a href="{{ str_starts_with($twitter, 'http') ? $twitter : 'https://x.com/'.ltrim($twitter, '@') }}" target="_blank" class="btn btn-xs btn-outline btn-info gap-1">
                                        <x-mary-icon name="o-hashtag" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Twitter / X</span>
                                    </a>
                                @endif
                                @if($website)
                                    <a href="{{ str_starts_with($website, 'http') ? $website : 'https://'.$website }}" target="_blank" class="btn btn-xs btn-outline btn-neutral gap-1">
                                        <x-mary-icon name="o-globe-alt" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Website</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </x-mary-card>
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.organization')) }}" shadow separator>
                <div class="grid gap-y-5">
                    <div class="flex flex-row gap-5">
                        <x-mary-icon name="fas.building" />
                        <span>
                        @if($lead->organization)<a href="{{ route('laravel-crm.organizations.show',$lead->organization) }}">{{ $lead->organization->name }}</a>@endif
                        </span>
                    </div>
                    <div class="flex flex-row gap-5">
                        <x-mary-icon name="fas.map-marker" />
                        <span>
                        {{ ($address) ? \VentureDrake\LaravelCrm\Http\Helpers\AddressLine\addressSingleLine($address) : null }}
                        </span>
                    </div>
                </div>
            </x-mary-card>
            @if($companySummary && $companySummary['other_leads']->isNotEmpty())
                <x-mary-card title="Colleagues at {{ $lead->organization->name }} ({{ $companySummary['other_leads']->count() }})" shadow separator>
                    <div class="space-y-2.5">
                        @foreach($companySummary['other_leads'] as $otherLead)
                            <div class="flex items-center justify-between p-2.5 rounded-lg border border-base-200 bg-base-100 hover:bg-base-200/50 transition-colors gap-2">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('laravel-crm.leads.show', $otherLead) }}" class="font-bold text-xs hover:text-primary hover:underline">
                                            {{ $otherLead->person?->name ?: $otherLead->title }}
                                        </a>
                                        @if($otherLead->person && $otherLead->title !== $otherLead->person->name)
                                            <span class="text-[11px] text-base-content/60">· {{ $otherLead->title }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] text-base-content/60">
                                        <span>Owner: {{ $otherLead->ownerUser?->name ?: 'Unallocated' }}</span>
                                        @if($otherLead->amount)
                                            <span>· {{ money($otherLead->amount, $otherLead->currency) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @php
                                        $otherRelayBadge = $otherLead->relay_badge;
                                    @endphp
                                    <x-mary-badge :value="$otherRelayBadge['label']" :class="$otherRelayBadge['class'].' badge-xs'" />
                                    @if($otherLead->pipelineStage)
                                        <x-mary-badge :value="$otherLead->pipelineStage->name" class="badge-xs badge-neutral text-white" />
                                    @endif
                                    <x-mary-button icon="o-arrow-top-right-on-square" link="{{ route('laravel-crm.leads.show', $otherLead) }}" class="btn-xs btn-ghost btn-square" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-mary-card>
            @endif
        </div>
        <div>
            <livewire:crm-activity-tabs :model="$lead" />
        </div>
    </div>

    {{-- PLAYBOOK & OBJECTION HANDLING MODAL --}}
    <x-mary-modal id="modalPlaybook{{ $lead->id }}" class="backdrop-blur-xs" box-class="max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="space-y-6">
            {{-- MODAL HEADER --}}
            <div class="flex items-start justify-between border-b border-base-200 pb-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-mary-icon name="o-bolt" class="w-6 h-6 text-warning" />
                        <h3 class="text-lg font-bold">Institutional Sales Playbook V2</h3>
                        <x-mary-badge value="DUB-REV-SALES-MOTIONS-V2-002" class="badge-neutral text-white text-xs" />
                    </div>
                    <p class="text-xs text-base-content/70">
                        <strong>Guiding Principle:</strong> Peer Engineer to Peer Quant. No exclamation marks, no sales tropes, direct code/data fulfillment.
                    </p>
                    <p class="text-xs text-primary font-medium">
                        Prospect: <strong>{{ $this->lead->person?->name ?: $this->lead->title }}</strong> · Organization: <strong>{{ $this->lead->organization?->name ?: 'Not linked' }}</strong>
                    </p>
                </div>
            </div>

            {{-- 1. LINKEDIN CONNECTION NOTES --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-sm uppercase tracking-wider text-base-content/80 flex items-center gap-1.5">
                        <x-mary-icon name="o-link" class="w-4 h-4 text-primary" />
                        1. LinkedIn Connection Notes (&lt; 300 Characters)
                    </h4>
                    <span class="text-xs text-base-content/60">Strictly character-capped, peer-to-peer open ended</span>
                </div>

                <div class="grid md:grid-cols-3 gap-3">
                    @foreach(['connection_angle_a', 'connection_angle_b', 'connection_angle_c'] as $key)
                        @php
                            $tmpl = $pb['templates'][$key];
                        @endphp
                        <div class="p-3 rounded-xl border {{ $tmpl['is_recommended'] ? 'border-warning/80 bg-warning/5 ring-1 ring-warning/30' : 'border-base-300 bg-base-100' }} flex flex-col justify-between gap-3 text-xs">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="font-bold">{{ $tmpl['angle_label'] }}</span>
                                    @if($tmpl['is_recommended'])
                                        <x-mary-badge value="RECOMMENDED" class="badge-warning text-neutral-900 badge-xs font-bold" />
                                    @endif
                                </div>
                                <div class="p-2 rounded bg-base-200/60 font-mono text-[11px] leading-relaxed text-base-content/90 select-all" id="tmpl-{{ $key }}-{{ $lead->id }}">
                                    {{ $tmpl['body'] }}
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-base-200 text-[11px]">
                                <span class="font-mono {{ $tmpl['is_valid_length'] ? 'text-success' : 'text-error font-bold' }}">
                                    {{ $tmpl['char_count'] }} / 300 chars
                                </span>
                                <div class="flex items-center gap-1">
                                    <button type="button" 
                                            onclick="navigator.clipboard.writeText(document.getElementById('tmpl-{{ $key }}-{{ $lead->id }}').innerText.trim()); alert('Copied to clipboard!')" 
                                            class="btn btn-xs btn-outline">
                                        Copy
                                    </button>
                                    <x-mary-button label="+ Task" wire:click="createStageTask('{{ $key }}')" class="btn-xs btn-primary text-white" spinner="createStageTask" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 2. CONVERSATIONAL CADENCE / SCENARIOS --}}
            <div class="space-y-3 pt-3 border-t border-base-200">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-sm uppercase tracking-wider text-base-content/80 flex items-center gap-1.5">
                        <x-mary-icon name="o-chat-bubble-left-right" class="w-4 h-4 text-info" />
                        2. Conversational Flow (Event-Driven Steps)
                    </h4>
                    <span class="text-xs text-base-content/60">Triggered by prospect behavior, not arbitrary calendars</span>
                </div>

                <div class="grid md:grid-cols-2 gap-3">
                    @foreach(['scenario_1', 'scenario_2', 'scenario_3', 'scenario_4', 'scenario_5', 'call_transition'] as $key)
                        @php
                            $tmpl = $pb['templates'][$key];
                        @endphp
                        <div class="p-3 rounded-xl border border-base-300 bg-base-100 flex flex-col justify-between gap-3 text-xs">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="font-bold">{{ $tmpl['angle_label'] }}</span>
                                    <x-mary-badge :value="'+'.$tmpl['due_in_days'].'d delay'" class="badge-neutral text-white badge-xs" />
                                </div>
                                <div class="p-2 rounded bg-base-200/60 font-mono text-[11px] leading-relaxed whitespace-pre-line text-base-content/90 select-all" id="tmpl-{{ $key }}-{{ $lead->id }}">
{{ $tmpl['body'] }}
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-base-200 text-[11px]">
                                <span class="text-base-content/60">Target: {{ $tmpl['target_stage'] }}</span>
                                <div class="flex items-center gap-1">
                                    <button type="button" 
                                            onclick="navigator.clipboard.writeText(document.getElementById('tmpl-{{ $key }}-{{ $lead->id }}').innerText.trim()); alert('Copied to clipboard!')" 
                                            class="btn btn-xs btn-outline">
                                        Copy
                                    </button>
                                    <x-mary-button label="+ Task" wire:click="createStageTask('{{ $key }}')" class="btn-xs btn-primary text-white" spinner="createStageTask" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 3. INSTITUTIONAL OBJECTION HANDLING --}}
            <div class="space-y-3 pt-3 border-t border-base-200">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-sm uppercase tracking-wider text-base-content/80 flex items-center gap-1.5">
                        <x-mary-icon name="o-shield-check" class="w-4 h-4 text-secondary" />
                        3. Institutional Objection Handling (Unflappable & Technical)
                    </h4>
                    <span class="text-xs text-base-content/60">Peer engineer rebuttal scripts</span>
                </div>

                <div class="space-y-2.5">
                    @foreach(['objection_inhouse', 'objection_wash_trading', 'objection_pricing', 'objection_equities', 'objection_lookahead'] as $key)
                        @php
                            $tmpl = $pb['templates'][$key];
                        @endphp
                        <div class="p-3 rounded-xl border border-base-300 bg-base-100 space-y-2 text-xs">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-primary">{{ $tmpl['angle_label'] }}</span>
                                <div class="flex items-center gap-1">
                                    <button type="button" 
                                            onclick="navigator.clipboard.writeText(document.getElementById('tmpl-{{ $key }}-{{ $lead->id }}').innerText.trim()); alert('Copied objection response to clipboard!')" 
                                            class="btn btn-xs btn-outline">
                                        Copy Response
                                    </button>
                                    <x-mary-button label="+ Task" wire:click="createStageTask('{{ $key }}')" class="btn-xs btn-secondary text-white" spinner="createStageTask" />
                                </div>
                            </div>
                            <div class="p-2.5 rounded bg-base-200/60 font-mono text-[11px] leading-relaxed text-base-content/90 select-all" id="tmpl-{{ $key }}-{{ $lead->id }}">
                                {{ $tmpl['body'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Close Playbook" onclick="modalPlaybook{{ $lead->id }}.close()" class="btn" />
        </x-slot:actions>
    </x-mary-modal>
</div>
