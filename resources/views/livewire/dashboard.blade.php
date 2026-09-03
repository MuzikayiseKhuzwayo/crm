<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.dashboard')) }}" progress-indicator>
        <x-slot:middle class="justify-end gap-2 items-center">
            <span class="badge badge-neutral badge-sm font-mono px-3 py-2.5 hidden sm:inline-flex gap-1.5 items-center">
                <x-mary-icon name="o-calendar" class="w-3.5 h-3.5 text-primary" />
                <span>{{ $this->periodLabel }}</span>
            </span>
        </x-slot:middle>

        <x-slot:actions>
            <div class="flex items-center gap-2">
                <select wire:model.live="period" class="select select-primary select-sm font-semibold">
                    <option value="today">{{ ucfirst(__('laravel-crm::lang.today')) }}</option>
                    <option value="yesterday">{{ ucfirst(__('laravel-crm::lang.yesterday')) }}</option>
                    <option value="last_7_days">{{ __('laravel-crm::lang.last_x_days', ['days' => 7]) }}</option>
                    <option value="last_30_days">Last 30 Days</option>
                    <option value="this_month">{{ ucfirst(__('laravel-crm::lang.this_month')) }}</option>
                    <option value="last_month">{{ ucfirst(__('laravel-crm::lang.last_month')) }}</option>
                    <option value="this_quarter">{{ ucfirst(__('laravel-crm::lang.this_quarter')) }}</option>
                    <option value="last_quarter">{{ ucfirst(__('laravel-crm::lang.last_quarter')) }}</option>
                    <option value="this_year">{{ ucfirst(__('laravel-crm::lang.this_year')) }}</option>
                    <option value="last_year">{{ ucfirst(__('laravel-crm::lang.last_year')) }}</option>
                    <option value="all_time">{{ ucfirst(__('laravel-crm::lang.all_time')) }}</option>
                </select>
            </div>
        </x-slot:actions>
    </x-mary-header>

    {{-- KPI STATS ROW 1 --}}
    <div class="grid lg:grid-cols-4 gap-4 mb-6">
        @hasleadsenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.new_leads')) }}"
                value="{{ $this->totalLeadsCount }}"
                icon="o-bolt"
                color="text-primary"
                description="{{ $this->convertedLeadsCount }} {{ __('laravel-crm::lang.converted') }} ({{ $this->conversionRate }}%)"
                class="shadow-sm"
            />
        @endhasleadsenabled

        @hasdealsenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.pipeline')) }} {{ __('laravel-crm::lang.value') }}"
                value="{{ money($this->openDealsValue, $this->getCurrency()) }}"
                icon="o-chart-bar"
                color="text-secondary"
                description="{{ $this->openDealsCount }} {{ __('laravel-crm::lang.open') }} {{ strtolower(__('laravel-crm::lang.deals')) }}"
                class="shadow-sm"
            />
        @endhasdealsenabled

        @hasdealsenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.deals')) }} {{ ucfirst(__('laravel-crm::lang.won')) }}"
                value="{{ money($this->wonDealsValue, $this->getCurrency()) }}"
                icon="o-trophy"
                color="text-success"
                description="{{ $this->wonDealsCount }} {{ strtolower(__('laravel-crm::lang.deals')) }} {{ strtolower(__('laravel-crm::lang.won')) }}"
                class="shadow-sm"
            />
        @endhasdealsenabled

        @hasinvoicesenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.outstanding')) }}"
                value="{{ money($this->invoicesOutstandingValue, $this->getCurrency()) }}"
                icon="o-clock"
                color="text-warning"
                description="{{ $this->invoicesOutstandingCount }} {{ strtolower(__('laravel-crm::lang.unpaid')) }} {{ strtolower(__('laravel-crm::lang.invoices')) }}"
                class="shadow-sm"
            />
        @endhasinvoicesenabled
    </div>

    {{-- KPI STATS ROW 2 --}}
    <div class="grid lg:grid-cols-4 gap-4 mb-6">
        @hasinvoicesenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.invoices')) }} {{ ucfirst(__('laravel-crm::lang.paid')) }}"
                value="{{ money($this->invoicesPaidValue, $this->getCurrency()) }}"
                icon="o-banknotes"
                color="text-success"
                description="{{ $this->invoicesPaidCount }} {{ strtolower(__('laravel-crm::lang.invoices')) }}"
                class="shadow-sm"
            />
        @endhasinvoicesenabled

        @hasquotesenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.quotes')) }}"
                value="{{ $this->quotesCount }}"
                icon="o-document-text"
                color="text-primary"
                description="{{ money($this->quotesValue, $this->getCurrency()) }} {{ strtolower(__('laravel-crm::lang.total')) }}"
                class="shadow-sm"
            />
        @endhasquotesenabled

        @hasordersenabled
            <x-mary-stat
                title="{{ ucfirst(__('laravel-crm::lang.orders')) }}"
                value="{{ $this->ordersCount }}"
                icon="o-shopping-cart"
                color="text-accent"
                description="{{ money($this->ordersValue, $this->getCurrency()) }} {{ strtolower(__('laravel-crm::lang.total')) }}"
                class="shadow-sm"
            />
        @endhasordersenabled

        <x-mary-stat
            title="{{ ucfirst(__('laravel-crm::lang.new')) }} {{ ucfirst(__('laravel-crm::lang.contacts')) }}"
            value="{{ $this->newPeopleCount + $this->newOrganizationsCount }}"
            icon="o-users"
            color="text-info"
            description="{{ $this->newPeopleCount }} {{ strtolower(__('laravel-crm::lang.people')) }}, {{ $this->newOrganizationsCount }} {{ strtolower(__('laravel-crm::lang.organizations')) }}"
            class="shadow-sm"
        />
    </div>

    {{-- CHARTS ROW 1: Revenue + Pipeline --}}
    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        @hasinvoicesenabled
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.revenue')) }}" shadow separator>
                <div class="h-72">
                    <x-mary-chart wire:model="revenueChart" class="!h-full" />
                </div>
            </x-mary-card>
        @endhasinvoicesenabled

        @hasdealsenabled
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.deals')) }} {{ ucfirst(__('laravel-crm::lang.pipeline')) }}" shadow separator>
                <div class="h-72">
                    <x-mary-chart wire:model="pipelineChart" class="!h-full" />
                </div>
            </x-mary-card>
        @endhasdealsenabled
    </div>

    {{-- CHARTS ROW 2: Leads vs Deals + Deal Status --}}
    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        @hasleadsenabled
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.leads')) }} vs {{ ucfirst(__('laravel-crm::lang.deals')) }}" shadow separator>
                <div class="h-72">
                    <x-mary-chart wire:model="leadsVsDealsChart" class="!h-full" />
                </div>
            </x-mary-card>
        @endhasleadsenabled

        @hasdealsenabled
            <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.deal')) }} {{ ucfirst(__('laravel-crm::lang.status')) }}" shadow separator>
                <div class="h-72">
                    <x-mary-chart wire:model="dealStatusChart" class="!h-full" />
                </div>
            </x-mary-card>
        @endhasdealsenabled
    </div>

    {{-- BOTTOM ROW: Actionable Attention Cards with Clickable Instance Links --}}
    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        {{-- Upcoming & Overdue Tasks --}}
        <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.upcoming')) }} {{ ucfirst(__('laravel-crm::lang.tasks')) }}" shadow separator>
            @if($this->overdueTasksCount > 0)
                <x-mary-alert icon="o-exclamation-triangle" class="alert-warning mb-4 shadow-xs">
                    <span class="font-bold">{{ $this->overdueTasksCount }} {{ strtolower(__('laravel-crm::lang.overdue')) }} {{ strtolower(__('laravel-crm::lang.tasks')) }}</span> requiring immediate action.
                </x-mary-alert>
            @endif

            <div class="divide-y divide-base-200">
                @forelse($this->upcomingTasks as $task)
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="o-clipboard-document-check" class="w-5 h-5 text-primary shrink-0" />
                            <div>
                                {{-- Clickable Task Link --}}
                                <a href="{{ route('laravel-crm.tasks.show', $task) }}" class="font-bold text-sm link link-hover link-primary">
                                    {{ $task->name }}
                                </a>
                                @if($task->taskable)
                                    @php
                                        $type = class_basename($task->taskable_type);
                                        $taskableRoute = match($type) {
                                            'Lead' => route('laravel-crm.leads.show', $task->taskable),
                                            'Deal' => route('laravel-crm.deals.show', $task->taskable),
                                            'Person' => route('laravel-crm.people.show', $task->taskable),
                                            'Organization' => route('laravel-crm.organizations.show', $task->taskable),
                                            'Quote' => route('laravel-crm.quotes.show', $task->taskable),
                                            default => null
                                        };
                                    @endphp
                                    <div class="text-xs text-base-content/60">
                                        @if($taskableRoute)
                                            <a href="{{ $taskableRoute }}" class="link link-hover text-base-content/70">
                                                {{ $type }}: {{ $task->taskable->title ?? $task->taskable->name }}
                                            </a>
                                        @else
                                            <span>{{ $type }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($task->due_at)
                                <span class="badge badge-sm {{ $task->due_at->isPast() ? 'badge-error text-white font-bold' : 'badge-neutral' }}">
                                    {{ $task->due_at->diffForHumans() }}
                                </span>
                            @endif
                            <x-mary-button link="{{ route('laravel-crm.tasks.show', $task) }}" icon="o-arrow-right" class="btn-ghost btn-xs btn-square" />
                        </div>
                    </div>
                @empty
                    <div class="text-base-content/50 text-sm py-4 text-center">
                        {{ ucfirst(__('laravel-crm::lang.no')) }} {{ strtolower(__('laravel-crm::lang.upcoming')) }} {{ strtolower(__('laravel-crm::lang.tasks')) }}
                    </div>
                @endforelse
            </div>

            @if(count($this->upcomingTasks) > 0)
                <x-slot:actions>
                    <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.view_all')) }}" link="{{ route('laravel-crm.tasks.index') }}" icon="o-arrow-right" class="btn-ghost btn-sm" />
                </x-slot:actions>
            @endif
        </x-mary-card>

        {{-- Unpaid / Outstanding Invoices --}}
        @hasinvoicesenabled
            <x-mary-card title="Invoices Needing Payment" shadow separator>
                <div class="divide-y divide-base-200">
                    @forelse($this->attentionInvoices as $invoice)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="o-banknotes" class="w-5 h-5 text-warning shrink-0" />
                                <div>
                                    {{-- Clickable Invoice Link --}}
                                    <a href="{{ route('laravel-crm.invoices.show', $invoice) }}" class="font-bold text-sm link link-hover link-primary">
                                        Invoice {{ $invoice->invoice_id ?: '#'.$invoice->id }}
                                    </a>
                                    <div class="text-xs text-base-content/60">
                                        @if($invoice->person)
                                            <a href="{{ route('laravel-crm.people.show', $invoice->person) }}" class="link link-hover">
                                                {{ $invoice->person->name }}
                                            </a>
                                        @elseif($invoice->organization)
                                            <a href="{{ route('laravel-crm.organizations.show', $invoice->organization) }}" class="link link-hover">
                                                {{ $invoice->organization->name }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-sm text-warning">
                                    {{ money($invoice->amount_due, $invoice->currency) }}
                                </div>
                                <div class="text-[11px] text-base-content/50">
                                    {{ $invoice->due_date ? 'Due '.$invoice->due_date->format('M j') : 'Unpaid' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-base-content/50 text-sm py-4 text-center">
                            All invoices are fully paid! 🎉
                        </div>
                    @endforelse
                </div>

                @if(count($this->attentionInvoices) > 0)
                    <x-slot:actions>
                        <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.view_all')) }}" link="{{ route('laravel-crm.invoices.index') }}" icon="o-arrow-right" class="btn-ghost btn-sm" />
                    </x-slot:actions>
                @endif
            </x-mary-card>
        @endhasinvoicesenabled
    </div>

    {{-- ATTENTION ROW 2: Open Leads + Recent Activity --}}
    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Open Leads Needing Action --}}
        @hasleadsenabled
            <x-mary-card title="Leads Needing Action" shadow separator>
                <div class="divide-y divide-base-200">
                    @forelse($this->attentionLeads as $lead)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="o-funnel" class="w-5 h-5 text-primary shrink-0" />
                                <div>
                                    {{-- Clickable Lead Link --}}
                                    <a href="{{ route('laravel-crm.leads.show', $lead) }}" class="font-bold text-sm link link-hover link-primary">
                                        {{ $lead->title }}
                                    </a>
                                    <div class="flex items-center gap-2 text-xs text-base-content/60 mt-0.5">
                                        @if($lead->lead_id)
                                            <span class="badge badge-xs badge-neutral">{{ $lead->lead_id }}</span>
                                        @endif
                                        @if($lead->person)
                                            <a href="{{ route('laravel-crm.people.show', $lead->person) }}" class="link link-hover">
                                                {{ $lead->person->name }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                @if($lead->amount)
                                    <div class="font-bold text-sm">
                                        {{ money($lead->amount, $lead->currency) }}
                                    </div>
                                @endif
                                <div class="text-[11px] text-base-content/50">
                                    {{ $lead->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-base-content/50 text-sm py-4 text-center">
                            No open leads created during this period.
                        </div>
                    @endforelse
                </div>

                @if(count($this->attentionLeads) > 0)
                    <x-slot:actions>
                        <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.view_all')) }}" link="{{ route('laravel-crm.leads.index') }}" icon="o-arrow-right" class="btn-ghost btn-sm" />
                    </x-slot:actions>
                @endif
            </x-mary-card>
        @endhasleadsenabled

        {{-- Recent Activity Feed with Clickable Record Links --}}
        <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.recent')) }} {{ ucfirst(__('laravel-crm::lang.activity')) }}" shadow separator>
            <div class="divide-y divide-base-200">
                @forelse($this->recentActivities as $activity)
                    <div class="flex items-start gap-3 py-3">
                        @php
                            $iconMap = [
                                'created' => 'o-plus-circle',
                                'updated' => 'o-pencil',
                                'deleted' => 'o-trash',
                            ];
                            $activityIcon = $iconMap[$activity->description ?? ''] ?? 'o-information-circle';
                        @endphp
                        <x-mary-icon name="{{ $activityIcon }}" class="w-5 h-5 text-primary mt-0.5 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm space-x-1">
                                {{-- User Link --}}
                                @if($activity->causeable)
                                    <a href="{{ route('laravel-crm.users.show', $activity->causeable) }}" class="font-bold link link-hover link-primary">
                                        {{ $activity->causeable->name }}
                                    </a>
                                @else
                                    <span class="font-semibold">{{ ucfirst(__('laravel-crm::lang.system')) }}</span>
                                @endif

                                <span class="text-base-content/60">
                                    {{ $activity->description ?? 'action' }}
                                </span>

                                {{-- Clickable Target Record Link --}}
                                @if($activity->recordable)
                                    @php
                                        $type = class_basename($activity->recordable_type);
                                        $recordRoute = match($type) {
                                            'Lead' => route('laravel-crm.leads.show', $activity->recordable),
                                            'Deal' => route('laravel-crm.deals.show', $activity->recordable),
                                            'Person' => route('laravel-crm.people.show', $activity->recordable),
                                            'Organization' => route('laravel-crm.organizations.show', $activity->recordable),
                                            'Quote' => route('laravel-crm.quotes.show', $activity->recordable),
                                            'Invoice' => route('laravel-crm.invoices.show', $activity->recordable),
                                            'Order' => route('laravel-crm.orders.show', $activity->recordable),
                                            'Task' => route('laravel-crm.tasks.show', $activity->recordable),
                                            default => null
                                        };
                                        $title = $activity->recordable->title ?? $activity->recordable->name ?? ($activity->recordable->lead_id ?? $activity->recordable->id);
                                    @endphp
                                    @if($recordRoute)
                                        <a href="{{ $recordRoute }}" class="font-bold link link-hover link-primary">
                                            {{ $type }} — {{ Str::limit($title, 35) }}
                                        </a>
                                    @else
                                        <span class="font-semibold">{{ $type }}</span>
                                    @endif
                                @endif
                            </div>
                            <div class="text-xs text-base-content/40 mt-0.5">
                                {{ $activity->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-base-content/50 text-sm py-4 text-center">
                        {{ ucfirst(__('laravel-crm::lang.no')) }} {{ strtolower(__('laravel-crm::lang.recent')) }} {{ strtolower(__('laravel-crm::lang.activity')) }}
                    </div>
                @endforelse
            </div>
        </x-mary-card>
    </div>
</div>
