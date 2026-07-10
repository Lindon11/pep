<?php

namespace App\Plugins\PeptideVendorsBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramService
{
    private string $apiBase;

    public function __construct()
    {
        $this->apiBase = rtrim((string) config('plugins.PeptideVendorsBot.api_base', 'https://api.telegram.org'), '/');
    }

    public function botToken(): string
    {
        $token = trim((string) config('plugins.PeptideVendorsBot.bot_token', ''));

        if ($token === '') {
            throw new RuntimeException('Peptide Vendors bot token is not configured. Set PEPTIDE_VENDORS_BOT_TOKEN in .env');
        }

        return $token;
    }

    public function sendMessage(string $chatId, string $text, ?int $messageThreadId = null, array $options = []): array
    {
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Telegram message cannot be empty.');
        }

        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/sendMessage';

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => 'true',
        ];

        if ($messageThreadId !== null && $messageThreadId > 0) {
            $payload['message_thread_id'] = (string) $messageThreadId;
        }

        foreach ($options as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $payload[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $value;
        }

        return $this->sendRequest($url, $payload);
    }

    public function getForumTopics(string $chatId): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/getForumTopics';

        $allTopics = [];
        $offset = 0;

        try {
            do {
                $payload = [
                    'chat_id' => $chatId,
                    'limit' => '100',
                ];
                if ($offset > 0) {
                    $payload['offset'] = (string) $offset;
                }

                $response = Http::timeout(15)
                    ->connectTimeout(10)
                    ->asForm()
                    ->post($url, $payload);

                $decoded = $response->json();

                if (is_array($decoded) && ($decoded['ok'] ?? false)) {
                    $result = $decoded['result'] ?? [];
                    $topics = $result['topics'] ?? [];
                    foreach ($topics as $topic) {
                        $allTopics[] = [
                            'topic_id' => (int) ($topic['message_thread_id'] ?? 0),
                            'name' => (string) ($topic['name'] ?? 'Unnamed'),
                            'icon_color' => $topic['icon_color'] ?? null,
                            'icon_custom_emoji_id' => $topic['icon_custom_emoji_id'] ?? null,
                        ];
                    }
                    $offset = $result['next_offset'] ?? 0;
                } else {
                    break;
                }
            } while ($offset > 0);
        } catch (Throwable $e) {
            Log::warning('peptidebot.forum_topics_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $allTopics;
    }

    public function restrictMember(string $chatId, string $userId): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/restrictChatMember';

        return $this->sendRequest($url, [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode([
                'can_send_messages' => false,
                'can_send_audios' => false,
                'can_send_documents' => false,
                'can_send_photos' => false,
                'can_send_videos' => false,
                'can_send_video_notes' => false,
                'can_send_voice_notes' => false,
                'can_send_polls' => false,
                'can_send_other_messages' => false,
                'can_add_web_page_previews' => false,
                'can_change_info' => false,
                'can_invite_users' => false,
                'can_pin_messages' => false,
                'can_manage_topics' => false,
            ]),
        ]);
    }

    public function unrestrictMember(string $chatId, string $userId): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/restrictChatMember';

        return $this->sendRequest($url, [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode([
                'can_send_messages' => true,
                'can_send_audios' => true,
                'can_send_documents' => true,
                'can_send_photos' => true,
                'can_send_videos' => true,
                'can_send_video_notes' => true,
                'can_send_voice_notes' => true,
                'can_send_polls' => true,
                'can_send_other_messages' => true,
                'can_add_web_page_previews' => true,
                'can_change_info' => false,
                'can_invite_users' => true,
                'can_pin_messages' => false,
                'can_manage_topics' => false,
            ]),
        ]);
    }


    public function banChatMember(string $chatId, string $userId): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/banChatMember';

        return $this->sendRequest($url, [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function sendWelcomeButton(string $chatId, string $text, array $button, array $extraButtons = []): array
    {
        $keyboard = [[$button]];

        if (!empty($extraButtons)) {
            $keyboard[] = $extraButtons;
        }

        return $this->sendMessage($chatId, $text, null, [
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }

    public function answerCallback(string $callbackQueryId, string $text, bool $showAlert = false): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/answerCallbackQuery';

        return $this->sendRequest($url, [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? 'true' : 'false',
        ]);
    }

    public function editMessageText(string $chatId, int $messageId, string $text, array $options = []): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/editMessageText';

        $payload = [
            'chat_id' => $chatId,
            'message_id' => (string) $messageId,
            'text' => $text,
        ];

        foreach ($options as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $payload[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $value;
        }

        return $this->sendRequest($url, $payload);
    }

    public function deleteMessage(string $chatId, int $messageId): array
    {
        $token = $this->botToken();
        $url = $this->apiBase . '/bot' . $token . '/deleteMessage';

        return $this->sendRequest($url, [
            'chat_id' => $chatId,
            'message_id' => (string) $messageId,
        ]);
    }

    public function sendWelcome(string $chatId, array $newUser, ?int $messageThreadId = null): array
    {
        $welcomeMessage = trim((string) config('plugins.PeptideVendorsBot.welcome_message', ''));
        $firstName = htmlspecialchars((string) ($newUser['first_name'] ?? 'there'), ENT_QUOTES, 'UTF-8');
        $message = str_replace('{first_name}', $firstName, $welcomeMessage);

        return $this->sendMessage($chatId, $message, $messageThreadId, ['parse_mode' => 'HTML']);
    }

    private function sendRequest(string $url, array $payload): array
    {
        try {
            $response = Http::timeout((int) config('plugins.PeptideVendorsBot.timeout', 25))
                ->connectTimeout((int) config('plugins.PeptideVendorsBot.connect_timeout', 10))
                ->asForm()
                ->post($url, $payload);

            $body = $response->body();
            $statusCode = $response->status();
            $decoded = $response->json();

            if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
                throw new RuntimeException(sprintf(
                    'Telegram API HTTP %d: %s',
                    $statusCode,
                    mb_substr((string) $body, 0, 500)
                ));
            }

            return $decoded;
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Telegram request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
