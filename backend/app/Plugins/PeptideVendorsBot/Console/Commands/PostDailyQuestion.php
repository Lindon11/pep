<?php

namespace App\Plugins\PeptideVendorsBot\Console\Commands;

use App\Plugins\PeptideVendorsBot\Models\DailyQuestion;
use App\Plugins\PeptideVendorsBot\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class PostDailyQuestion extends Command
{
    protected $signature = 'peptide:post-question';
    protected $description = 'Post a random daily question to the Peptides discussions topic';

    public function handle(TelegramService $telegram): int
    {
        $question = DailyQuestion::where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('last_posted_at')
                  ->orWhere('last_posted_at', '<', now()->subYear());
            })
            ->inRandomOrder()
            ->first();

        if (!$question) {
            $this->warn('No questions in the pool. Add some via the admin panel.');
            return 0;
        }

        $chatId = config('plugins.PeptideVendorsBot.target_chat_id', '');

        if ($chatId === '') {
            $this->error('Target chat ID not configured.');
            return 1;
        }

        try {
            $telegram->sendMessage($chatId, $question->question, 259, ['parse_mode' => 'HTML']);

            $question->update(['last_posted_at' => now()]);

            $this->info('Posted: ' . $question->question);
            return 0;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
