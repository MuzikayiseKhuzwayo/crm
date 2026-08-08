@php
    // The three task forms label these fields differently (a standing form says
    // "start at", an inline editor asks "when does it start?") but share the same
    // bindings and the same 15 minute snapping, so they share this partial.
    $startLabel ??= 'when_does_it_start';
    $dueLabel ??= 'whens_it_due';
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-data="{
    snap15(el) {
        if (!el.value) return;
        const d = new Date(el.value);
        d.setMinutes(Math.round(d.getMinutes() / 15) * 15, 0, 0);
        const p = n => String(n).padStart(2, '0');
        el.value = `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
        el.dispatchEvent(new Event('input'));
    }
}">
    <x-mary-datetime wire:model="start_at" label="{{ ucfirst(__('laravel-crm::lang.'.$startLabel)) }}" type="datetime-local" x-on:change="snap15($event.target)" />
    <x-mary-datetime wire:model="due_at" label="{{ ucfirst(__('laravel-crm::lang.'.$dueLabel)) }}" type="datetime-local" x-on:change="snap15($event.target)" />
</div>
