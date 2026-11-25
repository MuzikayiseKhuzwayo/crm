<div class="grid gap-5">
    <div class="grid md:grid-cols-3 gap-4">
        <x-mary-input
            wire:model="daily_connect_limit"
            type="number"
            min="1"
            max="100"
            label="Daily Connect Limit"
            hint="Recommended: 15/day"
        />
        <x-mary-input
            wire:model="daily_dm_limit"
            type="number"
            min="1"
            max="200"
            label="Daily DM Limit"
            hint="Recommended: 30/day"
        />
        <x-mary-input
            wire:model="weekly_connect_limit"
            type="number"
            min="1"
            max="500"
            label="Rolling 7-Day Connect Cap"
            hint="Recommended: 80/week"
        />
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <x-mary-input
            wire:model="min_delay_seconds"
            type="number"
            min="10"
            max="3600"
            label="Min Jitter Delay (seconds)"
            hint="Cooldown between actions (e.g. 180s)"
        />
        <x-mary-input
            wire:model="max_delay_seconds"
            type="number"
            min="10"
            max="7200"
            label="Max Jitter Delay (seconds)"
            hint="Upper bound for human-like spacing (e.g. 420s)"
        />
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <x-mary-input
            wire:model="active_start_hour"
            type="number"
            min="0"
            max="23"
            label="Business Hours Start (0-23)"
        />
        <x-mary-input
            wire:model="active_end_hour"
            type="number"
            min="1"
            max="24"
            label="Business Hours End (1-24)"
        />
        <x-mary-select
            wire:model="timezone"
            :options="$timezones"
            label="Timezone"
        />
    </div>

    <div>
        <label class="text-sm font-medium block mb-2">Active Days</label>
        <div class="flex flex-wrap gap-3">
            @foreach($weekdays as $day)
                <label class="label cursor-pointer gap-2 rounded-lg border border-base-300 px-3 py-1.5 bg-base-100">
                    <input
                        type="checkbox"
                        value="{{ $day['id'] }}"
                        wire:model="active_days"
                        class="checkbox checkbox-sm checkbox-primary"
                    />
                    <span class="label-text text-sm">{{ $day['label'] }}</span>
                </label>
            @endforeach
        </div>
        @error('active_days')
            <div class="text-error text-xs mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>
