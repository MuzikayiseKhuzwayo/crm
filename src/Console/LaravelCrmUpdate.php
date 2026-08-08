<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\InvoiceLine;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Observers\TeamObserver;
use VentureDrake\LaravelCrm\Services\SettingService;

/**
 * The half of the update that touches the database.
 *
 * Split out from laravelcrm:upgrade — which republishes assets and clears
 * caches, runs from the host's composer hook, and never opens a connection.
 * This command runs migrations, seeders and data backfills, so it stays
 * explicit: an operator (or a deploy script) runs it, once, after the code is
 * in place.
 *
 * It calls laravelcrm:upgrade first, so a human running one command by hand
 * still gets everything.
 *
 * Every failure here is fatal. This command used to catch migration and seeder
 * exceptions, downgrade them to warnings and still print "Laravel CRM is now
 * updated", which made a broken upgrade indistinguishable from a clean one in
 * a deploy log — and left the deploy script's `&&` chain running happily on.
 */
class LaravelCrmUpdate extends Command
{
    /**
     * The setting the database schema version is stamped into on success.
     *
     * Distinct from `version`, which Http/Middleware/Settings overwrites with
     * config('laravel-crm.version') on the first web request after a deploy and
     * therefore always reads as current. This one only moves when this command
     * completes, so "code is ahead of database" is detectable.
     */
    public const DB_VERSION_SETTING = 'db_version';

    /**
     * @var SettingService
     */
    private $settingService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:update
                           {--force : Run without confirmation, for deploy scripts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply Laravel CRM database migrations, seed data and backfills';

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Foundation\Composer
     */
    protected $composer;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Composer $composer, SettingService $settingService)
    {
        parent::__construct();
        $this->composer = $composer;
        $this->settingService = $settingService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return self::SUCCESS;
        }

        $this->info('Updating Laravel CRM...');

        // The safe half: republishes assets, prunes stale build output and
        // clears cached config/routes/views. Runs first so any stale
        // `config:cache` from the host can't poison reads of freshly-published
        // config or env below, and so an operator running this one command by
        // hand gets the whole upgrade.
        if ($this->call('laravelcrm:upgrade') !== self::SUCCESS) {
            $this->error('laravelcrm:upgrade failed. Laravel CRM has NOT been updated.');

            return self::FAILURE;
        }

        $this->info('Publishing migrations...');

        // The published stub set is frozen — nothing new is ever added to it
        // (see LaravelCrmServiceProvider::boot). Migrations added from here on
        // ship as real .php files in database/updates and reach the host
        // through loadMigrationsFrom, so they need no publishing at all.
        //
        // This forced re-publish stays for the stubs that already exist, so a
        // host picks up in-place fixes to them (idempotency guards and the
        // like). Already-run migrations are unaffected by the rewrite because
        // Laravel keys the migrations table by filename, not content.
        $this->call('vendor:publish', [
            '--provider' => 'VentureDrake\LaravelCrm\LaravelCrmServiceProvider',
            '--tag' => 'migrations',
            '--force' => true,
        ]);

        $this->info('Running migrations...');

        // Fatal, not a warning. A failed ALTER leaves the schema behind the
        // code, and every backfill below reads that schema — carrying on would
        // report success over a half-applied upgrade.
        try {
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                $this->error('Migrations failed. Laravel CRM has NOT been updated.');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Migrations failed: '.$e->getMessage());
            $this->error('Laravel CRM has NOT been updated.');

            return self::FAILURE;
        }

        $this->info('Reseeding base tables...');

        try {
            if ($this->callSilent('db:seed', [
                '--class' => 'VentureDrake\LaravelCrm\Database\Seeders\LaravelCrmTablesSeeder',
                '--force' => true,
            ]) !== self::SUCCESS) {
                $this->error('Seeding base tables failed. Laravel CRM has NOT been updated.');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Seeding base tables failed: '.$e->getMessage());
            $this->error('Laravel CRM has NOT been updated.');

            return self::FAILURE;
        }

        $this->seedLookupData();

        // The db_update_* markers below are written with setInstallWide rather
        // than set. A console command has no authenticated user, so a plain
        // set() stamps no team_id and writes a row that web requests — which
        // read Settings through BelongsToTeamsScope — cannot see, leaving the
        // system check reporting an update this command has just completed.
        // setInstallWide also rewrites every row of the same name, so per-team
        // duplicates written by older versions of this package clear too.
        if ($this->settingService->get('db_update_0180') == 0) {
            $this->info('Updating Laravel CRM quote numbers...');

            foreach (Quote::whereNull('number')->get() as $quote) {
                $this->info('Updating Laravel CRM quote #'.$quote->id);

                $quote->update([
                    'quote_id' => $this->settingService->get('quote_prefix').(1000 + $quote->id),
                    'prefix' => $this->settingService->get('quote_prefix'),
                    'number' => 1000 + $quote->id,
                ]);
            }

            $this->info('Updating Laravel CRM quote numbers complete');

            $this->info('Updating Laravel CRM order numbers...');

            foreach (Order::whereNull('number')->get() as $order) {
                $this->info('Updating Laravel CRM order #'.$order->id);

                $order->update([
                    'order_id' => $this->settingService->get('order_prefix').(1000 + $order->id),
                    'prefix' => $this->settingService->get('order_prefix'),
                    'number' => 1000 + $order->id,
                ]);
            }

            $this->settingService->setInstallWide('db_update_0180', 1);
            $this->info('Updating Laravel CRM orders numbers complete');
        }

        if ($this->settingService->get('db_update_0181') == 0) {
            $this->info('Updating Laravel CRM organization linked to person...');

            foreach (Person::whereNotNull('organization_id')->get() as $person) {
                if ($contact = $person->contacts()->create([
                    'team_id' => $person->team_id,
                    'entityable_type' => $person->organization->getMorphClass(),
                    'entityable_id' => $person->organization->id,
                ])) {
                    $person->update([
                        'organization_id' => null,
                    ]);
                }
            }

            $this->settingService->setInstallWide('db_update_0181', 1);
            $this->info('Updating Laravel CRM organization linked to person complete.');
        }

        if ($this->settingService->get('db_update_0191') == 0) {
            $this->info('Updating Laravel CRM split orders, invoices & deliveries...');

            foreach (Order::whereNotNull('quote_id')->get() as $order) {
                if ($order->quote) {
                    foreach ($order->quote->quoteProducts as $quoteProduct) {
                        if ($orderProduct = $order->orderProducts()
                            ->whereNull('quote_product_id')
                            ->where([
                                'product_id' => $quoteProduct->product_id,
                                'price' => $quoteProduct->price,
                            ])->first()) {
                            $orderProduct->update([
                                'quote_product_id' => $quoteProduct->id,
                            ]);
                        }
                    }
                }
            }

            foreach (Invoice::whereNotNull('order_id')->get() as $invoice) {
                if ($invoice->order) {
                    foreach ($invoice->order->orderProducts as $orderProduct) {
                        if ($invoiceLine = $invoice->invoiceLines()
                            ->whereNull('order_product_id')
                            ->where([
                                'product_id' => $orderProduct->product_id,
                                'price' => $orderProduct->price,
                            ])->first()) {
                            $invoiceLine->update([
                                'order_product_id' => $orderProduct->id,
                            ]);
                        }
                    }
                }
            }

            $this->settingService->setInstallWide('db_update_0191', 1);
            $this->info('Updating Laravel CRM split orders, invoices & deliveries complete.');
        }

        if ($this->settingService->get('db_update_0193') == 0) {
            $this->info('Updating Laravel CRM split deliveries...');

            foreach (Delivery::whereNotNull('order_id')->get() as $delivery) {
                if ($delivery->order) {
                    foreach ($delivery->order->orderProducts as $orderProduct) {
                        if ($deliveryProduct = $delivery->deliveryProducts()
                            ->whereNull('quantity')
                            ->where([
                                'order_product_id' => $orderProduct->id,
                            ])->first()) {
                            $deliveryProduct->update([
                                'quantity' => $orderProduct->quantity,
                            ]);
                        }
                    }
                }
            }

            $this->settingService->setInstallWide('db_update_0193', 1);
            $this->info('Updating Laravel CRM split deliveries complete.');
        }

        if ($this->settingService->get('db_update_0194') == 0) {
            $this->info('Updating Laravel CRM delivery numbers...');

            foreach (Delivery::whereNull('number')->get() as $delivery) {
                $this->info('Updating Laravel CRM delivery #'.$delivery->id);

                $delivery->update([
                    'delivery_id' => $this->settingService->get('delivery_prefix').(1000 + $delivery->id),
                    'prefix' => $this->settingService->get('delivery_prefix'),
                    'number' => 1000 + $delivery->id,
                ]);
            }

            $this->settingService->setInstallWide('db_update_0194', 1);
            $this->info('Updating Laravel CRM delivery numbers complete');
        }

        if ($this->settingService->get('db_update_0199') == 0) {
            $this->info('Updating Laravel CRM tax amounts...');

            foreach (QuoteProduct::whereNull('tax_amount')->get() as $quoteProduct) {
                $this->info('Updating Laravel CRM quote product tax #'.$quoteProduct->id);

                if ($quoteProduct->product && $quoteProduct->product->taxRate) {
                    $taxRate = $quoteProduct->product->taxRate->rate;
                } elseif ($quoteProduct->product && $quoteProduct->product->tax_rate) {
                    $taxRate = $quoteProduct->product->tax_rate;
                } else {
                    $taxRate = optional(Setting::where('name', 'tax_rate')->first())->value ?? 0;
                }

                $quoteProduct->update([
                    'tax_rate' => $taxRate,
                    'tax_amount' => $quoteProduct->amount * ($taxRate / 100),
                ]);
            }

            foreach (OrderProduct::whereNull('tax_amount')->get() as $orderProduct) {
                $this->info('Updating Laravel CRM order product tax #'.$orderProduct->id);

                if ($orderProduct->product && $orderProduct->product->taxRate) {
                    $taxRate = $orderProduct->product->taxRate->rate;
                } elseif ($orderProduct->product && $orderProduct->product->tax_rate) {
                    $taxRate = $orderProduct->product->tax_rate;
                } else {
                    $taxRate = optional(Setting::where('name', 'tax_rate')->first())->value ?? 0;
                }

                $orderProduct->update([
                    'tax_rate' => $taxRate,
                    'tax_amount' => $orderProduct->amount * ($taxRate / 100),
                ]);
            }

            foreach (InvoiceLine::whereNull('tax_amount')->get() as $invoiceLine) {
                $this->info('Updating Laravel CRM invoice line tax #'.$invoiceLine->id);

                if ($invoiceLine->product && $invoiceLine->product->taxRate) {
                    $taxRate = $invoiceLine->product->taxRate->rate;
                } elseif ($invoiceLine->product && $invoiceLine->product->tax_rate) {
                    $taxRate = $invoiceLine->product->tax_rate;
                } else {
                    $taxRate = optional(Setting::where('name', 'tax_rate')->first())->value ?? 0;
                }

                $invoiceLine->update([
                    'tax_rate' => $taxRate,
                    'tax_amount' => ($invoiceLine->amount * ($taxRate / 100)) / 100,
                ]);
            }

            $this->settingService->setInstallWide('db_update_0199', 1);
            $this->info('Updating Laravel CRM tax amounts complete');
        }

        if ($this->settingService->get('db_update_1200') == 0) {
            $this->info('Updating Laravel CRM pipeline tables');

            $this->callSilent('db:seed', [
                '--class' => 'VentureDrake\LaravelCrm\Database\Seeders\LaravelCrmPipelineTablesSeeder',
                '--force' => true,
            ]);

            foreach (Lead::whereNull('number')->get() as $lead) {
                $this->info('Updating Laravel CRM lead #'.$lead->id);

                $lead->update([
                    'lead_id' => $this->settingService->get('lead_prefix').(1000 + $lead->id),
                    'prefix' => $this->settingService->get('lead_prefix'),
                    'number' => 1000 + $lead->id,
                ]);
            }

            foreach (Deal::whereNull('number')->get() as $deal) {
                $this->info('Updating Laravel CRM deal #'.$deal->id);

                $deal->update([
                    'deal_id' => $this->settingService->get('deal_prefix').(1000 + $deal->id),
                    'prefix' => $this->settingService->get('deal_prefix'),
                    'number' => 1000 + $deal->id,
                ]);
            }

            $this->settingService->setInstallWide('db_update_1200', 1);
            $this->info('Updating Laravel CRM pipeline tables complete.');
        }

        if ($this->settingService->get('db_update_1201') == 0) {
            // Back-fill per-team CRM lookup data + pipelines for every pre-existing
            // team (e.g. a Jetstream personal team created before the CRM was
            // installed). Without this, `/leads/create` on a teams-enabled host
            // renders against an empty per-team pipeline and trips the null-pipeline
            // bug. Idempotent — safe to re-run. Skipped entirely when teams are off,
            // but the marker still flips so subsequent runs don't re-check.
            if (config('permission.teams')) {
                $this->info('Back-filling per-team CRM data for existing teams');

                $teamClass = class_exists('App\Models\Team')
                    ? 'App\Models\Team'
                    : (class_exists('App\Team') ? 'App\Team' : null);

                if ($teamClass !== null) {
                    foreach ($teamClass::all() as $team) {
                        $this->info('Back-filling per-team CRM data for team #'.$team->id);
                        TeamObserver::seedCrmDataForTeam($team->id);
                        TeamObserver::repointCrmRecordsToTeamPipelines($team->id);
                    }
                } else {
                    $this->warn('Teams enabled but no Team model found at App\Models\Team or App\Team. Skipping per-team back-fill.');
                }

                $this->info('Back-filling per-team CRM data complete.');
            }

            $this->settingService->setInstallWide('db_update_1201', 1);
        }

        // Only stamped on the success path, and install-wide for the same
        // reason as the db_update_* markers above. SystemCheckService reads it
        // back to answer "is the code ahead of the database?", so stamping it
        // after a partial run would silence the very alert that would have told
        // the operator to re-run this command.
        $this->settingService->setInstallWide(
            self::DB_VERSION_SETTING,
            config('laravel-crm.version')
        );

        $this->info('Laravel CRM is now updated.');

        return self::SUCCESS;
    }

    /**
     * Re-run the lookup-data seeders that upgrading hosts previously had to
     * know about and run by hand.
     *
     * All six are idempotent — updateOrInsert / firstOrCreate / existence-
     * checked inserts throughout — so re-running revokes nothing and duplicates
     * nothing. None is fatal: they seed reference data, not schema, and a host
     * that has customised its own lookup rows should not have its deploy fail
     * over one of them.
     *
     * laravelcrm:permissions and friends only do anything with teams on; they
     * fan the global CRM roles and lookup data out to each team.
     */
    protected function seedLookupData(): void
    {
        $commands = ['laravelcrm:lead-sources'];

        if (config('laravel-crm.teams')) {
            $commands = array_merge($commands, [
                'laravelcrm:permissions',
                'laravelcrm:labels',
                'laravelcrm:addresstypes',
                'laravelcrm:contacttypes',
                'laravelcrm:organizationtypes',
            ]);
        }

        $this->info('Seeding lookup data...');

        foreach ($commands as $command) {
            try {
                $this->callSilent($command);
            } catch (\Throwable $e) {
                $this->warn("Could not run {$command}: ".$e->getMessage());
                $this->warn("Run \"php artisan {$command}\" manually to finish seeding it.");
            }
        }
    }

    /**
     * Whether to go ahead.
     *
     * Only ever asks on a production console with a real operator in front of
     * it. Defaults to yes so a deploy script that inherited the old
     * no-confirmation behaviour and does not pass --force still proceeds.
     */
    protected function confirmToProceed(): bool
    {
        if ($this->option('force') || ! $this->input->isInteractive()) {
            return true;
        }

        if (! app()->environment('production')) {
            return true;
        }

        return $this->confirm('Application in production. Run Laravel CRM database updates?', true);
    }
}
