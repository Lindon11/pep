<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\VendorAccessRequest;
use App\Core\Services\NotificationService;
use App\Core\Services\WebSocketService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class TelegramBotController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private WebSocketService $websocket,
    ) {
    }

    public function webhook(Request $request)
    {
        $data = $request->all();

        // Handle callback query (inline button press)
        if (isset($data['callback_query'])) {
            return $this->handleCallback($data['callback_query']);
        }

        // Handle text commands
        $message = $data['message']['text'] ?? '';
        $chatId = $data['message']['chat']['id'] ?? null;

        if (!$chatId) return response('OK');

        preg_match('/^\/(approve|deny)(\d+)$/', $message, $matches);
        if (!$matches) {
            $this->sendMessage($chatId, 'Commands: /approve{id} or /deny{id}');
            return response('OK');
        }

        return $this->processAction($matches[1], (int) $matches[2], $chatId);
    }

    private function handleCallback(array $callback): mixed
    {
        $data = $callback['data'] ?? '';
        $chatId = $callback['message']['chat']['id'] ?? '';
        $messageId = $callback['message']['message_id'] ?? '';

        preg_match('/^(approve|deny)(\d+)$/', $data, $matches);
        if (!$matches) return response('OK');

        $result = $this->processAction($matches[1], (int) $matches[2], $chatId);

        // Update the original message to show it was processed
        $token = env('PEPTIDE_VENDORS_BOT_TOKEN');
        if ($token && $chatId && $messageId) {
            try {
                Http::post("https://api.telegram.org/bot{$token}/editMessageReplyMarkup", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'reply_markup' => json_encode(['inline_keyboard' => []]),
                ]);
            } catch (\Exception $e) {}
        }

        return $result;
    }

    private function processAction(string $action, int $requestId, string $chatId): mixed
    {
        $vendorRequest = VendorAccessRequest::with('user')->find($requestId);
        if (!$vendorRequest) {
            $this->sendMessage($chatId, "Request #{$requestId} not found.");
            return response('OK');
        }

        if ($vendorRequest->status !== 'pending') {
            $this->sendMessage($chatId, "Request #{$requestId} was already {$vendorRequest->status}.");
            return response('OK');
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

        $this->sendMessage($chatId, "✅ Request #{$requestId} from {$user->name} has been {$status}.");
        return response('OK');
    }

    private function sendMessage(string $chatId, string $text): void
    {
        $token = env('PEPTIDE_VENDORS_BOT_TOKEN');
        if (!$token) return;
        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {}
    }
}
