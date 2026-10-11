<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Services\Agent\CrmHeartbeatService;

class LaravelCrmAgentHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:agent-heartbeat {--force : Force notification dispatch even if metrics are normal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the autonomous CRM agent heartbeat to evaluate tasks and lead alerts';

    /**
     * Execute the console command.
     */
    public function handle(CrmHeartbeatService $heartbeat): int
    {
        $this->info('Running CRM Agent Heartbeat evaluation...');

        $force = (bool) $this->option('force');
        $result = $heartbeat->runHeartbeat($force);

        if ($result['action_taken']) {
            $this->info('✅ Heartbeat briefing generated and sent to Telegram.');
            $this->line('');
            $this->line($result['briefing'] ?? '');
        } else {
            $this->comment('ℹ️ Heartbeat nominal: '.($result['message'] ?? 'No action required'));
        }

        return self::SUCCESS;
    }
}
