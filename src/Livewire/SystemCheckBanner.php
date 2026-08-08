<?php

namespace VentureDrake\LaravelCrm\Livewire;

use Livewire\Component;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

/**
 * Renders the SystemCheckService alerts as a dismissible bar above the page
 * content, restoring the notices the (dead) SystemCheck middleware used to
 * flash.
 *
 * Livewire rather than a plain partial for two reasons: php-flasher is not an
 * option here (UserController try/catches it because it is not always
 * initialised), and Mary's toast is Alpine-only, so it cannot read the
 * per-user dismissal that lives in crm_settings.
 */
class SystemCheckBanner extends Component
{
    /**
     * Name of the per-user crm_settings row holding the signature of the
     * alert set the user last dismissed.
     */
    public const DISMISS_SETTING = 'system_check_dismissed';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $alerts = [];

    public ?string $signature = null;

    public function mount(): void
    {
        $this->resolve();
    }

    /**
     * Record the current signature against the user so this exact alert set
     * stays hidden, then clear the rendered state.
     *
     * A later change to the underlying alerts produces a different signature,
     * which no longer matches the stored one, so the banner comes back.
     */
    public function dismiss(): void
    {
        $user = auth()->user();

        // Every public Livewire method is invokable straight from the client,
        // so re-check the permission resolve() gates on rather than relying on
        // the component having rendered anything.
        abort_unless($user && $user->can('view crm updates'), 403);

        // Recompute rather than persist $this->signature: Livewire properties
        // are client-writable, so the rendered value must not be the one that
        // reaches the database.
        $signature = app('laravel-crm.system-check')->signature();

        if ($signature !== null) {
            app('laravel-crm.settings')->setForUser($user->getKey(), self::DISMISS_SETTING, $signature);
        }

        $this->alerts = [];
        $this->signature = null;
    }

    public function render()
    {
        return view('laravel-crm::livewire.system-check-banner', [
            'messages' => array_map(fn (array $alert) => $this->message($alert), $this->alerts),
        ]);
    }

    /**
     * The alert's sentence, with its links already substituted and every
     * interpolated value escaped.
     *
     * Composed here rather than in Blade so the e() calls sit next to the
     * values they protect — the surrounding sentence is developer-authored
     * lang, but the version numbers come from the database.
     *
     * Protected, and reached via render()'s view data rather than $this-> in
     * the view: a public method here would be client-invokable for no reason,
     * and its SystemCheckService:: constant reads trip the `Service::` write
     * heuristic in ActionAuthorizationCoverageTest.
     */
    protected function message(array $alert): string
    {
        // Two URLs, deliberately: docs_url answers "what is in this release?"
        // and the upgrade guide answers "how do I install it?".
        $docsUrl = (string) config('laravel-crm.docs_url');
        $upgradeGuideUrl = (string) config('laravel-crm.upgrade_guide_url');

        switch ($alert['type'] ?? null) {
            case SystemCheckService::UPGRADE_REQUIRED:
                return __('laravel-crm::lang.system_check_upgrade_required', [
                    'guide' => $this->link($upgradeGuideUrl, __('laravel-crm::lang.system_check_upgrade_guide')),
                ]);

            case SystemCheckService::UPDATE_AVAILABLE:
                return __('laravel-crm::lang.system_check_update_available', [
                    'details' => $this->link($docsUrl, __('laravel-crm::lang.system_check_view_version_details', [
                        'version' => $alert['latest_version'] ?? '',
                    ])),
                    'update' => $this->link(
                        route('laravel-crm.updates.index'),
                        __('laravel-crm::lang.system_check_update_now'),
                        false
                    ),
                ]);

            case SystemCheckService::DB_UPDATE_REQUIRED:
                // The command itself, not just a link to a page about it — the
                // fix is a line typed into a terminal, and the operator reading
                // this banner is the person who has to type it.
                //
                // The link placeholder is :updates_page, not :update: the
                // command value ends in the literal "laravelcrm:update", and
                // Laravel substitutes longest key first — so a :update
                // placeholder would be applied *after* :command and chew the
                // colon-word out of the command it had just inserted.
                return __('laravel-crm::lang.system_check_db_update_required', [
                    'command' => $this->code(__('laravel-crm::lang.system_check_db_update_command')),
                    'updates_page' => $this->link(
                        route('laravel-crm.updates.index'),
                        __('laravel-crm::lang.system_check_update_database'),
                        false
                    ),
                ]);
        }

        return '';
    }

    /**
     * Populate $alerts/$signature, or leave both empty when there is nothing
     * this user should see.
     */
    protected function resolve(): void
    {
        $this->alerts = [];
        $this->signature = null;

        $user = auth()->user();

        // can() rather than the middleware's raw hasPermissionTo(): the latter
        // throws PermissionDoesNotExist when permissions were never seeded,
        // which is precisely the broken install this banner exists to report
        // on. Spatie's checkPermissionTo() swallows that and returns false.
        if (! $user || ! $user->can('view crm updates')) {
            return;
        }

        $systemCheck = app('laravel-crm.system-check');

        $alerts = $systemCheck->alerts();

        if (count($alerts) === 0) {
            return;
        }

        $signature = $systemCheck->signature();

        if ($signature !== null && $this->dismissedSignature($user) === $signature) {
            return;
        }

        $this->alerts = $alerts;
        $this->signature = $signature;
    }

    protected function dismissedSignature($user): ?string
    {
        $dismissed = app('laravel-crm.settings')->getForUser(
            $user->getKey(),
            self::DISMISS_SETTING
        );

        return $dismissed === null ? null : (string) $dismissed;
    }

    /**
     * Both the href and the label are escaped — the label carries interpolated
     * database values (the version number) and the href is config-driven.
     */
    protected function link(string $href, string $label, bool $external = true): string
    {
        $target = $external ? ' target="_blank" rel="noopener noreferrer"' : '';

        return '<a href="'.e($href).'"'.$target.' class="link">'.e($label).'</a>';
    }

    /**
     * A shell command rendered inline. Escaped for the same reason link() is —
     * it arrives through the translator, so a locale file is the input.
     */
    protected function code(string $command): string
    {
        return '<code class="font-mono text-sm">'.e($command).'</code>';
    }
}
