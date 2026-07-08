<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\Http;

class TelegramNotificationService
{
    public static function notifyVendorRequest($user, $requestId): void
    {
        $token = env('PEPTIDE_VENDORS_BOT_TOKEN');
        $chatId = env('PEPTIDE_VENDORS_CHAT_ID');
        if (!$token || !$chatId) return;

        $text = "🚨 <b>New Vendor Request</b>\nUser: {$user->name} (@{$user->username})";

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => '✅ Approve', 'callback_data' => "approve{$requestId}"],
                        ['text' => '❌ Deny', 'callback_data' => "deny{$requestId}"],
                    ]],
                ]),
            ]);
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
