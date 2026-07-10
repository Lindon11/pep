<?php

namespace App\Plugins\PeptideVendorsBot\Controllers;

use App\Core\Models\VendorAccessRequest;
use App\Core\Services\NotificationService;
use App\Plugins\PeptideVendorsBot\Models\TopicPermission;
use App\Plugins\PeptideVendorsBot\Models\Verification;
use App\Plugins\PeptideVendorsBot\Models\WelcomeMessage;
use App\Plugins\PeptideVendorsBot\Models\WebhookUpdate;
use App\Plugins\PeptideVendorsBot\Services\ReviewService;
use App\Plugins\PeptideVendorsBot\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    const VENDOR_APPROVAL_CHAT_ID = "5870086543";
    const FEEDBACK_CHAT_ID = '-1004376480189';
    const FEEDBACK_THREAD_ID = 1214;
    const FEEDBACK_MESSAGE_ID = 1219;

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly ReviewService $reviewService,
        private readonly NotificationService $notifications,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        if (!config('plugins.PeptideVendorsBot.webhook_enabled', false)) {
            Log::warning('peptidebot.webhook.disabled', [
                'remote_addr' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Webhook mode is disabled.'], 403);
        }

        if (!$this->validateSecretToken($request)) {
            Log::warning('peptidebot.webhook.invalid_token', [
                'remote_addr' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Invalid webhook secret token.'], 403);
        }

        $update = $request->all();

        if (empty($update)) {
            return response()->json(['ok' => false, 'error' => 'Empty payload.'], 400);
        }

        Log::info('peptidebot.webhook.received', [
            'has_message' => isset($update['message']),
            'has_callback_query' => isset($update['callback_query']),
            'has_my_chat_member' => isset($update['my_chat_member']),
            'has_chat_member' => isset($update['chat_member']),
            'update_id' => $update['update_id'] ?? null,
        ]);

        try {
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query'], $update['update_id'] ?? null);
                return response()->json(['ok' => true]);
            }

            $message = $update['message'] ?? null;

            if (is_array($message)) {
                $this->logUpdate($update['update_id'] ?? null, 'message', $message);

                if ($this->hasNewChatMembers($message)) {
                    $this->welcomeNewMembers($message);
                }

                if ($this->isSyncSellersCommand($message)) {
                    $this->syncSellers($message);
                }

                $this->handleVendorApprovalTextCommand($message);
                $this->enforceLinkSpam($message);
                $this->enforceTopicPermissions($message);

                if ($this->reviewService->isReviewTopic($message)) {
                    $parsed = $this->reviewService->parseReview($message);
                    if ($parsed !== null) {
                        $this->reviewService->storeReview($parsed, $message);
                        $this->updateVendorFeedback();
                    } else {
                        $fromId = (string) ($message['from']['id'] ?? '');
                        if ($fromId !== '8859449708') {
                            try {
                                $this->telegram->deleteMessage(
                                    (string) ($message['chat']['id'] ?? ''),
                                    (int) ($message['message_id'] ?? 0)
                                );
                            } catch (Throwable) {}
                        }
                    }
                }
            }

            if (isset($update['my_chat_member'])) {
                $this->logUpdate($update['update_id'] ?? null, 'my_chat_member', $update['my_chat_member']);
            }

            if (isset($update['chat_member'])) {
                $this->logUpdate($update['update_id'] ?? null, 'chat_member', $update['chat_member']);
                $this->handleChatMemberUpdate($update['chat_member']);
            }
        } catch (Throwable $e) {
            Log::error('peptidebot.webhook.processing_failed', [
                'error' => $e->getMessage(),
                'update_id' => $update['update_id'] ?? null,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function health(Request $request): JsonResponse
    {
        $token = trim((string) config('plugins.PeptideVendorsBot.bot_token', ''));

        $latestWelcome = WelcomeMessage::query()->orderByDesc('id')->first();

        $lastHourCount = WebhookUpdate::query()
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return response()->json([
            'ok' => true,
            'timestamp' => now()->toISOString(),
            'webhook' => [
                'enabled' => (bool) config('plugins.PeptideVendorsBot.webhook_enabled', false),
                'bot_token_configured' => $token !== '',
                'endpoint' => url('/peptide-vendors/telegram/webhook'),
                'welcome_enabled' => (bool) config('plugins.PeptideVendorsBot.welcome_enabled', true),
                'join_verification' => (bool) config('plugins.PeptideVendorsBot.join_verification_enabled', true),
            ],
            'stats' => [
                'total_updates' => WebhookUpdate::count(),
                'last_hour_updates' => $lastHourCount,
                'total_welcomes' => WelcomeMessage::count(),
                'restricted_topics' => TopicPermission::distinct('topic_id')->count('topic_id'),
                'allowed_users' => TopicPermission::count(),
                'verified_members' => Verification::where('agreed', true)->count(),
            ],
            'latest_welcome' => $latestWelcome ? [
                'id' => $latestWelcome->id,
                'chat_id' => $latestWelcome->chat_id,
                'user_id' => $latestWelcome->user_id,
                'first_name' => $latestWelcome->first_name,
                'created_at' => $latestWelcome->created_at?->toISOString(),
            ] : null,
        ]);
    }

    private function handleCallbackQuery(array $callbackQuery, mixed $updateId): void
    {
        $callbackQueryId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $from = $callbackQuery['from'] ?? [];
        $userId = (string) ($from['id'] ?? '');
        $userName = (string) ($from['first_name'] ?? '');
        $username = (string) ($from['username'] ?? '');
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? $callbackQuery['chat']['id'] ?? '');

        if ($data === 'show_rules' || str_starts_with($data, 'show_rules:')) {
            $rulesText = "📌 <b>Peptide Vendors Community Rules</b>\n\n"
                . "1. <b>Be respectful</b> – No harassment, hate speech, or personal attacks.\n"
                . "2. <b>No scamming</b> – Report suspicious activity to admins. All transactions are at your own risk.\n"
                . "3. <b>Vendors won't DM first</b> – If someone contacts you unsolicited, treat it as a potential scam.\n"
                . "4. <b>Keep topics organised</b> – Post in the correct topics.\n"
                . "5. <b>Keep discussions public</b> – Ask in the group so everyone can benefit.\n"
                . "6. <b>Leave reviews</b> – Share your experience to help the community.\n"
                . "7. <b>18+ only</b> – This community is for adults only.\n"
                . "8. <b>New vendors must be vetted</b> – Contact an admin before posting.\n\n"
                . "❓ If you're unsure about a seller, ask an admin.\n\n"
                . "Click <b>Confirm</b> below to agree and gain access.";

            $this->telegram->sendMessage($chatId, $rulesText, 154, [
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => '✅ Confirm', 'callback_data' => 'confirm'],
                    ]],
                ]),
            ]);

            $this->telegram->answerCallback($callbackQueryId, '📋 Rules posted in the Rules topic. Read and confirm there.');
            return;
        }

        if ($data === 'confirm' || $data === 'agree_rules') {
            $existing = Verification::where('user_id', $userId)->first();

            if ($existing && $existing->agreed) {
                $this->telegram->answerCallback($callbackQueryId, 'You already agreed to the rules!', true);
                return;
            }

            try {
                $this->telegram->unrestrictMember($chatId, $userId);

                Verification::updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'user_name' => $userName,
                        'username' => $username,
                        'agreed' => true,
                        'agreed_at' => now(),
                    ]
                );

                $this->telegram->answerCallback($callbackQueryId, '✅ Welcome! You can now post.');

                $this->telegram->sendMessage($chatId, "Welcome {$userName}! You've agreed to the rules and can now participate.");

                Log::info('peptidebot.verification.agreed', [
                    'user_id' => $userId,
                    'user_name' => $userName,
                ]);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
            $isAdmin = str_contains($msg, 'user is an administrator') || str_contains($msg, 'can\'t remove chat owner');

            Verification::updateOrCreate(
                ['user_id' => $userId],
                ['user_name' => $userName, 'username' => $username, 'agreed' => true, 'agreed_at' => now()]
            );

            if ($isAdmin) {
                $this->telegram->answerCallback($callbackQueryId, '✅ You\'re an admin - no restrictions needed!');
            } else {
                Log::error('peptidebot.verification.failed', [
                    'user_id' => $userId,
                    'error' => $msg,
                ]);
                $this->telegram->answerCallback($callbackQueryId, 'Error verifying. Contact an admin.', true);
            }
        }
        }
    }
    
    private function validateSecretToken(Request $request): bool
    {
        $expectedSecret = trim((string) config('plugins.PeptideVendorsBot.webhook_secret', ''));

        if ($expectedSecret === '') {
            return true;
        }

        $providedSecret = trim((string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''));

        if ($providedSecret === '') {
            return false;
        }

        return hash_equals($expectedSecret, $providedSecret);
    }

    private function hasNewChatMembers(array $message): bool
    {
        if (!empty($message['new_chat_members']) && is_array($message['new_chat_members'])) {
            return true;
        }

        if (!empty($message['new_chat_participant']) && is_array($message['new_chat_participant'])) {
            return true;
        }

        if (!empty($message['left_chat_member']) && is_array($message['left_chat_member'])) {
            return false;
        }

        return false;
    }

    private function welcomeNewMembers(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $messageThreadId = isset($message['is_topic_message']) && $message['is_topic_message']
            ? (int) ($message['message_thread_id'] ?? 0)
            : null;

        if ($chatId === '') {
            return;
        }

        $newMembers = $message['new_chat_members'] ?? $message['new_chat_participant'] ?? [];

        if (!is_array($newMembers)) {
            $newMembers = [$newMembers];
        }

        foreach ($newMembers as $member) {
            if (!is_array($member)) {
                continue;
            }

            $userId = (string) ($member['id'] ?? '');
            $firstName = (string) ($member['first_name'] ?? '');
            $username = (string) ($member['username'] ?? '');
            $lastName = (string) ($member['last_name'] ?? '');
            $isPremium = (bool) ($member['is_premium'] ?? false);

            if ($userId === '') {
                continue;
            }

            $existing = WelcomeMessage::where('chat_id', $chatId)
                ->where('user_id', $userId)
                ->first();

            if ($existing !== null) {
                continue;
            }

            try {
                $this->telegram->restrictMember($chatId, $userId);

                $displayName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
                $welcomeMessage = trim((string) config('plugins.PeptideVendorsBot.welcome_message', ''));
                $welcomeText = str_replace('{first_name}', $displayName, $welcomeMessage);
                $welcomeText .= "\n\n⚠️ You are currently restricted from posting. Please click the button below to agree to the rules and gain access.";

                $this->telegram->sendMessage($chatId, $welcomeText, null, [
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[
                            [
                                'text' => '✅ I Agree to the Rules',
                                'callback_data' => 'confirm',
                            ],
                        ]],
                    ]),
                ]);

                WelcomeMessage::create([
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'is_premium' => $isPremium,
                ]);

                Log::info('peptidebot.welcome.restricted', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'first_name' => $firstName,
                ]);
            } catch (Throwable $e) {
                WelcomeMessage::create([
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'is_premium' => $isPremium,
                    'error' => $e->getMessage(),
                ]);

                Log::error('peptidebot.welcome.failed', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function enforceLinkSpam(array $message): void
    {
        if (!config('plugins.PeptideVendorsBot.link_spam_enabled', true)) {
            return;
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $userId = (string) ($message['from']['id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);
        $firstName = (string) ($message['from']['first_name'] ?? 'there');

        if ($chatId === '' || $userId === '' || $messageId <= 0 || $text === '') {
            return;
        }

        if (!preg_match('/https?:\/\/t\.me\/[^\s]+/', $text) && !preg_match('/tg:\/\/[^\s]+/', $text)) {
            return;
        }

        $isAllowed = TopicPermission::where('allowed_user_id', $userId)->exists();
        $isAdmin = false;

        if (!$isAllowed) {
            try {
                $token = $this->telegram->botToken();
                $url = "https://api.telegram.org/bot{$token}/getChatAdministrators";
                $response = Http::timeout(10)->asForm()->post($url, ['chat_id' => $chatId]);
                $decoded = $response->json();
                if (is_array($decoded) && ($decoded['ok'] ?? false)) {
                    foreach ($decoded['result'] ?? [] as $admin) {
                        $adminUser = $admin['user'] ?? [];
                        if ((string) ($adminUser['id'] ?? '') === $userId) {
                            $isAdmin = true;
                            break;
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        if ($isAllowed || $isAdmin) {
            return;
        }

        try {
            $this->telegram->deleteMessage($chatId, $messageId);

            $reply = "<a href=\"tg://user?id={$userId}\">{$firstName}</a>, your message with a link was removed. Only approved members can post links.";
            $this->telegram->sendMessage($chatId, $reply, null, ['parse_mode' => 'HTML']);

            Log::info('peptidebot.spam.link_deleted', [
                'user_id' => $userId,
                'message_id' => $messageId,
            ]);
        } catch (Throwable $e) {
            Log::warning('peptidebot.spam.delete_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function enforceTopicPermissions(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $threadId = isset($message['is_topic_message']) ? (int) ($message['message_thread_id'] ?? 0) : 0;
        $userId = (string) ($message['from']['id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);
        $firstName = (string) ($message['from']['first_name'] ?? 'there');
        $senderTag = trim((string) ($message['sender_tag'] ?? ''));

        if ($threadId <= 0 || $chatId === '' || $userId === '' || $messageId <= 0) {
            return;
        }

        if ($userId === '5870086543') {
            return;
        }

        if ($threadId === 1214) {
            try {
                $this->telegram->deleteMessage($chatId, $messageId);
            } catch (Throwable) {}
            return;
        }

        $allowed = TopicPermission::where('topic_id', $threadId)->pluck('allowed_user_id');

        if ($allowed->isEmpty()) {
            return;
        }

        if ($allowed->contains($userId)) {
            return;
        }

        if ($senderTag !== '') {
            try {
                $username = (string) ($message['from']['username'] ?? '');
                $displayName = $username !== '' ? "@{$username}" : $firstName;

                // Only auto-approve for the Sellers topic (id: 16)
                $sellerTopicId = 16;
                $exists = TopicPermission::where('topic_id', $sellerTopicId)
                    ->where('allowed_user_id', $userId)
                    ->exists();

                if (!$exists) {
                    TopicPermission::create([
                        'topic_id' => $sellerTopicId,
                        'allowed_user_id' => $userId,
                        'allowed_user_name' => $displayName,
                        'topic_name' => TopicPermission::where('topic_id', $sellerTopicId)->value('topic_name') ?? "Topic {$sellerTopicId}",
                        'added_by' => 'auto',
                    ]);

                    $this->telegram->sendMessage($chatId,
                        "✅ <b>New Vendor Auto-Approved!</b>\n\n"
                        . "👤 <a href=\"tg://user?id={$userId}\">{$displayName}</a>\n"
                        . "🏷️ {$senderTag}\n\n"
                        . "They can now advertise in the sellers topic.", null, ['parse_mode' => 'HTML']);
                }

                // Allow message if posting in the Sellers topic, otherwise enforce restriction
                if ($threadId === $sellerTopicId) {
                    return;
                }
            } catch (Throwable $e) {
                Log::warning('peptidebot.permission.auto_approve_failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->telegram->deleteMessage($chatId, $messageId);

            $topicName = TopicPermission::where('topic_id', $threadId)->value('topic_name') ?? 'this topic';
            $reply = "<a href=\"tg://user?id={$userId}\">{$firstName}</a>, your message in {$topicName} was removed because only approved vendors can post there. Please contact the admins to be vetted for access.";

            $this->telegram->sendMessage($chatId, $reply, null, ['parse_mode' => 'HTML']);

            Log::info('peptidebot.permission.deleted', [
                'topic_id' => $threadId,
                'user_id' => $userId,
                'message_id' => $messageId,
            ]);
        } catch (Throwable $e) {
            Log::warning('peptidebot.permission.delete_failed', [
                'topic_id' => $threadId,
                'user_id' => $userId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isSyncSellersCommand(array $message): bool
    {
        $text = trim((string) ($message['text'] ?? ''));
        return preg_match('/^\/syncsellers(@\w+)?$/i', $text) === 1;
    }

    

    private function syncSellers(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $threadId = isset($message['is_topic_message']) ? (int) ($message['message_thread_id'] ?? 0) : null;

        try {
            $token = $this->telegram->botToken();
            $url = "https://api.telegram.org/bot{$token}/getChatAdministrators";

            $response = Http::timeout(15)
                ->asForm()
                ->post($url, ['chat_id' => $chatId]);

            $decoded = $response->json();

            if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
                $this->telegram->sendMessage($chatId, 'Failed to fetch admins.', $threadId);
                return;
            }

            $sellerTopics = [16];
            $added = [];
            $admins = $decoded['result'] ?? [];

            foreach ($admins as $admin) {
                $title = (string) ($admin['custom_title'] ?? '');
                $user = $admin['user'] ?? [];
                $userId = (string) ($user['id'] ?? '');
                $userName = (string) ($user['first_name'] ?? '');

                if ($userId === '') {
                    continue;
                }

                if (stripos($title, 'seller') === false) {
                    continue;
                }

                foreach ($sellerTopics as $topicId) {
                    $exists = TopicPermission::where('topic_id', $topicId)
                        ->where('allowed_user_id', $userId)
                        ->exists();

                    if (!$exists) {
                        TopicPermission::create([
                            'topic_id' => $topicId,
                            'allowed_user_id' => $userId,
                            'allowed_user_name' => $userName,
                            'topic_name' => "Topic {$topicId}",
                        ]);
                        $added[] = "{$userName} ({$title}) -> Topic {$topicId}";
                    }
                }
            }

            if (empty($added)) {
                $this->telegram->sendMessage($chatId, 'No new sellers found. All sellers already have permissions.', $threadId);
            } else {
                $msg = "✅ Synced sellers:\n" . implode("\n", $added);
                $this->telegram->sendMessage($chatId, $msg, $threadId);
            }

            Log::info('peptidebot.sellers.synced', [
                'added' => $added,
            ]);
        } catch (Throwable $e) {
            Log::error('peptidebot.sellers.sync_failed', [
                'error' => $e->getMessage(),
            ]);
            try {
                $this->telegram->sendMessage($chatId, "Sync failed: {$e->getMessage()}", $threadId);
            } catch (Throwable) {
            }
        }
    }

    private function handleChatMemberUpdate(array $chatMember): void
    {
        $newStatus = $chatMember['new_chat_member'] ?? null;
        $oldStatus = $chatMember['old_chat_member'] ?? null;

        if (!is_array($newStatus) || !is_array($oldStatus)) {
            return;
        }

        $newStatusStr = (string) ($newStatus['status'] ?? '');
        $oldStatusStr = (string) ($oldStatus['status'] ?? '');

        $user = $newStatus['user'] ?? $oldStatus['user'] ?? [];
        $userId = (string) ($user['id'] ?? '');
        $userName = (string) ($user['first_name'] ?? '');
        $chatId = (string) ($chatMember['chat']['id'] ?? '');

        if ($userId === '' || $chatId === '') {
            return;
        }

        if ($oldStatusStr !== 'kicked' && $newStatusStr === 'kicked') {
            try {
                $this->telegram->banChatMember($chatId, $userId);

                \App\Plugins\PeptideVendorsBot\Models\WelcomeMessage::where('chat_id', $chatId)
                    ->where('user_id', $userId)
                    ->update(['banned' => true]);

                Log::info('peptidebot.ban.auto_banned', [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'reason' => 'kicked_by_admin',
                ]);
            } catch (Throwable $e) {
                Log::warning('peptidebot.ban.failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        $title = (string) ($newStatus['custom_title'] ?? '');
        if (stripos($title, 'seller') === false) {
            return;
        }

        $user = $newStatus['user'] ?? [];
        $userId = (string) ($user['id'] ?? '');
        $userName = (string) ($user['first_name'] ?? '');
        $chatId = (string) ($chatMember['chat']['id'] ?? '');

        if ($userId === '') {
            return;
        }

        $sellerTopics = [16];
        $added = [];

        foreach ($sellerTopics as $topicId) {
            $exists = TopicPermission::where('topic_id', $topicId)
                ->where('allowed_user_id', $userId)
                ->exists();

            if (!$exists) {
                TopicPermission::create([
                    'topic_id' => $topicId,
                    'allowed_user_id' => $userId,
                    'allowed_user_name' => $userName,
                    'topic_name' => "Topic {$topicId}",
                ]);
                $added[] = $topicId;
            }
        }

        if (!empty($added)) {
            Log::info('peptidebot.seller.auto_added', [
                'user_id' => $userId,
                'user_name' => $userName,
                'title' => $title,
                'topics' => $added,
            ]);

            $this->telegram->sendMessage($chatId,
                "✅ <b>New Vendor Approved!</b>\n\n"
                . "👤 <a href=\"tg://user?id={$userId}\">{$userName}</a>\n"
                . "🏷️ {$title}\n\n"
                . "They can now advertise in the sellers topic.", null, ['parse_mode' => 'HTML']);
        }
    }

    private function updateVendorFeedback(): void
    {
        try {
            $stats = $this->reviewService->vendorStats();

            if (empty($stats)) {
                return;
            }

            usort($stats, fn($a, $b) => $b['total_reviews'] <=> $a['total_reviews']);

            $lines = [];
            foreach ($stats as $vendor) {
                $avg = $vendor['average_rating'];
                $displayRating = $avg == (int) $avg ? (int) $avg : $avg;
                $stars = str_repeat('⭐', (int) round($avg));
                $name = $vendor['vendor'];

                $tagMsgs = WebhookUpdate::where('payload', 'like', "%sender_tag%")
                    ->where('payload', 'like', "%{$name}%")
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn($u) => is_string($u->payload) ? json_decode($u->payload, true) : $u->payload);

                foreach ($tagMsgs as $m) {
                    $fn = $m['from']['first_name'] ?? '';
                    $uname = $m['from']['username'] ?? '';
                    if ($uname !== '' && (strtolower($fn) === strtolower($name) || strtolower($uname) === strtolower(ltrim($name, '@')))) {
                        $name = "@{$uname}";
                        break;
                    }
                }

                if (!str_starts_with($name, '@')) {
                    $name = "@{$name}";
                }

                $lines[] = "{$name}\n{$stars} {$displayRating}/5 — {$vendor['total_reviews']} reviews";
            }

            $text = "<b>Vendor Feedback Summary</b>\n\n" . implode("\n", $lines);

            $this->telegram->editMessageText(self::FEEDBACK_CHAT_ID, self::FEEDBACK_MESSAGE_ID, $text, ['parse_mode' => 'HTML']);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'message is not modified')) {
                return;
            }
            Log::warning('peptidebot.feedback.update_failed', ['error' => $msg]);
        }
    }

    private function handleVendorApprovalTextCommand(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId !== self::VENDOR_APPROVAL_CHAT_ID || $text === '') {
            return;
        }

        preg_match('/^\/(approve|deny)(\d+)$/', $text, $matches);
        if (!$matches) {
            return;
        }

        $this->processVendorAction($matches[1], (int) $matches[2], $chatId);
    }

    private function processVendorAction(string $action, int $requestId, string $chatId, mixed $messageId = null): void
    {
        $vendorRequest = VendorAccessRequest::with('user')->find($requestId);
        if (!$vendorRequest) {
            $this->sendVendorMessage($chatId, "Request #{$requestId} not found.");
            return;
        }

        if ($vendorRequest->status !== 'pending') {
            $this->sendVendorMessage($chatId, "Request #{$requestId} was already {$vendorRequest->status}.");
            return;
        }

        $user = $vendorRequest->user;
        $status = $action === 'approve' ? 'approved' : 'denied';

        $vendorRequest->update(['status' => $status]);

        if ($action === 'approve') {
            $user->update(['is_approved_vendor' => true]);
            $user->assignRole('vendor');

            $this->notifications->create(
                $user,
                'vendor_request',
                'Vendor Access Approved',
                'Your vendor access request has been approved. You can now manage your vendor listing.',
                ['request_id' => $vendorRequest->id],
                '✅',
                '/vendors/my'
            );
        } else {
            $this->notifications->create(
                $user,
                'vendor_request',
                'Vendor Access Denied',
                'Your vendor access request has been denied.',
                ['request_id' => $vendorRequest->id],
                '❌',
                null
            );
        }

        $this->sendVendorMessage($chatId, "✅ Request #{$requestId} from {$user->name} has been {$status}.");

        // Clear inline keyboard on the original message
        if ($messageId) {
            try {
                $token = $this->telegram->botToken();
                Http::post("https://api.telegram.org/bot{$token}/editMessageReplyMarkup", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'reply_markup' => json_encode(['inline_keyboard' => []]),
                ]);
            } catch (Throwable) {}
        }
    }

    private function sendVendorMessage(string $chatId, string $text): void
    {
        try {
            $token = $this->telegram->botToken();
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (Throwable) {}
    }

    private function logUpdate(mixed $updateId, string $type, array $data): void
    {
        try {
            $chatId = (string) ($data['chat']['id'] ?? $data['message']['chat']['id'] ?? '');

            WebhookUpdate::create([
                'update_id' => $updateId !== null ? (string) $updateId : null,
                'type' => $type,
                'chat_id' => $chatId,
                'payload' => $data,
            ]);
        } catch (Throwable $e) {
            Log::warning('peptidebot.webhook.log_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
