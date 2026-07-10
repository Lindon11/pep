<?php

namespace App\Plugins\PeptideVendorsBot\Console\Commands;

use App\Plugins\PeptideVendorsBot\Models\ReminderMessage;
use App\Plugins\PeptideVendorsBot\Models\WelcomeMessage;
use App\Plugins\PeptideVendorsBot\Models\Verification;
use App\Plugins\PeptideVendorsBot\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class SendVerificationReminder extends Command
{
    protected $signature = 'peptide:remind-unverified';
    protected $description = 'Send verification reminder to members who joined but haven\'t agreed to the rules';

    public function handle(TelegramService $telegram): int
    {
        $chatId = '-1004376480189';

        $welcomed = WelcomeMessage::where('banned', false)->get();
        $verifiedIds = Verification::pluck('user_id')->toArray();

        $sent = 0;
        $errors = 0;

        foreach ($welcomed as $w) {
            if (in_array($w->user_id, $verifiedIds)) {
                continue;
            }

            try {
                $result = $telegram->sendMessage($chatId,
                    "<a href=\"tg://user?id={$w->user_id}\">{$w->first_name}</a>, you haven't agreed to the rules yet! Click below to confirm and start posting.",
                    null,
                    [
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [[
                                ['text' => '✅ I Agree to the Rules', 'callback_data' => 'confirm']
                            ]]
                        ])
                    ]
                );

                $messageId = $result['result']['message_id'] ?? null;
                if ($messageId) {
                    ReminderMessage::create([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'user_id' => $w->user_id,
                        'sent_at' => now(),
                    ]);
                }

                $sent++;
                usleep(300000);
            } catch (Throwable $e) {
                $this->error("Failed for {$w->first_name}: {$e->getMessage()}");
                $errors++;
                sleep(5);
            }
        }

        $this->info("Reminders sent: {$sent}, errors: {$errors}");
        return 0;
    }
}
