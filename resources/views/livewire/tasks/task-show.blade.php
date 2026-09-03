<div class="crm-content">
    {{-- HEADER --}}
    <x-crm-header title="{{ $task->name }}" class="mb-5" progress-indicator>
        {{-- BADGES --}}
        <x-slot:badges>
            @if($task->completed_at)
                <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.completed')) }}" class="badge badge-success text-white" />
            @else
                @if($task->due_at && $task->due_at->isPast())
                    <x-mary-badge value="Overdue" class="badge badge-error text-white font-bold" />
                @else
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.pending')) }}" class="badge badge-neutral" />
                @endif
            @endif
        </x-slot:badges>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back_to_tasks')) }}" link="{{ url(route('laravel-crm.tasks.index')) }}" icon="fas.angle-double-left" class="btn-sm btn-outline" responsive />
            @can('edit crm tasks')
                @if(! $task->completed_at)
                    | <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.complete')) }}" wire:click="complete" class="btn-sm btn-success text-white" spinner="complete" responsive />
                @endif
                <x-mary-button link="{{ url(route('laravel-crm.tasks.edit', $task)) }}" icon="o-pencil-square" class="btn-sm btn-square btn-outline" responsive />
            @endcan
            @can('delete crm tasks')
                <x-mary-button onclick="modalDeleteTask{{ $task->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner />
                <x-crm-delete-confirm model="task" id="{{ $task->id }}" />
            @endcan
        </x-slot:actions>
    </x-crm-header>

    <div class="grid lg:grid-cols-2 gap-5 items-start">
        {{-- LEFT COLUMN: TASK DETAILS & EVERYWHERE ENTITY LINKS --}}
        <div class="grid gap-y-5">
            {{-- DUE DATE & OVERDUE ALERT BANNER --}}
            @if(! $task->completed_at && $task->due_at && $task->due_at->isPast())
                <div class="alert alert-error shadow-sm rounded-xl text-white flex items-center gap-3">
                    <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6 shrink-0" />
                    <div>
                        <h4 class="font-bold text-sm">Task Overdue</h4>
                        <p class="text-xs opacity-90">This task was due {{ $task->due_at->diffForHumans() }} ({{ $task->due_at->format('M j, Y g:i A') }}).</p>
                    </div>
                </div>
            @endif

            {{-- TASK DETAILS CARD --}}
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.details')) }}" shadow separator>
                <div class="grid gap-y-4">
                    @if($task->description)
                        <div>
                            <span class="text-xs font-bold text-base-content/70 uppercase tracking-wider block mb-1">{{ ucfirst(__('laravel-crm::lang.description')) }}</span>
                            <div class="prose prose-sm max-w-none text-base-content whitespace-pre-line leading-relaxed bg-base-200/40 p-3.5 rounded-xl border border-base-200">
                                {!! nl2br(e($task->description)) !!}
                            </div>
                        </div>
                    @endif

                    <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-base-200">
                        <div>
                            <span class="text-xs font-bold text-base-content/60 block mb-0.5">{{ ucfirst(__('laravel-crm::lang.created')) }}</span>
                            <span class="text-sm font-medium">{{ $task->created_at->diffForHumans() }} ({{ $task->created_at->format('M j, Y') }})</span>
                        </div>
                        @if($task->start_at)
                            <div>
                                <span class="text-xs font-bold text-base-content/60 block mb-0.5">{{ ucfirst(__('laravel-crm::lang.start_at')) }}</span>
                                <span class="text-sm font-medium">{{ $task->start_at->diffForHumans() }}</span>
                            </div>
                        @endif
                        @if($task->due_at)
                            <div>
                                <span class="text-xs font-bold text-base-content/60 block mb-0.5">{{ ucfirst(__('laravel-crm::lang.due_date')) }}</span>
                                <span class="text-sm font-medium {{ ($task->due_at->isPast() && !$task->completed_at) ? 'text-error font-bold' : '' }}">
                                    {{ $task->due_at->diffForHumans() }} ({{ $task->due_at->format('M j, Y g:i A') }})
                                </span>
                            </div>
                        @endif
                        @if($task->completed_at)
                            <div>
                                <span class="text-xs font-bold text-base-content/60 block mb-0.5">{{ ucfirst(__('laravel-crm::lang.completed')) }}</span>
                                <span class="text-sm font-medium text-success font-semibold">{{ $task->completed_at->diffForHumans() }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-base-200">
                        <div>
                            <span class="text-xs font-bold text-base-content/60 block mb-1">{{ ucfirst(__('laravel-crm::lang.owner')) }}</span>
                            @if($task->ownerUser)
                                <a href="{{ route('laravel-crm.users.show', $task->ownerUser) }}" class="inline-flex items-center gap-2 link link-hover link-primary text-sm font-medium">
                                    <x-mary-icon name="fas.user-circle" class="w-4 h-4 text-primary" />
                                    <span>{{ $task->ownerUser->name }}</span>
                                </a>
                            @else
                                <span class="text-sm text-base-content/50">{{ ucfirst(__('laravel-crm::lang.unallocated')) }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-xs font-bold text-base-content/60 block mb-1">{{ ucfirst(__('laravel-crm::lang.assigned_to')) }}</span>
                            @if($task->assignedToUser)
                                <a href="{{ route('laravel-crm.users.show', $task->assignedToUser) }}" class="inline-flex items-center gap-2 link link-hover link-primary text-sm font-medium">
                                    <x-mary-icon name="fas.user-check" class="w-4 h-4 text-success" />
                                    <span>{{ $task->assignedToUser->name }}</span>
                                </a>
                            @else
                                <span class="text-sm text-base-content/50">{{ ucfirst(__('laravel-crm::lang.unallocated')) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-mary-card>

            {{-- ORIGIN ENTITY ("EVERYWHERE LINK") CARD --}}
            @if($task->taskable)
                @php
                    $entity = $task->taskable;
                    $entityType = class_basename(get_class($entity));
                @endphp
                <x-mary-card title="Associated CRM Record" shadow separator>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="badge badge-primary text-white font-bold text-xs uppercase">{{ $entityType }}</span>
                            <span class="text-xs text-base-content/50">Linked Entity</span>
                        </div>

                        {{-- LEAD ENTITY --}}
                        @if($entityType === 'Lead')
                            <div class="p-3.5 bg-base-200/50 rounded-xl border border-base-200 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="{{ route('laravel-crm.leads.show', $entity) }}" class="text-base font-bold link link-hover link-primary flex items-center gap-2">
                                        <x-mary-icon name="o-funnel" class="w-5 h-5 text-primary" />
                                        <span>{{ $entity->title }}</span>
                                    </a>
                                    @if($entity->lead_id)
                                        <span class="badge badge-sm badge-neutral">{{ $entity->lead_id }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                                    @if($entity->pipelineStage)
                                        <span class="badge badge-xs badge-info text-white">{{ $entity->pipelineStage->name }}</span>
                                    @endif
                                    @if($entity->amount)
                                        <span class="font-bold text-base-content">{{ money($entity->amount, $entity->currency) }}</span>
                                    @endif
                                </div>
                            </div>
                        {{-- DEAL ENTITY --}}
                        @elseif($entityType === 'Deal')
                            <div class="p-3.5 bg-base-200/50 rounded-xl border border-base-200 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="{{ route('laravel-crm.deals.show', $entity) }}" class="text-base font-bold link link-hover link-primary flex items-center gap-2">
                                        <x-mary-icon name="o-briefcase" class="w-5 h-5 text-primary" />
                                        <span>{{ $entity->title }}</span>
                                    </a>
                                    @if($entity->deal_id)
                                        <span class="badge badge-sm badge-neutral">{{ $entity->deal_id }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                                    @if($entity->pipelineStage)
                                        <span class="badge badge-xs badge-info text-white">{{ $entity->pipelineStage->name }}</span>
                                    @endif
                                    @if($entity->amount)
                                        <span class="font-bold text-base-content">{{ money($entity->amount, $entity->currency) }}</span>
                                    @endif
                                </div>
                            </div>
                        {{-- PERSON ENTITY --}}
                        @elseif($entityType === 'Person')
                            <div class="p-3.5 bg-base-200/50 rounded-xl border border-base-200 space-y-2">
                                <a href="{{ route('laravel-crm.people.show', $entity) }}" class="text-base font-bold link link-hover link-primary flex items-center gap-2">
                                    <x-mary-icon name="fas.user-circle" class="w-5 h-5 text-primary" />
                                    <span>{{ $entity->name }}</span>
                                </a>
                            </div>
                        {{-- ORGANIZATION ENTITY --}}
                        @elseif($entityType === 'Organization')
                            <div class="p-3.5 bg-base-200/50 rounded-xl border border-base-200 space-y-2">
                                <a href="{{ route('laravel-crm.organizations.show', $entity) }}" class="text-base font-bold link link-hover link-primary flex items-center gap-2">
                                    <x-mary-icon name="fas.building" class="w-5 h-5 text-primary" />
                                    <span>{{ $entity->name }}</span>
                                </a>
                            </div>
                        {{-- QUOTE ENTITY --}}
                        @elseif($entityType === 'Quote')
                            <div class="p-3.5 bg-base-200/50 rounded-xl border border-base-200 space-y-2">
                                <a href="{{ route('laravel-crm.quotes.show', $entity) }}" class="text-base font-bold link link-hover link-primary flex items-center gap-2">
                                    <x-mary-icon name="o-document-text" class="w-5 h-5 text-primary" />
                                    <span>Quote {{ $entity->quote_id ?: '#'.$entity->id }}</span>
                                </a>
                            </div>
                        @else
                            <div class="p-3.5 bg-base-200/50 rounded-xl border border-base-200">
                                <span class="font-bold text-sm">{{ $entity->name ?: $entity->title ?: 'Linked Record' }}</span>
                            </div>
                        @endif
                    </div>
                </x-mary-card>
            @endif

            {{-- ASSOCIATED CONTACT PERSON CARD --}}
            @php
                $person = null;
                if ($task->taskable && $task->taskable instanceof \VentureDrake\LaravelCrm\Models\Person) {
                    $person = $task->taskable;
                } elseif ($task->taskable && method_exists($task->taskable, 'person') && $task->taskable->person) {
                    $person = $task->taskable->person;
                }
            @endphp
            @if($person)
                <x-mary-card title="Contact Person" shadow separator>
                    <div class="grid gap-y-3 text-sm">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="fas.user-circle" class="w-4 h-4 text-primary shrink-0" />
                            <a href="{{ route('laravel-crm.people.show', $person) }}" class="link link-hover link-primary font-bold">
                                {{ $person->name }}
                            </a>
                        </div>
                        @if($email = $person->getPrimaryEmail())
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="fas.envelope" class="w-4 h-4 text-base-content/60 shrink-0" />
                                <a href="mailto:{{ $email->address }}" class="link link-hover text-base-content/80">{{ $email->address }}</a>
                            </div>
                        @endif
                        @if($phone = $person->getPrimaryPhone())
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="fas.phone" class="w-4 h-4 text-base-content/60 shrink-0" />
                                <a href="tel:{{ $phone->number }}" class="link link-hover text-base-content/80">{{ $phone->number }}</a>
                            </div>
                        @endif
                        @if($person->linkedin || $person->twitter || $person->website)
                            <div class="pt-2 border-t border-base-200 flex flex-wrap gap-2">
                                @if($person->linkedin)
                                    <a href="{{ str_starts_with($person->linkedin, 'http') ? $person->linkedin : 'https://'.$person->linkedin }}" target="_blank" class="btn btn-xs btn-outline btn-primary gap-1">
                                        <x-mary-icon name="o-link" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>LinkedIn</span>
                                    </a>
                                @endif
                                @if($person->twitter)
                                    <a href="{{ str_starts_with($person->twitter, 'http') ? $person->twitter : 'https://x.com/'.ltrim($person->twitter, '@') }}" target="_blank" class="btn btn-xs btn-outline btn-info gap-1">
                                        <x-mary-icon name="o-hashtag" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Twitter / X</span>
                                    </a>
                                @endif
                                @if($person->website)
                                    <a href="{{ str_starts_with($person->website, 'http') ? $person->website : 'https://'.$person->website }}" target="_blank" class="btn btn-xs btn-outline btn-neutral gap-1">
                                        <x-mary-icon name="o-globe-alt" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Website</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-mary-card>
            @endif

            {{-- ASSOCIATED ORGANIZATION CARD --}}
            @php
                $organization = null;
                if ($task->taskable && $task->taskable instanceof \VentureDrake\LaravelCrm\Models\Organization) {
                    $organization = $task->taskable;
                } elseif ($task->taskable && method_exists($task->taskable, 'organization') && $task->taskable->organization) {
                    $organization = $task->taskable->organization;
                }
            @endphp
            @if($organization)
                <x-mary-card title="Associated Organization" shadow separator>
                    <div class="grid gap-y-3 text-sm">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="fas.building" class="w-4 h-4 text-primary shrink-0" />
                            <a href="{{ route('laravel-crm.organizations.show', $organization) }}" class="link link-hover link-primary font-bold">
                                {{ $organization->name }}
                            </a>
                        </div>
                        @if($organization->website_url)
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="o-globe-alt" class="w-4 h-4 text-base-content/60 shrink-0" />
                                <a href="{{ str_starts_with($organization->website_url, 'http') ? $organization->website_url : 'https://'.$organization->website_url }}" target="_blank" class="link link-hover text-base-content/80">
                                    {{ $organization->website_url }}
                                </a>
                            </div>
                        @endif
                        @if($address = $organization->getPrimaryAddress())
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="fas.map-marker" class="w-4 h-4 text-base-content/60 shrink-0" />
                                <span>{{ \VentureDrake\LaravelCrm\Http\Helpers\AddressLine\addressSingleLine($address) }}</span>
                            </div>
                        @endif
                    </div>
                </x-mary-card>
            @endif

            <x-crm-custom-field-values :model="$task" :group="true" />
        </div>

        {{-- RIGHT COLUMN: TASK PROGRESSION & ACTIVITY / NOTES TABS --}}
        <div>
            <x-mary-card title="Task Activity & Progression" shadow separator>
                <livewire:crm-activity-tabs :model="$task" />
            </x-mary-card>
        </div>
    </div>
</div>
