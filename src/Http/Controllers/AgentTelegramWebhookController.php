<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VentureDrake\LaravelCrm\Services\Agent\CrmHeartbeatService;
use VentureDrake\LaravelCrm\Services\Agent\CrmToolsService;
use VentureDrake\LaravelCrm\Services\Agent\GeminiAgentService;
use VentureDrake\LaravelCrm\Services\Agent\TelegramBotService;

class AgentTelegramWebhookController extends Controller
{
    /**
     * Handle incoming webhook updates from Telegram.
     */
    public function handle(
        Request $request,
        TelegramBotService $telegram,
        GeminiAgentService $gemini,
        CrmHeartbeatService $heartbeat,
        CrmToolsService $tools
    ): JsonResponse {
        $update = $request->all();

        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'] ?? null;
            $text = trim($message['text'] ?? '');

            if ($chatId && ! empty($text)) {
                if ($text === '/start' || $text === '/setup') {
                    $welcome = "👋 Welcome Muzi! I am your autonomous **Alchemy CRM Chief of Staff**.\n\n".
                        "I monitor your pipeline velocity, track tasks, and execute CRM actions.\n\n".
                        "• *What's the status of our CRM today?*\n".
                        "• *Show me overdue tasks*\n".
                        "• *Give me a summary of lead LD-4504*\n".
                        '• *Switch to daily digest mode* (or type `/mode`)';

                    $telegram->sendMessage($chatId, $welcome, [
                        'inline_keyboard' => [
                            [
                                ['text' => '📋 Tasks List', 'callback_data' => 'view_tasks'],
                                ['text' => '🎛️ Notification Mode', 'callback_data' => 'agent_mode_menu'],
                            ],
                        ],
                    ]);
                } elseif (preg_match('/^\/(mode|notifications|settings)(?:\s+(.+))?$/i', $text, $matches)) {
                    $arg = trim($matches[2] ?? '');
                    $flash = null;
                    if ($arg !== '') {
                        $res = $tools->updateNotificationSettings(['mode' => $arg, 'clear_snooze' => true]);
                        $flash = "Mode switched to {$res['mode_label']}";
                    }
                    $deck = $tools->buildModeControlDeck($flash);
                    $telegram->sendMessage($chatId, $deck['text'], $deck['reply_markup']);
                } elseif (preg_match('/^\/snooze(?:\s+(\d+(?:\.\d+)?))?$/i', $text, $matches)) {
                    $hours = isset($matches[1]) ? (float) $matches[1] : 3.0;
                    $tools->updateNotificationSettings(['snooze_hours' => $hours]);
                    $deck = $tools->buildModeControlDeck($hours > 0 ? "💤 Alerts snoozed for {$hours}h" : '🔔 Alerts resumed');
                    $telegram->sendMessage($chatId, $deck['text'], $deck['reply_markup']);
                } else {
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
                }
            }
        } elseif (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $callbackId = $callback['id'] ?? null;
            $chatId = $callback['message']['chat']['id'] ?? null;
            $messageId = $callback['message']['message_id'] ?? null;
            $data = $callback['data'] ?? '';

            if ($chatId && (
                $data === 'agent_mode_menu'
                || str_starts_with($data, 'agent_mode:')
                || str_starts_with($data, 'agent_snooze:')
            )) {
                $controlResult = $heartbeat->handleTelegramControlCallback($data, $chatId, $messageId);
                if ($callbackId) {
                    $telegram->answerCallbackQuery($callbackId, $controlResult['toast'] ?? 'Updated');
                }

                return response()->json(['ok' => true]);
            }

            if ($callbackId) {
                $telegram->answerCallbackQuery($callbackId, 'Processing request...');
            }

            if ($chatId) {
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

        return response()->json(['ok' => true]);
    }
}
