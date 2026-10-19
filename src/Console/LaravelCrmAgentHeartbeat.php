<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Services\Agent\CrmHeartbeatService;
use VentureDrake\LaravelCrm\Services\Agent\CrmToolsService;

class LaravelCrmAgentHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:agent-heartbeat
                            {--force : Force notification dispatch even if metrics are normal or suppressed}
                            {--mode= : Optionally set the proactivity mode (high, balanced, low, silent)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the autonomous CRM agent heartbeat to evaluate tasks and lead alerts';

    /**
     * Execute the console command.
     */
    public function handle(
        CrmHeartbeatService $heartbeat,
        CrmToolsService $tools
    ): int {
        if ($mode = $this->option('mode')) {
            $updated = $tools->updateNotificationSettings(['mode' => $mode]);
            $this->info("🎛️ Proactivity mode set to: {$updated['mode_label']}");
        }

        $this->info('Running CRM Agent Heartbeat evaluation...');

        $force = (bool) $this->option('force');
        $result = $heartbeat->runHeartbeat($force);

        if ($result['action_taken']) {
            $this->info('✅ Heartbeat briefing generated and sent to Telegram.');
            $this->line('');
            $this->line($result['briefing'] ?? '');
        } else {
            $this->comment("ℹ️ Heartbeat [{$result['status']}]: ".($result['message'] ?? 'No action required'));
        }

        return self::SUCCESS;
    }
}
