<div class="space-y-4">
    <p class="text-sm text-base-content/70">
        Connect your local LinkedIn Chrome extension to this CRM instance using a Sanctum personal access token.
    </p>

    <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 space-y-2" x-data="{ copiedEndpoint: false }">
        <div class="text-xs font-semibold uppercase tracking-wide text-base-content/60">Outreach API Base URL</div>
        <div class="flex items-center gap-2">
            <code class="flex-1 text-xs font-mono bg-base-100 px-2.5 py-1.5 rounded border border-base-300 truncate">{{ $this->apiEndpointUrl }}</code>
            <button
                type="button"
                class="btn btn-xs btn-outline"
                x-on:click="navigator.clipboard?.writeText(@js($this->apiEndpointUrl)); copiedEndpoint = true; setTimeout(() => copiedEndpoint = false, 1500)"
            >
                <span x-show="!copiedEndpoint">Copy URL</span>
                <span x-show="copiedEndpoint" x-cloak class="text-success">Copied</span>
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-48">
            <x-mary-input wire:model="apiTokenName" label="Token Name" placeholder="linkedin-chrome-extension" />
        </div>
        <x-mary-button
            label="Generate Sanctum Token"
            wire:click="generateExtensionToken"
            icon="o-key"
            class="btn-primary text-white"
            spinner="generateExtensionToken"
        />
    </div>

    @if($generatedPlainTextToken)
        <div role="alert" class="alert alert-success flex-col items-stretch gap-2" x-data="{ copiedToken: false }">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase">New Personal Access Token (copy now — shown once)</span>
                <button
                    type="button"
                    class="btn btn-xs btn-neutral text-white"
                    x-on:click="navigator.clipboard?.writeText(@js($generatedPlainTextToken)); copiedToken = true; setTimeout(() => copiedToken = false, 2000)"
                >
                    <span x-show="!copiedToken">Copy Token</span>
                    <span x-show="copiedToken" x-cloak>Copied!</span>
                </button>
            </div>
            <code class="block w-full break-all rounded bg-base-100/90 text-base-content px-3 py-2 text-xs font-mono select-all">{{ $generatedPlainTextToken }}</code>
        </div>
    @endif

    <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 space-y-2" x-data="{ copiedCli: false }">
        <div class="text-xs font-semibold uppercase tracking-wide text-base-content/60">Or Issue via Artisan CLI</div>
        <div class="flex items-center gap-2">
            <code class="flex-1 text-xs font-mono bg-base-100 px-2.5 py-1.5 rounded border border-base-300 overflow-x-auto">{{ $this->cliTokenCommand }}</code>
            <button
                type="button"
                class="btn btn-xs btn-outline"
                x-on:click="navigator.clipboard?.writeText(@js($this->cliTokenCommand)); copiedCli = true; setTimeout(() => copiedCli = false, 1500)"
            >
                <span x-show="!copiedCli">Copy</span>
                <span x-show="copiedCli" x-cloak class="text-success">Copied</span>
            </button>
        </div>
    </div>
</div>
