<?php

namespace App\Plugins\Community\Controllers;

use App\Core\Http\Controllers\Controller;
use App\Core\Http\Resources\CommunityRoomMessageResource;
use App\Core\Models\CommunityRoomMessage;
use App\Core\Services\WebSocketService;
use Illuminate\Http\Request;

class CommunityChatRoomController extends Controller
{
    public function __construct(
        private WebSocketService $websocket,
    ) {
    }

    public function index(Request $request, string $room)
    {
        // Authorize via WebSocketService helper to share logic
        abort_if(!$this->websocket->authorizeChannel($request->user(), "room.{$room}"), 403, 'You are not authorized to view this room.');

        $perPage = (int) $request->input('per_page', 50);

        $messages = CommunityRoomMessage::with('sender.roles')
            ->where('room', $room)
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

        return CommunityRoomMessageResource::collection($messages)
            ->additional([
                'next_cursor' => $messages->nextCursor()?->encode(),
                'prev_cursor' => $messages->previousCursor()?->encode(),
                'per_page' => $messages->perPage(),
            ]);
    }

    public function store(Request $request, string $room)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Authorize via WebSocketService helper to share logic
        abort_if(!$this->websocket->authorizeChannel($user, "room.{$room}"), 403, 'You are not authorized to post in this room.');

        $message = CommunityRoomMessage::create([
            'room' => $room,
            'sender_user_id' => $user->id,
            'body' => trim($validated['body']),
            'sent_at' => now(),
        ]);

        $message->load('sender.roles');

        $this->websocket->broadcast("room.{$room}", 'chat.message', [
            'message' => (new CommunityRoomMessageResource($message))->resolve(),
        ]);

        return (new CommunityRoomMessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}
