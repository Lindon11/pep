<?php

namespace App\Plugins\PeptideVendorsBot\Controllers;

use App\Plugins\PeptideVendorsBot\Models\IptvLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LimitlessWebhookController extends Controller
{
    private string $token = '8728835633:AAF7K5TIQWRRMVlTbB1R7KUpAv5I04dVAPw';

    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return response()->json(['ok' => true]);
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $userId = (string) ($message['from']['id'] ?? '');
        $userName = (string) ($message['from']['first_name'] ?? '');
        $username = (string) ($message['from']['old_username'] ?? '');
        $lastName = (string) ($message['from']['last_name'] ?? '');

        if ($chatId !== '-1002053196818') {
            // Handle deep link: /start username
            if (preg_match('/^\/start\s+(.+)/i', $text, $m)) {
                $iptvUsername = trim($m[1]);
                $iptvUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $iptvUsername);
                if ($iptvUsername !== '') {
                    $line = IptvLine::where('old_username', $iptvUsername)->orWhere('new_username', $iptvUsername)->first();
                    if ($line && !$line->telegram_user_id) {
                        $line->update([
                            'telegram_user_id' => $userId,
                            'telegram_username' => $username ? "@{$username}" : $userName,
                            'telegram_first_name' => $userName,
                            'telegram_last_name' => $lastName,
                            'linked_at' => now(),
                            'can_dm' => true,
                        ]);
                    }
                }
                $this->sendWelcome($chatId, $userName);
                return response()->json(['ok' => true]);
            }

            $fwd = $message['forward_from'] ?? null;
            if (is_array($fwd) && $text !== '') {
                $this->handleForward($message, $chatId, $text);
            } else {
                $this->sendWelcome($chatId, $userName);
            }
            return response()->json(['ok' => true]);
        }

        if ($text === '') {
            return response()->json(['ok' => true]);
        }

        $lines = explode("\n", $text);
        $linked = 0;
        foreach ($lines as $rawLine) {
            $iptvUsername = trim($rawLine);
            do {
                $prev = $iptvUsername;
                $iptvUsername = preg_replace('/^(?:username|user|name)\s*/i', '', $iptvUsername);
            } while ($iptvUsername !== $prev);
            $iptvUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $iptvUsername);

            if ($iptvUsername === '' || strlen($iptvUsername) < 3) continue;

            $line = IptvLine::where('old_username', 'like', $iptvUsername . '%')->orderBy('old_username')->first();
            if (!$line || $line->telegram_user_id) continue;

            $line->update([
                'telegram_user_id' => $userId,
                'telegram_username' => $username ? "@{$username}" : $userName,
                'telegram_first_name' => $userName,
                'telegram_last_name' => $lastName,
                'linked_at' => now(),
                'can_dm' => true,
            ]);
            $linked++;
        }

        if ($linked > 0) {
            $this->sendWelcome($userId, $userName);
        }

        return response()->json(['ok' => true]);
    }

    private function sendWelcome(string $chatId, string $name): void
    {
        $keyboard = json_encode([
            'inline_keyboard' => [[
                ['text' => '🔑 Get My Login Details', 'callback_data' => 'get_login'],
            ]],
        ]);

        try {
            Http::timeout(10)->asForm()->post(
                "https://api.telegram.org/bot{$this->token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => "Welcome {$name}! I can help you retrieve your IPTV login details.",
                    'reply_markup' => $keyboard,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('limitless.welcome_failed', ['error' => $e->getMessage()]);
        }
    }

    private function handleCallbackQuery(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $userId = (string) ($callback['from']['id'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');

        Http::timeout(5)->asForm()->post(
            "https://api.telegram.org/bot{$this->token}/answerCallbackQuery",
            ['callback_query_id' => $callbackId]
        );

        if ($data !== 'get_login') return;

        $lines = IptvLine::where('telegram_user_id', $userId)->get();

        if ($lines->isEmpty()) {
            $text = "No IPTV accounts found linked to your Telegram.";
        } else {
            $parts = [];
            foreach ($lines as $line) {
                $parts[] = "Username: {$line->old_username}\nPassword: {$line->password}\nExpiry Date: {$line->expire_date}";
            }
            $text = "Your IPTV Account(s):\n\n" . implode("\n\n", $parts);
        }

        try {
            Http::timeout(10)->asForm()->post(
                "https://api.telegram.org/bot{$this->token}/sendMessage",
                ['chat_id' => $chatId, 'text' => $text]
            );
        } catch (\Throwable $e) {}
    }

    private function handleForward(array $message, string $chatId, string $text): void
    {
        $fwd = $message['forward_from'] ?? null;
        if (!is_array($fwd)) return;

        $fwdId = (string) ($fwd['id'] ?? '');
        $fwdName = trim(($fwd['first_name'] ?? '') . ' ' . ($fwd['last_name'] ?? ''));
        $fwdUser = (string) ($fwd['old_username'] ?? '');

        $words = preg_split('/[\s,;.!?]+/', $text);
        $linked = 0;
        $notFound = 0;
        $already = 0;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $word);
            if (strlen($clean) < 3) continue;
            $line = IptvLine::where('old_username', 'like', $clean . '%')->orWhere('new_username', 'like', $clean . '%')->orderBy('old_username')->first();
            if (!$line) { $notFound++; continue; }
            if ($line->telegram_user_id) { $already++; continue; }
            $line->update([
                'telegram_user_id' => $fwdId,
                'telegram_username' => $fwdUser ? "@{$fwdUser}" : $fwdName,
                'telegram_first_name' => $fwd['first_name'] ?? '',
                'telegram_last_name' => $fwd['last_name'] ?? '',
                'linked_at' => now(),
                'can_dm' => true,
            ]);
            $linked++;
            $this->sendWelcome($fwdId, $fwdName);
        }

        $parts = [];
        if ($linked > 0) $parts[] = "Linked {$linked} account(s)";
        if ($already > 0) $parts[] = "{$already} already linked";
        if ($notFound > 0) $parts[] = "{$notFound} not found";
        $reply = implode(', ', $parts) ?: 'No IPTV usernames found';

        try {
            Http::timeout(10)->asForm()->post(
                "https://api.telegram.org/bot{$this->token}/sendMessage",
                ['chat_id' => $chatId, 'text' => $reply]
            );
        } catch (\Throwable $e) {}
    }
}
