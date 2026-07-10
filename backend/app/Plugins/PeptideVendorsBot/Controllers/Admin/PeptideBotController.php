<?php

namespace App\Plugins\PeptideVendorsBot\Controllers\Admin;

use App\Plugins\PeptideVendorsBot\Models\DailyQuestion;
use App\Plugins\PeptideVendorsBot\Models\TopicPermission;
use App\Plugins\PeptideVendorsBot\Models\Verification;
use App\Plugins\PeptideVendorsBot\Models\WelcomeMessage;
use App\Plugins\PeptideVendorsBot\Models\WebhookUpdate;
use App\Plugins\PeptideVendorsBot\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PeptideBotController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegram,
    ) {
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'bot_token_configured' => trim((string) config('plugins.PeptideVendorsBot.bot_token', '')) !== '',
            'webhook_enabled' => (bool) config('plugins.PeptideVendorsBot.webhook_enabled', false),
            'welcome_enabled' => (bool) config('plugins.PeptideVendorsBot.welcome_enabled', true),
            'target_chat_id' => config('plugins.PeptideVendorsBot.target_chat_id', ''),
            'join_verification' => (bool) config('plugins.PeptideVendorsBot.join_verification_enabled', true),
            'stats' => [
                'total_updates' => WebhookUpdate::count(),
                'total_welcomes' => WelcomeMessage::count(),
                'restricted_topics' => TopicPermission::distinct('topic_id')->count('topic_id'),
                'allowed_users' => TopicPermission::count(),
                'verified_members' => Verification::where('agreed', true)->count(),
            ],
        ]);
    }

    public function questions(): JsonResponse
    {
        $questions = DailyQuestion::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'enabled' => $q->enabled,
                'last_posted_at' => $q->last_posted_at?->toISOString(),
                'created_at' => $q->created_at?->toISOString(),
            ]);

        return response()->json(['ok' => true, 'questions' => $questions]);
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
        ]);

        $question = DailyQuestion::create([
            'question' => $data['question'],
            'enabled' => true,
        ]);

        return response()->json(['ok' => true, 'question' => $question], 201);
    }

    public function updateQuestion(Request $request, int $id): JsonResponse
    {
        $question = DailyQuestion::findOrFail($id);

        $data = $request->validate([
            'question' => 'sometimes|string|max:500',
            'enabled' => 'sometimes|boolean',
        ]);

        $question->update($data);

        return response()->json(['ok' => true]);
    }

    public function deleteQuestion(int $id): JsonResponse
    {
        DailyQuestion::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function verifications(): JsonResponse
    {
        $verified = Verification::where('agreed', true)
            ->latest('agreed_at')
            ->take(50)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'user_id' => $v->user_id,
                'user_name' => $v->user_name,
                'old_username' => $v->old_username,
                'agreed_at' => $v->agreed_at,
            ]);

        return response()->json(['ok' => true, 'verified' => $verified]);
    }

    public function topics(): JsonResponse
    {
        $chatId = config('plugins.PeptideVendorsBot.target_chat_id', '');

        $updates = WebhookUpdate::where('chat_id', $chatId)
            ->where('type', 'message')
            ->latest()
            ->take(500)
            ->get()
            ->pluck('payload');

        $named = [];
        $allIds = [];

        foreach ($updates as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $threadId = (int) ($payload['message_thread_id'] ?? 0);
            if ($threadId > 0) {
                $allIds[$threadId] = true;
            }

            $topicData = $payload['forum_topic_created'] ?? $payload['forum_topic_edited'] ?? null;
            if (!is_array($topicData)) {
                continue;
            }

            $name = (string) ($topicData['name'] ?? '');
            if ($name !== '' && !isset($named[$threadId])) {
                $named[$threadId] = $name;
            }
        }

        $knownNames = [
            16 => 'Sellers / Single Vial',
            20 => 'Linyi Xinhao Peptides - China Vendor',
            31 => 'TRT Peptides - China Vendor',
            120 => 'Reviews & Feedback',
            126 => 'Xu - Trusted China Source',
        ];

        foreach ($knownNames as $tid => $name) {
            $allIds[$tid] = true;
            if (!isset($named[$tid])) {
                $named[$tid] = $name;
            }
        }

        $seen = [];
        foreach ($allIds as $tid => $_) {
            $seen[] = [
                'topic_id' => $tid,
                'name' => $named[$tid] ?? "Topic {$tid}",
            ];
        }

        usort($seen, fn ($a, $b) => $a['topic_id'] - $b['topic_id']);

        return response()->json([
            'ok' => true,
            'topics' => array_values($seen),
        ]);
    }

    public function welcomeMessages(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->input('per_page', 20)), 100);

        $messages = WelcomeMessage::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($messages);
    }

    public function webhookUpdates(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->input('per_page', 20)), 100);

        $updates = WebhookUpdate::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($updates);
    }

    public function permissions(): JsonResponse
    {
        $permissions = TopicPermission::query()
            ->orderBy('topic_id')
            ->orderBy('id')
            ->get()
            ->groupBy('topic_id');

        $result = [];
        foreach ($permissions as $topicId => $topicPerms) {
            $result[] = [
                'topic_id' => (int) $topicId,
                'topic_name' => $topicPerms->first()->topic_name ?? "Topic {$topicId}",
                'allowed_users' => $topicPerms->map(fn ($p) => [
                    'id' => $p->id,
                    'user_id' => $p->allowed_user_id,
                    'user_name' => $p->allowed_user_name,
                    'added_at' => $p->created_at,
                ]),
            ];
        }

        return response()->json([
            'ok' => true,
            'topics' => $result,
        ]);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topic_id' => 'required|integer|min:1',
            'user_id' => 'required|string',
            'user_name' => 'nullable|string|max:255',
            'topic_name' => 'nullable|string|max:255',
        ]);

        $exists = TopicPermission::where('topic_id', $data['topic_id'])
            ->where('allowed_user_id', $data['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['ok' => false, 'error' => 'User already allowed in this topic.'], 409);
        }

        $permission = TopicPermission::create([
            'topic_id' => $data['topic_id'],
            'allowed_user_id' => $data['user_id'],
            'allowed_user_name' => $data['user_name'] ?? null,
            'topic_name' => $data['topic_name'] ?? null,
            'added_by' => $request->user()?->id,
        ]);

        return response()->json(['ok' => true, 'permission' => $permission], 201);
    }

    public function deletePermission(int $id): JsonResponse
    {
        $permission = TopicPermission::findOrFail($id);
        $permission->delete();

        return response()->json(['ok' => true]);
    }

    public function deleteTopicPermissions(Request $request): JsonResponse
    {
        $topicId = (int) $request->input('topic_id', 0);

        if ($topicId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Invalid topic_id.'], 400);
        }

        TopicPermission::where('topic_id', $topicId)->delete();

        return response()->json(['ok' => true]);
    }

    public function recentDeleted(): JsonResponse
    {
        $deleted = WebhookUpdate::where('type', 'deleted_message')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'chat_id' => $u->chat_id,
                'created_at' => $u->created_at,
                'payload' => $u->payload,
            ]);

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    public function iptvLines(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        $sortBy = $request->input('sort_by', 'linked');
        $canDm = $request->input('can_dm', '');
        $perPage = min((int) ($request->input('per_page', 50)), 200);

        $query = \App\Plugins\PeptideVendorsBot\Models\IptvLine::query();

        if ($canDm !== '') {
            $query->where('can_dm', (int) $canDm);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('old_username', 'like', "%{$search}%")
                  ->orWhere('telegram_username', 'like', "%{$search}%")
                  ->orWhere('telegram_first_name', 'like', "%{$search}%")
                  ->orWhere('telegram_last_name', 'like', "%{$search}%")
                  ->orWhere('owner', 'like', "%{$search}%");
            });
        }

        match ($sortBy) {
            'expire_asc' => $query->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END, STR_TO_DATE(SUBSTRING_INDEX(expire_date, CHAR(10), 1), \'%d-%m-%Y\') ASC'),
            'expire_desc' => $query->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END, STR_TO_DATE(SUBSTRING_INDEX(expire_date, CHAR(10), 1), \'%d-%m-%Y\') DESC'),
            'recent' => $query->orderByDesc('id'),
            default => $query->orderByRaw('CASE WHEN telegram_user_id IS NOT NULL THEN 0 ELSE 1 END')->orderByDesc('linked_at'),
        };

        $lines = $query->paginate($perPage);

        return response()->json($lines);
    }





    public function storeIptvLine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'old_username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'expire_date' => 'nullable|string|max:255',
            'con' => 'nullable|string|max:255',
            'speed' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $line = \App\Plugins\PeptideVendorsBot\Models\IptvLine::create([
            'old_username' => $data['old_username'],
            'password' => $data['password'],
            'expire_date' => $data['expire_date'] ?? null,
            'con' => $data['con'] ?? null,
            'speed' => $data['speed'] ?? null,
            'notes' => $data['notes'] ?? null,
            'owner' => 'linkerW',
        ]);

        return response()->json(['ok' => true, 'line' => $line], 201);
    }

    public function updateIptvLine(Request $request, int $id): JsonResponse
    {
        $line = \App\Plugins\PeptideVendorsBot\Models\IptvLine::findOrFail($id);

        $data = $request->validate([
            'new_username' => 'nullable|string|max:255',
            'new_password' => 'nullable|string|max:255',
        ]);

        $notify = false;

        if (isset($data['new_username']) && $data['new_username'] !== $line->new_username) {
            $line->new_username = $data['new_username'];
            $notify = true;
        }
        if (isset($data['new_password']) && $data['new_password'] !== $line->new_password) {
            $line->new_password = $data['new_password'];
            $notify = true;
        }

        $line->save();

        // Send notification if linked and details changed
        if ($notify && $line->telegram_user_id) {
            $this->notifyLineUpdate($line);
        }

        return response()->json(['ok' => true, 'line' => $line]);
    }

    private function notifyLineUpdate(\App\Plugins\PeptideVendorsBot\Models\IptvLine $line): void
    {
        $token = '8728835633:AAF7K5TIQWRRMVlTbB1R7KUpAv5I04dVAPw';
        $msg = "⚠️ <b>Your IPTV line has been updated!</b>\n\n";
        $msg .= "<b>Account Updated:</b> {$line->old_username}.\n\n";
        $msg .= "Please <b>log out</b> of your app and <b>select Limitless TV</b> again.\n\n";
        if ($line->new_username) {
            $msg .= "New Username: <code>{$line->new_username}</code>\n";
        }
        if ($line->new_password) {
            $msg .= "New Password: <code>{$line->new_password}</code>\n";
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->asForm()->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => (string) $line->telegram_user_id,
                    'text' => $msg,
                    'parse_mode' => 'HTML',
                ]
            );

            if ($response->successful()) {
                $line->update([
                    'last_notified_at' => now(),
                    'last_notified_message' => $msg,
                    'can_dm' => true,
                ]);
            } else {
                $line->update(['can_dm' => false]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('limitless.notify_failed', ['error' => $e->getMessage()]);
            $line->update(['can_dm' => false]);
        }
    }

}