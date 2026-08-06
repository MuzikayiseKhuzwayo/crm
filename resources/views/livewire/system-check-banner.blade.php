<div>
    @if (count($alerts) > 0)
        <div class="mb-6 grid gap-3">
            @foreach ($alerts as $index => $alert)
                @php($warning = ($alert['level'] ?? 'info') === 'warning')
                {{--
                    Mary's Alert renders the default slot only when `title` is
                    null, so the sentence goes in the slot and no title is set.
                    `dismissible` is skipped too — it is Alpine-only (x-show),
                    so it would hide the bar without recording the dismissal.
                    `id` rather than wire:key: Alert builds its own wire:key
                    from the uuid, which folds `id` in.
                --}}
                <x-mary-alert
                    id="crm-system-check-{{ $index }}"
                    :icon="$warning ? 'o-exclamation-triangle' : 'o-information-circle'"
                    class="{{ $warning ? 'alert-warning' : 'alert-info' }}"
                >
                    <span>
                        <strong>{{ ucfirst(__('laravel-crm::lang.important')) }}:</strong>
                        {!! $messages[$index] !!}
                    </span>

                    <x-slot:actions>
                        <x-mary-button
                            icon="o-x-mark"
                            wire:click="dismiss"
                            class="btn-xs btn-circle btn-ghost"
                            :title="ucfirst(__('laravel-crm::lang.dismiss'))"
                        />
                    </x-slot:actions>
                </x-mary-alert>
            @endforeach
        </div>
    @endif
</div>
