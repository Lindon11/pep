<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\Http;

class TelegramNotificationService
{
    public static function notifyVendorRequest($user): void
    {
        $token = env('PEPTIDE_VENDORS_BOT_TOKEN');
        $chatId = env('PEPTIDE_VENDORS_CHAT_ID');
        if (!$token || !$chatId) return;

        $text = "🚨 <b>New Vendor Request</b>\nUser: {$user->name} (@{$user->username})\n\n<a href=\"https://pepvguides.com/admin/vendor-access-requests\">Open Admin Panel</a> to approve or deny.";

        try {
            Http::get("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            // Silent fail
        }
    }
}
