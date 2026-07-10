<?php

namespace App\Plugins\PeptideVendorsBot\Console\Commands;

use App\Plugins\PeptideVendorsBot\Models\ReminderMessage;
use App\Plugins\PeptideVendorsBot\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class CleanupOldReminders extends Command
{
    protected $signature = 'peptide:cleanup-reminders';
    protected $description = 'Delete verification reminder messages older than 24 hours';

    public function handle(TelegramService $telegram): int
    {
        $cutoff = now()->subHours(24);
        $old = ReminderMessage::where('sent_at', '<', $cutoff)->get();

        $deleted = 0;
        $errors = 0;

        foreach ($old as $reminder) {
            try {
                $telegram->deleteMessage($reminder->chat_id, $reminder->message_id);
                $reminder->delete();
                $deleted++;
                usleep(200000);
            } catch (Throwable $e) {
                $reminder->delete();
                $errors++;
            }
        }

        $this->info("Deleted {$deleted} old reminders, errors: {$errors}");
        return 0;
    }
}
