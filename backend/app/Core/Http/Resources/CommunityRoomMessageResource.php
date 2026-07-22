<?php

namespace App\Core\Http\Resources;

use App\Core\Models\CommunityRoomMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityRoomMessageResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        /** @var CommunityRoomMessage $this */
        $sender = $this->sender;

        return [
            'id' => $this->id,
            'room' => $this->room,
            'body' => $this->body,
            'text' => $this->body,
            'time' => $this->sent_at?->diffForHumans() ?? 'just now',
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => $sender->display_name,
                'username' => $sender->username,
                'color' => $sender->color ?? 'purple',
                'initial' => $sender->initial,
                'role' => $sender->role_label,
            ] : null,
        ];
    }
}
