<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Services\Agent\TelegramBotService;

class LaravelCrmAgentTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:agent-telegram-webhook
                            {--url= : The public webhook URL to register with Telegram}
                            {--delete : Remove the webhook and restore polling mode}
                            {--info : Display current Telegram webhook status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure or inspect the Telegram webhook for the CRM agent';

    /**
     * Execute the console command.
     */
    public function handle(TelegramBotService $telegram): int
    {
        if ($this->option('delete')) {
            $this->info('Deleting Telegram webhook...');
            if ($telegram->deleteWebhook()) {
                $this->info('✅ Telegram webhook deleted. Bot is now ready for polling.');

                return self::SUCCESS;
            }

            $this->error('Failed to delete webhook.');

            return self::FAILURE;
        }

        $url = $this->option('url');

        if (! $url) {
            $defaultHost = config('app.url', 'https://crm.techfusion-alchemy.xyz');
            $url = rtrim($defaultHost, '/').'/p/agent/telegram/webhook';
        }

        $this->info("Registering Telegram webhook at: {$url}");

        if ($telegram->setWebhook($url)) {
            $this->info("✅ Telegram webhook successfully registered to: {$url}");

            return self::SUCCESS;
        }

        $this->error('Failed to register webhook with Telegram.');

        return self::FAILURE;
    }
}
