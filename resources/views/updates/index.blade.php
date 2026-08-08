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

                // check() rather than alerts(): this page is where an operator comes to
                // find out, so it must answer even on a host that has switched the
                // notification banner off.
                $databaseUpdateRequired = collect(app('laravel-crm.system-check')->check())
                    ->contains(fn ($alert) => ($alert['type'] ?? null) === \VentureDrake\LaravelCrm\Services\SystemCheckService::DB_UPDATE_REQUIRED);
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

        {{--
            The commands, spelled out. Neither this page nor the system check
            banner used to say what to actually run, so the only way to find out
            was to already know that laravelcrm:update exists.
        --}}
        <x-mary-card shadow class="mt-6" title="{{ ucfirst(__('laravel-crm::lang.how_to_update')) }}">
            @if($databaseUpdateRequired)
                {{-- title + description, not the default slot: Mary's Alert renders
                     the slot only when title is null. --}}
                <x-mary-alert
                    icon="o-information-circle"
                    class="alert-info mb-4"
                    title="{{ ucfirst(__('laravel-crm::lang.database_update_required')) }}"
                    description="{{ __('laravel-crm::lang.your_database_is_behind_the_installed_code') }}"
                />
            @endif

            <p class="mb-3">{{ ucfirst(__('laravel-crm::lang.run_these_from_your_project_root')) }}:</p>

            <div class="grid gap-y-4">
                <div>
                    <pre class="bg-base-200 rounded p-3 overflow-x-auto"><code>composer update venturedrake/laravel-crm</code></pre>
                    <p class="text-sm opacity-70 mt-1">{{ __('laravel-crm::lang.update_step_composer') }}</p>
                </div>
                <div>
                    <pre class="bg-base-200 rounded p-3 overflow-x-auto"><code>php artisan laravelcrm:update</code></pre>
                    <p class="text-sm opacity-70 mt-1">{{ __('laravel-crm::lang.update_step_artisan') }}</p>
                </div>
            </div>

            <p class="mt-4">
                <a class="link" href="{{ config('laravel-crm.docs_url') }}" target="_blank" rel="noopener">{{ ucfirst(__('laravel-crm::lang.upgrade_guide')) }}</a>
            </p>
        </x-mary-card>
    </div>
</x-crm::app-layout>
