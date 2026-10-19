<?php

namespace VentureDrake\LaravelCrm\Console;

use Illuminate\Console\Command;
use VentureDrake\LaravelCrm\Services\Agent\CrmHeartbeatService;
use VentureDrake\LaravelCrm\Services\Agent\CrmToolsService;
use VentureDrake\LaravelCrm\Services\Agent\GeminiAgentService;
use VentureDrake\LaravelCrm\Services\Agent\TelegramBotService;

class LaravelCrmAgentTelegramPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravelcrm:agent-telegram-poll {--once : Run only a single polling request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Telegram for incoming messages and execute CRM agent turns';

    /**
     * Execute the console command.
     */
    public function handle(
        TelegramBotService $telegram,
        GeminiAgentService $gemini,
        CrmHeartbeatService $heartbeat,
        CrmToolsService $tools
    ): int {
        $this->info('Starting Telegram Bot Polling service...');

        $botProfile = $telegram->getMe();
        if (! $botProfile) {
            $this->error('Failed to connect to Telegram API. Check your TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        $this->info("Connected to @{$botProfile['username']} ({$botProfile['first_name']})");

        $offset = null;
        $isOnce = (bool) $this->option('once');

        do {
            $updates = $telegram->getUpdates($offset, 15);

            foreach ($updates as $update) {
                $offset = $update['update_id'] + 1;

                if (isset($update['message'])) {
                    $this->processMessage($update['message'], $telegram, $gemini, $tools);
                } elseif (isset($update['callback_query'])) {
                    $this->processCallbackQuery($update['callback_query'], $telegram, $gemini, $heartbeat);
                }
            }

            if ($isOnce) {
                break;
            }

            usleep(250000); // 250ms sleep
        } while (true);

        return self::SUCCESS;
    }

    protected function processMessage(
        array $message,
        TelegramBotService $telegram,
        GeminiAgentService $gemini,
        CrmToolsService $tools
    ): void {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');
        $sender = $message['from']['first_name'] ?? 'User';

        if (! $chatId || empty($text)) {
            return;
        }

        $this->line("[MSG] From {$sender} ({$chatId}): {$text}");

        if ($text === '/start' || $text === '/setup') {
            $welcome = "👋 Welcome Muzi! I am your autonomous **Alchemy CRM Chief of Staff**.\n\n".
                "I continuously monitor your leads, track task deadlines, advance pipeline velocity, and execute actions on command.\n\n".
                "**What you can ask me anytime:**\n".
                "• *What's the status of our CRM today?*\n".
                "• *Show me overdue tasks*\n".
                "• *Give me a summary of lead LD-4504*\n".
                "• *Find leads in the Systematic Macro niche*\n".
                '• *Switch to daily digest mode* (or type `/mode`)';

            $telegram->sendMessage($chatId, $welcome, [
                'inline_keyboard' => [
                    [
                        ['text' => '📋 Tasks List', 'callback_data' => 'view_tasks'],
                        ['text' => '🎛️ Notification Mode', 'callback_data' => 'agent_mode_menu'],
                    ],
                ],
            ]);

            return;
        }

        if (preg_match('/^\/(mode|notifications|settings)(?:\s+(.+))?$/i', $text, $matches)) {
            $arg = trim($matches[2] ?? '');
            $flash = null;
            if ($arg !== '') {
                $res = $tools->updateNotificationSettings(['mode' => $arg, 'clear_snooze' => true]);
                $flash = "Mode switched to {$res['mode_label']}";
            }
            $deck = $tools->buildModeControlDeck($flash);
            $telegram->sendMessage($chatId, $deck['text'], $deck['reply_markup']);
            $this->info('🎛️ Sent Proactivity Control Deck to Telegram.');

            return;
        }

        if (preg_match('/^\/snooze(?:\s+(\d+(?:\.\d+)?))?$/i', $text, $matches)) {
            $hours = isset($matches[1]) ? (float) $matches[1] : 3.0;
            $tools->updateNotificationSettings(['snooze_hours' => $hours]);
            $deck = $tools->buildModeControlDeck($hours > 0 ? "💤 Alerts snoozed for {$hours}h" : '🔔 Alerts resumed');
            $telegram->sendMessage($chatId, $deck['text'], $deck['reply_markup']);
            $this->info('💤 Updated snooze timer and sent Control Deck.');

            return;
        }

        // Run Gemini agent turn with tools
        $this->comment('🤖 Reasoning & tool execution via Gemini 2.5 Flash...');
        $historyKey = "crm_agent_history_{$chatId}";
        $history = cache()->get($historyKey, []);

        $response = $gemini->executeTurn($text, $history);

        $telegram->sendMessage($chatId, $response);

        $history[] = ['role' => 'user', 'parts' => [['text' => $text]]];
        $history[] = ['role' => 'model', 'parts' => [['text' => $response]]];
        if (count($history) > 8) {
            $history = array_slice($history, -8);
        }
        cache()->put($historyKey, $history, now()->addHours(24));

        $this->info('✅ Response sent to Telegram.');
    }

    protected function processCallbackQuery(
        array $callback,
        TelegramBotService $telegram,
        GeminiAgentService $gemini,
        CrmHeartbeatService $heartbeat
    ): void {
        $callbackId = $callback['id'];
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $data = $callback['data'] ?? '';

        if (! $chatId) {
            return;
        }

        $this->line("[CALLBACK] {$data} from {$chatId}");

        if (
            $data === 'agent_mode_menu'
            || str_starts_with($data, 'agent_mode:')
            || str_starts_with($data, 'agent_snooze:')
        ) {
            $controlResult = $heartbeat->handleTelegramControlCallback($data, $chatId, $messageId);
            $telegram->answerCallbackQuery($callbackId, $controlResult['toast'] ?? 'Updated');
            $this->info("🎛️ Processed control callback: {$data}");

            return;
        }

        $telegram->answerCallbackQuery($callbackId, 'Processing...');

        $prompt = match ($data) {
            'view_tasks' => 'List the top overdue and due tasks right now with their linked leads.',
            'view_alerts' => 'Check lead alerts: stale leads, ready to advance, and overdue tasks.',
            default => "User clicked action: {$data}. Check CRM status accordingly.",
        };

        $historyKey = "crm_agent_history_{$chatId}";
        $history = cache()->get($historyKey, []);

        $response = $gemini->executeTurn($prompt, $history);
        $telegram->sendMessage($chatId, $response);

        $history[] = ['role' => 'user', 'parts' => [['text' => $prompt]]];
        $history[] = ['role' => 'model', 'parts' => [['text' => $response]]];
        if (count($history) > 8) {
            $history = array_slice($history, -8);
        }
        cache()->put($historyKey, $history, now()->addHours(24));
    }
}
