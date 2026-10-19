<?php

namespace VentureDrake\LaravelCrm\Services\Agent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected ?string $token;

    protected ?string $adminChatId;

    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('laravel-crm.agent.telegram_bot_token');
        $this->adminChatId = config('laravel-crm.agent.telegram_admin_chat_id');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Test connection and retrieve bot profile.
     */
    public function getMe(): ?array
    {
        try {
            $response = Http::withoutVerifying()->timeout(10)->get("{$this->baseUrl}/getMe");

            return $response->successful() ? $response->json('result') : null;
        } catch (\Throwable $e) {
            Log::error('Telegram getMe error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Send message to a chat id.
     */
    public function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null): bool
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if ($replyMarkup) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/sendMessage", $payload);

            if (! $response->successful()) {
                // If markdown parsing fails due to special characters, fallback to plain text
                $payload['parse_mode'] = null;
                $fallback = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/sendMessage", $payload);

                return $fallback->successful();
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send alert to the configured admin chat.
     */
    public function sendAlertToAdmin(string $text, ?array $replyMarkup = null): bool
    {
        if (! $this->adminChatId) {
            Log::warning('Telegram adminChatId not configured.');

            return false;
        }

        return $this->sendMessage($this->adminChatId, $text, $replyMarkup);
    }

    /**
     * Fetch long-polling updates.
     */
    public function getUpdates(?int $offset = null, int $timeout = 25): array
    {
        try {
            $payload = [
                'timeout' => $timeout,
                'allowed_updates' => ['message', 'callback_query'],
            ];

            if ($offset) {
                $payload['offset'] = $offset;
            }

            $response = Http::withoutVerifying()->timeout($timeout + 5)->post("{$this->baseUrl}/getUpdates", $payload);

            if ($response->successful()) {
                return $response->json('result') ?? [];
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('Telegram getUpdates error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Answer inline button callback.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        try {
            $payload = [
                'callback_query_id' => $callbackQueryId,
                'show_alert' => $showAlert,
            ];
            if ($text) {
                $payload['text'] = $text;
            }

            $response = Http::withoutVerifying()->timeout(5)->post("{$this->baseUrl}/answerCallbackQuery", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Edit an existing message text and/or inline keyboard in place.
     */
    public function editMessageText(string|int $chatId, int $messageId, string $text, ?array $replyMarkup = null): bool
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if ($replyMarkup) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/editMessageText", $payload);

            if (! $response->successful()) {
                $payload['parse_mode'] = null;
                $fallback = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/editMessageText", $payload);

                return $fallback->successful();
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram editMessageText error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Edit only the inline keyboard of an existing message in place.
     */
    public function editMessageReplyMarkup(string|int $chatId, int $messageId, ?array $replyMarkup = null): bool
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => $replyMarkup ?? ['inline_keyboard' => []],
            ];

            $response = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/editMessageReplyMarkup", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram editMessageReplyMarkup error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Set webhook URL for Telegram.
     */
    public function setWebhook(string $url): bool
    {
        try {
            $payload = [
                'url' => $url,
                'allowed_updates' => ['message', 'callback_query'],
            ];

            $response = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/setWebhook", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram setWebhook error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Delete webhook to enable long-polling.
     */
    public function deleteWebhook(bool $dropPending = false): bool
    {
        try {
            $response = Http::withoutVerifying()->timeout(10)->post("{$this->baseUrl}/deleteWebhook", [
                'drop_pending_updates' => $dropPending,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram deleteWebhook error: '.$e->getMessage());

            return false;
        }
    }
}
