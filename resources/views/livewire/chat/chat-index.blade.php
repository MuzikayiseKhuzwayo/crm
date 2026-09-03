<div class="crm-content" wire:poll.10s>
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.chat')) }}" progress-indicator>
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.search_chat')) }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        <x-slot:actions>
            <x-mary-select wire:model.live="status" :options="[
                ['id' => 'open', 'name' => ucfirst(__('laravel-crm::lang.open'))],
                ['id' => 'pending', 'name' => ucfirst(__('laravel-crm::lang.pending'))],
                ['id' => 'closed', 'name' => ucfirst(__('laravel-crm::lang.closed'))],
                ['id' => '', 'name' => ucfirst(__('laravel-crm::lang.all'))],
            ]" />

            <x-mary-button label="Embed & External Setup"
                           icon="o-code-bracket"
                           @click="$wire.showEmbedModal = true"
                           class="btn-outline"
                           responsive />

            @can('create', \VentureDrake\LaravelCrm\Models\ChatWidget::class)
                <x-mary-button label="Create Chat Widget"
                               link="{{ url(route('laravel-crm.chat-widgets.create')) }}"
                               icon="o-plus"
                               class="btn-primary text-white"
                               responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- SETUP GUIDANCE BANNER --}}
    @if($chatWidgets->isEmpty())
        <x-mary-card shadow class="bg-gradient-to-r from-primary/10 via-base-200 to-secondary/10 border border-primary/20 mb-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-primary/20 text-primary rounded-xl shrink-0">
                        <x-mary-icon name="o-chat-bubble-left-right" class="w-8 h-8" />
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-base-content">Expose Live Chat on Your Website or Portal</h3>
                        <p class="text-xs text-base-content/70 mt-0.5">
                            Create a Chat Widget to embed real-time visitor chat on external websites, web apps, or share direct chat portal links with your leads.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @can('create', \VentureDrake\LaravelCrm\Models\ChatWidget::class)
                        <x-mary-button label="Create Chat Widget" link="{{ url(route('laravel-crm.chat-widgets.create')) }}" icon="o-plus" class="btn-primary text-white btn-sm" />
                    @endcan
                </div>
            </div>
        </x-mary-card>
    @else
        <div class="flex items-center justify-between bg-base-200/50 p-3 rounded-lg mb-4 text-xs">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-success animate-pulse"></span>
                <span class="font-medium text-base-content/80">Active Chat Integration:</span>
                <span class="font-bold text-primary">{{ $activeWidget?->name ?? 'Default Widget' }}</span>
            </div>
            <div class="flex items-center gap-3">
                <button @click="$wire.showEmbedModal = true" class="text-xs text-primary font-semibold hover:underline inline-flex items-center gap-1">
                    <x-mary-icon name="o-code-bracket" class="w-3.5 h-3.5" />
                    <span>Get Embed Code & Direct Link</span>
                </button>
                <a href="{{ route('laravel-crm.chat-widgets.index') }}" class="text-xs text-neutral-content/70 hover:text-base-content hover:underline inline-flex items-center gap-1">
                    <x-mary-icon name="o-cog-6-tooth" class="w-3.5 h-3.5" />
                    <span>Manage Widgets</span>
                </a>
            </div>
        </div>
    @endif

    {{-- CONVERSATIONS TABLE --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$conversations" :link="route('laravel-crm.chat.show', ['chat' => '[id]'])" with-pagination class="whitespace-nowrap">
            @scope('cell_visitor_online', $c)
                @if($c->visitor_online)
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.online')) }}" class="badge-success badge-sm text-white" />
                @else
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.offline')) }}" class="badge-ghost badge-sm" />
                @endif
            @endscope
            @scope('cell_unread_count', $c)
                @if($c->unread_count > 0)
                    <x-mary-badge value="{{ $c->unread_count > 99 ? '99+' : $c->unread_count }}" class="badge-error badge-sm text-white" />
                @endif
            @endscope
            @scope('cell_status', $c)
                <x-mary-badge value="{{ ucfirst($c->status) }}" class="{{ $c->status === 'open' ? 'badge-success' : ($c->status === 'pending' ? 'badge-warning' : 'badge-neutral') }} text-white" />
            @endscope
            @scope('actions', $c)
                <div class="flex gap-1 justify-end">
                    @can('view crm chat')
                        <x-mary-button icon="o-eye" link="{{ url(route('laravel-crm.chat.show', $c)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('create', \VentureDrake\LaravelCrm\Models\Lead::class)
                        @if(!$c->lead_id)
                            <x-mary-button onclick="modalConvertConversation{{ $c->id }}.showModal()" icon="fas.crosshairs" class="btn-sm btn-square btn-success text-white" spinner />
                            <dialog id="modalConvertConversation{{ $c->id }}" class="modal">
                                <div class="modal-box text-left">
                                    <h3 class="text-lg font-bold">{{ ucfirst(__('laravel-crm::lang.convert_to_lead')) }}?</h3>
                                    <p class="py-4">{{ ucfirst(__('laravel-crm::lang.convert_to_lead_confirm')) }}</p>
                                    <div class="modal-action">
                                        <form method="dialog">
                                            <button class="btn">{{ ucfirst(__('laravel-crm::lang.cancel')) }}</button>
                                            <button wire:click="convertToLead({{ $c->id }})" class="btn btn-success text-white">{{ ucfirst(__('laravel-crm::lang.convert_to_lead')) }}</button>
                                        </form>
                                    </div>
                                </div>
                            </dialog>
                        @endif
                    @endcan
                    @if($c->status !== 'closed')
                        <x-mary-button onclick="modalCloseConversation{{ $c->id }}.showModal()" icon="o-x-circle" class="btn-sm btn-square btn-warning text-white" spinner />
                        <dialog id="modalCloseConversation{{ $c->id }}" class="modal">
                            <div class="modal-box text-left">
                                <h3 class="text-lg font-bold">{{ ucfirst(__('laravel-crm::lang.close_chat')) }}?</h3>
                                <p class="py-4">{{ __('laravel-crm::lang.close_chat_confirm') }}</p>
                                <div class="modal-action">
                                    <form method="dialog">
                                        <button class="btn">{{ ucfirst(__('laravel-crm::lang.cancel')) }}</button>
                                        <button wire:click="close({{ $c->id }})" class="btn btn-warning text-white">{{ ucfirst(__('laravel-crm::lang.close_chat')) }}</button>
                                    </form>
                                </div>
                            </div>
                        </dialog>
                    @endif
                    @can('delete crm chat')
                        <x-mary-button onclick="modalDeleteConversation{{ $c->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner />
                        <x-crm-delete-confirm model="conversation" id="{{ $c->id }}" deleting="conversation" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- EMBED & SETUP MODAL DRAWER --}}
    <x-mary-drawer wire:model="showEmbedModal" title="Expose Live Chat Externally" class="lg:w-1/2" right separator with-close-button>
        <div class="grid gap-6">
            @if($chatWidgets->isNotEmpty())
                {{-- WIDGET SELECTOR --}}
                <div>
                    <label class="block text-xs font-semibold text-base-content/80 mb-2">Select Chat Widget:</label>
                    <select wire:model.live="selectedWidgetId" class="select select-bordered w-full text-xs">
                        @foreach($chatWidgets as $w)
                            <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->is_active ? 'Active' : 'Inactive' }})</option>
                        @endforeach
                    </select>
                </div>

                @if($activeWidget)
                    {{-- 1. JS EMBED SNIPPET --}}
                    <div class="bg-base-200 p-4 rounded-xl space-y-2 border border-base-300">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-base-content flex items-center gap-1.5">
                                <x-mary-icon name="o-code-bracket" class="w-4 h-4 text-primary" />
                                JavaScript Embed Snippet (For External Websites)
                            </span>
                            <button x-data @click="navigator.clipboard.writeText(`{{ addslashes($activeWidget->embedSnippet()) }}`); $wire.success('JS snippet copied to clipboard!')" class="btn btn-xs btn-primary text-white">
                                <x-mary-icon name="o-document-duplicate" class="w-3.5 h-3.5" />
                                Copy Code
                            </button>
                        </div>
                        <p class="text-[11px] text-base-content/70">
                            Paste this JavaScript code snippet inside the <code>&lt;head&gt;</code> of any website, client app, or landing page to display the live chat widget bubble.
                        </p>
                        <pre class="bg-base-300 p-3 rounded-lg text-[11px] font-mono overflow-x-auto text-primary"><code>{{ $activeWidget->embedSnippet() }}</code></pre>
                    </div>

                    {{-- 2. DIRECT PUBLIC LINK --}}
                    <div class="bg-base-200 p-4 rounded-xl space-y-2 border border-base-300">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-base-content flex items-center gap-1.5">
                                <x-mary-icon name="o-link" class="w-4 h-4 text-secondary" />
                                Direct Shareable Live Chat Link
                            </span>
                            <div class="flex gap-2">
                                <button x-data @click="navigator.clipboard.writeText('{{ route('laravel-crm.portal.chat.widget', ['publicKey' => $activeWidget->public_key]) }}'); $wire.success('Direct link copied to clipboard!')" class="btn btn-xs btn-outline">
                                    <x-mary-icon name="o-document-duplicate" class="w-3.5 h-3.5" />
                                    Copy Link
                                </button>
                                <a href="{{ route('laravel-crm.portal.chat.widget', ['publicKey' => $activeWidget->public_key]) }}" target="_blank" class="btn btn-xs btn-secondary text-white">
                                    <x-mary-icon name="o-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                    Open Preview
                                </a>
                            </div>
                        </div>
                        <p class="text-[11px] text-base-content/70">
                            Send this standalone link to clients via email or SMS to start an instant live chat session.
                        </p>
                        <div class="bg-base-300 p-2.5 rounded-lg text-xs font-mono text-base-content truncate">
                            {{ route('laravel-crm.portal.chat.widget', ['publicKey' => $activeWidget->public_key]) }}
                        </div>
                    </div>

                    {{-- 3. HTML IFRAME EMBED --}}
                    <div class="bg-base-200 p-4 rounded-xl space-y-2 border border-base-300">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-base-content flex items-center gap-1.5">
                                <x-mary-icon name="o-window" class="w-4 h-4 text-accent" />
                                HTML Iframe Embed
                            </span>
                            <button x-data @click="navigator.clipboard.writeText('<iframe src=\'{{ route('laravel-crm.portal.chat.widget', ['publicKey' => $activeWidget->public_key]) }}\' width=\'380\' height=\'560\' style=\'border:none; border-radius:12px;\'></iframe>'); $wire.success('Iframe code copied to clipboard!')" class="btn btn-xs btn-outline">
                                <x-mary-icon name="o-document-duplicate" class="w-3.5 h-3.5" />
                                Copy Iframe
                            </button>
                        </div>
                        <pre class="bg-base-300 p-3 rounded-lg text-[11px] font-mono overflow-x-auto text-base-content">&lt;iframe src="{{ route('laravel-crm.portal.chat.widget', ['publicKey' => $activeWidget->public_key]) }}" width="380" height="560" style="border:none; border-radius:12px;"&gt;&lt;/iframe&gt;</pre>
                    </div>

                    {{-- 4. SETTINGS & CUSTOMIZATION --}}
                    <div class="flex items-center justify-between p-3 bg-base-200 rounded-xl text-xs">
                        <div>
                            <span class="font-bold text-base-content">Widget Configuration:</span>
                            <span class="text-neutral-content/70 ml-2">Color: {{ $activeWidget->color ?? '#2563eb' }} | Position: {{ $activeWidget->position ?? 'bottom-right' }}</span>
                        </div>
                        <a href="{{ route('laravel-crm.chat-widgets.edit', $activeWidget) }}" class="btn btn-xs btn-ghost text-primary font-semibold hover:underline">
                            Edit Widget Settings
                        </a>
                    </div>
                @endif
            @else
                <div class="text-center py-8">
                    <x-mary-icon name="o-chat-bubble-left-right" class="w-12 h-12 text-primary/40 mx-auto mb-3" />
                    <h3 class="font-bold text-base text-base-content">No Chat Widgets Configured Yet</h3>
                    <p class="text-xs text-base-content/70 max-w-sm mx-auto mt-1 mb-4">
                        Create your first Chat Widget to customize colors, welcome messages, and generate embed codes for external websites.
                    </p>
                    <a href="{{ route('laravel-crm.chat-widgets.create') }}" class="btn btn-primary text-white btn-sm">
                        Create First Chat Widget
                    </a>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Close" icon="o-x-mark" class="btn-ghost" @click="$wire.showEmbedModal = false" />
            <a href="{{ route('laravel-crm.chat-widgets.index') }}" class="btn btn-primary text-white">
                Manage All Widgets
            </a>
        </x-slot:actions>
    </x-mary-drawer>
</div>
