<?php

namespace VentureDrake\LaravelCrm\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TimelineItem extends Component
{
    public string $uuid;

    public function __construct(
        public string $title,
        public ?string $id = null,
        public ?string $subtitle = null,
        public ?string $description = null,
        public ?string $icon = null,
        public ?bool $pending = false,
        public ?bool $first = false,
        public ?bool $last = false,

        public ?string $connectorPendingClass = 'border-s-base-300',
        public ?string $connectorActiveClass = '!border-s-primary',
        public ?string $bulletActiveClass = '!bg-primary',
        public ?string $bulletPendingClass = 'bg-base-300',

        public $activity = null,
        public $activityType = null
    ) {
        $this->uuid = 'crm-timeline-item'.md5(serialize($this)).$id;
    }

    public function getOriginEntity()
    {
        if (! $this->activity) {
            return null;
        }

        $origin = $this->activity->timelineable;

        if (! $origin && $this->activity->recordable) {
            $recordable = $this->activity->recordable;
            foreach (['taskable', 'noteable', 'callable', 'meetingable', 'lunchable', 'fileable'] as $relation) {
                if (method_exists($recordable, $relation) && $recordable->{$relation}) {
                    $origin = $recordable->{$relation};
                    break;
                }
            }
        }

        return $origin;
    }

    public function getAssignedUser()
    {
        if (! $this->activity || ! $this->activity->recordable) {
            return null;
        }

        $recordable = $this->activity->recordable;

        if (isset($recordable->assignedToUser) && $recordable->assignedToUser) {
            return $recordable->assignedToUser;
        }

        if (method_exists($recordable, 'userAssigned') && $recordable->userAssigned) {
            return $recordable->userAssigned;
        }

        return null;
    }

    public function getAuthorUser()
    {
        if (! $this->activity) {
            return null;
        }

        if ($this->activity->causeable && method_exists($this->activity->causeable, 'name')) {
            return $this->activity->causeable;
        }

        if ($this->activity->recordable && isset($this->activity->recordable->createdByUser)) {
            return $this->activity->recordable->createdByUser;
        }

        return null;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return <<<'HTML'
            @php
                $originEntity = $this->getOriginEntity();
                $authorUser = $this->getAuthorUser();
                $assignedUser = $this->getAssignedUser();
            @endphp
            <div>
                <!-- Last item `border cut` -->
                <div @class(["border-s-2 $connectorPendingClass h-5 -mb-5" => $last, $connectorActiveClass => !$pending])>
                </div>

                <!-- WRAPPER THAT ALSO ACTS A LINE CONNECTOR -->
                <div @class([
                        "border-s-2 $connectorPendingClass ps-8 py-3",
                        $connectorActiveClass => !$pending,
                        "pt-0" => $first,
                        "!border-s-0" => $last,
                     ])
                >
                    <!-- BULLET -->
                    <div @class([
                            "w-4 h-4 -mb-5 -ms-[41px] $bulletPendingClass rounded-full",
                            $bulletActiveClass => !$pending,
                            "!-ms-[39px]" => $last,
                            "w-8 h-8 !-ms-[48px] -mb-7" => $icon
                         ])
                    >
                        <!-- ICON -->
                        @if($icon)
                            <x-mary-icon :name="$icon" @class(["ms-2 mt-1 w-4 h-4", "text-base-100" => !$pending ])  />
                        @endif
                    </div>

                    <!-- HEADER WITH TITLE, ORIGIN CHIP & USER METADATA -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-sm text-base-content">{{ $title }}</span>

                            @if($activityType)
                                <span class="badge badge-sm badge-neutral font-semibold uppercase tracking-wider text-[10px]">
                                    {{ $activityType }}
                                </span>
                            @endif

                            {{-- ORIGIN ENTITY CHIP ("Where the activity comes from") --}}
                            @if($originEntity)
                                @if(is_a($originEntity, 'VentureDrake\LaravelCrm\Models\Lead'))
                                    <a href="{{ route('laravel-crm.leads.show', $originEntity) }}" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                                        <x-mary-icon name="o-funnel" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Lead: {{ $originEntity->title }}</span>
                                    </a>
                                @elseif(is_a($originEntity, 'VentureDrake\LaravelCrm\Models\Deal'))
                                    <a href="{{ route('laravel-crm.deals.show', $originEntity) }}" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-secondary/10 text-secondary hover:bg-secondary/20 transition-colors">
                                        <x-mary-icon name="o-briefcase" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Deal: {{ $originEntity->title }}</span>
                                    </a>
                                @elseif(is_a($originEntity, 'VentureDrake\LaravelCrm\Models\Person'))
                                    <a href="{{ route('laravel-crm.people.show', $originEntity) }}" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-info/10 text-info hover:bg-info/20 transition-colors">
                                        <x-mary-icon name="o-user" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Person: {{ $originEntity->name }}</span>
                                    </a>
                                @elseif(is_a($originEntity, 'VentureDrake\LaravelCrm\Models\Organization'))
                                    <a href="{{ route('laravel-crm.organizations.show', $originEntity) }}" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-accent/10 text-accent hover:bg-accent/20 transition-colors">
                                        <x-mary-icon name="o-building-office" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                                        <span>Organization: {{ $originEntity->name }}</span>
                                    </a>
                                @endif
                            @endif
                        </div>

                        <!-- METADATA: AUTHOR, ASSIGNED USER & TIMESTAMP -->
                        <div class="flex items-center gap-3 text-xs text-base-content/70">
                            @if($authorUser)
                                <div class="flex items-center gap-1.5" title="Author / Performer">
                                    <x-mary-avatar :title="$authorUser->name" class="w-5 h-5 text-[10px] bg-primary/10 text-primary font-bold shrink-0" />
                                    <span class="font-medium text-xs">{{ $authorUser->name }}</span>
                                </div>
                            @endif

                            @if($assignedUser)
                                <div class="flex items-center gap-1.5" title="Assigned User">
                                    <span class="text-[10px] text-neutral-content/60">Assigned:</span>
                                    <x-mary-avatar :title="$assignedUser->name" class="w-5 h-5 text-[10px] bg-secondary/10 text-secondary font-bold shrink-0" />
                                    <span class="font-medium text-xs">{{ $assignedUser->name }}</span>
                                </div>
                            @endif

                            @if($subtitle)
                                <span class="text-xs text-neutral-content/70 font-medium">{{ $subtitle }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- DESCRIPTION -->
                    @if($description)
                        <div class="text-sm mt-2">
                            {{ $description }}
                        </div>
                    @endif
                    
                    @if($activity && $activityType)
                        <div class="ms-4 mt-2">
                            @livewire('crm-' . $activityType . '-item', [$activityType => $activity->recordable, 'related' => false], key('activity-' . $activityType . '-' . $activity->id))
                        </div>
                    @endif                   
                </div>
            </div>
        HTML;
    }
}
