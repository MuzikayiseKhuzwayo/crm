<x-mary-card title="{{ ucfirst(__('laravel-crm::lang.leads')) }} ({{ $this->leads->count() }})" shadow separator>
    <x-slot:menu>
        @can('create crm leads')
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.create_lead')) }}" 
                           link="{{ route('laravel-crm.leads.create', ['organization_id' => $model->id]) }}" 
                           icon="o-plus" 
                           class="btn-xs btn-outline btn-primary" />
        @endcan
    </x-slot:menu>

    @if($this->leads->isNotEmpty())
        <div class="space-y-3">
            @foreach($this->leads as $lead)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded-lg border border-base-200 bg-base-100 hover:bg-base-200/50 transition-colors gap-2">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-primary">{{ $lead->lead_id }}</span>
                            <a href="{{ route('laravel-crm.leads.show', $lead) }}" class="font-bold text-xs hover:text-primary hover:underline">
                                {{ $lead->title }}
                            </a>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/70">
                            @if($lead->person)
                                <a href="{{ route('laravel-crm.people.show', $lead->person) }}" class="inline-flex items-center gap-1 text-info hover:underline">
                                    <x-mary-icon name="o-user" class="w-3.5 h-3.5" style="width:14px;height:14px;" />
                                    <span>{{ $lead->person->name }}</span>
                                </a>
                            @endif
                            @if($lead->ownerUser)
                                <span class="inline-flex items-center gap-1">
                                    <x-mary-icon name="o-user-circle" class="w-3.5 h-3.5" style="width:14px;height:14px;" />
                                    <span>{{ $lead->ownerUser->name }}</span>
                                </span>
                            @endif
                            @if($lead->amount)
                                <span class="font-semibold text-success">
                                    {{ money($lead->amount, $lead->currency) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if($lead->pipelineStage)
                            <x-mary-badge :value="$lead->pipelineStage->name" class="badge-sm badge-neutral text-white" />
                        @endif
                        <x-mary-button icon="o-eye" link="{{ route('laravel-crm.leads.show', $lead) }}" class="btn-xs btn-ghost btn-square" />
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-xs text-base-content/50 py-3 text-center">
            No leads recorded for this organization yet.
        </div>
    @endif
</x-mary-card>
