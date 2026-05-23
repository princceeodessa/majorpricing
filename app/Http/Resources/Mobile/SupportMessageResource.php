<?php

namespace App\Http\Resources\Mobile;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportMessage
 */
class SupportMessageResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'manager_id' => $this->manager_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender?->name,
            'message' => $this->message,
            'is_own' => (int) $this->sender_id === (int) $request->user()?->id,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
