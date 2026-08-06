<x-crm::app-layout title="{{ ucfirst(__('laravel-crm::lang.updates')) }}">
    <div class="crm-content">
        <x-mary-header title="Laravel CRM {{ ucfirst(__('laravel-crm::lang.updates')) }}" progress-indicator></x-mary-header>
        <x-mary-card shadow>
            @php
                // Read through SettingService so this page and SystemCheckService share one
                // cached source. UpdateController's writes above bust that cache via
                // SettingObserver, so a freshly-fetched version_latest is visible here.
                $settings        = app('laravel-crm.settings');
                $currentVersion  = $settings->get('version');
                $latestVersion   = $settings->get('version_latest');

                // version_compare, not string comparison: '2.2.0' < '2.10.0' is false when
                // compared as strings, which silently hid the update banner once the minor
                // version reached double digits.
                $comparable      = $currentVersion && $latestVersion;
                $isLatest        = $comparable && version_compare($currentVersion, $latestVersion, '>=');
                $updateAvailable = $comparable && version_compare($currentVersion, $latestVersion, '<');
            @endphp
            <div class="grid gap-y-3">
                @if($currentVersion)
                    <p>{{ ucfirst(__('laravel-crm::lang.current_version')) }}: <strong>{{ $currentVersion }}</strong>
                        @if($isLatest)
                            &mdash; {{ __('laravel-crm::lang.is_the_latest_version') }}
                        @endif
                    </p>
                @else
                    <p>{{ ucfirst(__('laravel-crm::lang.current_version')) }}: &mdash;</p>
                @endif

                @if($updateAvailable)
                    <p>{{ ucfirst(__('laravel-crm::lang.updated_version_of_laravel_crm_is_available')) }}</p>
                    <p>{{ ucfirst(__('laravel-crm::lang.you_can_update_from_laravel_crm')) }} {{ $currentVersion }} to {{ $latestVersion }}</p>
                    <p>
                        <a type="button" class="btn btn-primary text-white" href="{{ config('laravel-crm.docs_url') }}" target="_blank" rel="noopener">{{ ucfirst(__('laravel-crm::lang.upgrade_guide')) }}</a>
                    </p>
                @endif
            </div>
        </x-mary-card>
    </div>
</x-crm::app-layout>
