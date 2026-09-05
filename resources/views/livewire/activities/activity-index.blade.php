<div class="grid gap-5">
    <x-mary-card separator>
        @foreach($this->activities as $activity)
            @php
                $userName = $activity->causeable->name ?? $activity->recordable?->createdByUser?->name ?? null;
                $activityType = $activity->recordable_type ? strtolower(class_basename($activity->recordable_type)) : null;
                $isTaskActivity = ($activity->timelineable instanceof \VentureDrake\LaravelCrm\Models\Task 
                    || $activity->timelineable_type === \VentureDrake\LaravelCrm\Models\Task::class 
                    || (method_exists($activity->timelineable, 'getMorphClass') && $activity->timelineable_type === (new \VentureDrake\LaravelCrm\Models\Task)->getMorphClass())
                    || ($activity->recordable && method_exists($activity->recordable, 'noteable') && $activity->recordable->noteable instanceof \VentureDrake\LaravelCrm\Models\Task));
                $title = ($isTaskActivity && $activityType === 'note')
                    ? ($userName ? $userName . ' added a note to task' : 'Added a note to task')
                    : (($userName ? $userName . ' created a ' : 'Created a ') . ($activityType ?? 'activity'));
            @endphp

            <x-crm-timeline-item
                :title="$title"
                :subtitle="$activity->created_at->format('M d, Y h:i A') . ' (' . $activity->created_at->diffForHumans() . ')'"
                :activity="$activity"
                :activityType="$activityType"
                :first="$loop->first"
                :last="$loop->last"
            />
        @endforeach
    </x-mary-card>
</div>