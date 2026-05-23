<?php

namespace App\Http\Resources\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ManagerClientResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $lastMessage = $this->relationLoaded('supportMessages')
            ? $this->supportMessages->last()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'city' => $this->city,
            'phone' => $this->phone,
            'telegram' => $this->telegram,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'support_messages_count' => (int) ($this->support_messages_count ?? 0),
            'unread_messages_count' => (int) ($this->unread_messages_count ?? 0),
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'message' => $lastMessage->message,
                'sender_id' => $lastMessage->sender_id,
                'created_at' => $lastMessage->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
