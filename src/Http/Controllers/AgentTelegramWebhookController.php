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
                        '• *Mark task [id] as completed*';

                    $telegram->sendMessage($chatId, $welcome);
                } else {
                    $response = $gemini->executeTurn($text);
                    $telegram->sendMessage($chatId, $response);
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

                $response = $gemini->executeTurn($prompt);
                $telegram->sendMessage($chatId, $response);
            }
        }

        return response()->json(['ok' => true]);
    }
}
