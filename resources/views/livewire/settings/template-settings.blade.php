<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.templates')) }}" class="mb-5" progress-indicator />

    <x-mary-form wire:submit="save">
        {{-- TABS (DaisyUI radio tabs-lift + tab content — matches /crm/users tab style) --}}
        <div role="tablist" class="tabs tabs-lift">
            @foreach ($docTypes as $docType)
                @php($tabLabel = ucfirst(__('laravel-crm::lang.'.str_replace('-', '_', $docType))))
                <input type="radio"
                       name="template-tabs"
                       role="tab"
                       class="tab"
                       aria-label="{{ $tabLabel }}"
                       value="{{ $docType }}"
                       @checked($tab === $docType)
                       wire:model.live="tab" />
                <div role="tabpanel" class="tab-content bg-base-100 border-base-300 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                        @foreach ($templates as $slug => $template)
                            @php($isSelected = ($selected[$docType] ?? null) === $slug)
                            @php($thumbnailPath = 'vendor/laravel-crm/img/pdf-templates/'.$slug.'.svg')
                            @php($previewUrl = route('laravel-crm.settings.templates.preview', ['docType' => $docType, 'slug' => $slug]))
                            <div
                                wire:key="template-card-{{ $docType }}-{{ $slug }}"
                                class="border rounded-lg p-3 flex flex-col gap-2 cursor-pointer transition {{ $isSelected ? 'border-primary ring-2 ring-primary bg-primary/5' : 'border-base-300 hover:border-primary/50' }}"
                                wire:click="select('{{ $docType }}', '{{ $slug }}')"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="template-{{ $docType }}"
                                            value="{{ $slug }}"
                                            class="radio radio-primary radio-sm"
                                            @checked($isSelected)
                                            wire:click.stop="select('{{ $docType }}', '{{ $slug }}')"
                                        />
                                        <span class="font-semibold text-sm">{{ ucfirst($template['label']) }}</span>
                                    </div>
                                    @if ($isSelected)
                                        <x-mary-icon name="o-check-circle" class="text-primary w-5 h-5" />
                                    @endif
                                </div>
                                <div class="bg-base-200/50 rounded flex items-center justify-center overflow-hidden" style="height: 140px;">
                                    @if (file_exists(public_path($thumbnailPath)))
                                        <img
                                            src="{{ asset($thumbnailPath) }}"
                                            alt="{{ $template['label'] }}"
                                            class="max-h-full max-w-full object-contain"
                                        />
                                    @else
                                        <div class="text-xs text-base-content/40 p-3 text-center">{{ ucfirst($template['label']) }}</div>
                                    @endif
                                </div>
                                <p class="text-xs text-base-content/70">{{ $template['description'] }}</p>
                                <a
                                    href="{{ $previewUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-sm btn-outline btn-primary mt-auto"
                                    wire:click.stop=""
                                >
                                    <x-mary-icon name="o-document-magnifying-glass" class="w-4 h-4" />
                                    {{ ucfirst(__('laravel-crm::lang.preview')) }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.save_changes')) }}" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>
