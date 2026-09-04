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

            <div class="pt-3 border-t border-base-200 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-mary-icon name="o-bolt" class="w-4 h-4 text-warning shrink-0" />
                    <span class="text-xs font-bold text-base-content/70 uppercase">Quick Stage Automation Tasks:</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-mary-button label="+ Task: Connection Request" wire:click="createStageTask('connection_request')" icon="o-plus" class="btn-xs btn-outline btn-primary" spinner="createStageTask" />
                    <x-mary-button label="+ Task: Send Intro DM" wire:click="createStageTask('intro_dm')" icon="o-plus" class="btn-xs btn-outline btn-info" spinner="createStageTask" />
                    <x-mary-button label="+ Task: Schedule Call" wire:click="createStageTask('schedule_call')" icon="o-plus" class="btn-xs btn-outline btn-warning" spinner="createStageTask" />
                    <x-mary-button label="+ Task: Send Proposal" wire:click="createStageTask('send_proposal')" icon="o-plus" class="btn-xs btn-outline btn-secondary" spinner="createStageTask" />
                    <x-mary-button label="+ Task: Follow Up" wire:click="createStageTask('follow_up')" icon="o-plus" class="btn-xs btn-outline btn-neutral" spinner="createStageTask" />
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
        </div>
        <div>
            <livewire:crm-activity-tabs :model="$lead" />
        </div>
    </div>
</div>
