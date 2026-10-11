<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        GeminiAgentService $gemini
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
                        '• *Delete lead LD-TFA-L1542*';

                    $telegram->sendMessage($chatId, $welcome);
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
            $data = $callback['data'] ?? '';

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
