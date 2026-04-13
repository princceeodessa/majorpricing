<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SupportMessage;
use App\Support\OrderStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationsController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();

        $sinceMessage = $request->query('since_message');
        $sinceOrder = $request->query('since_order');

        $messageBase = SupportMessage::query()
            ->where('sender_id', '!=', $user->id)
            ->when($user->canManageClients(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            }, function ($query) use ($user) {
                $query->where('client_id', $user->id);
            });

        $latestMessage = (clone $messageBase)
            ->latest('created_at')
            ->first();

        $newMessageQuery = clone $messageBase;
        if ($sinceMessage) {
            $newMessageQuery->where('created_at', '>', $sinceMessage);
        }

        $newMessage = $sinceMessage
            ? $newMessageQuery->latest('created_at')->first()
            : null;
        $newMessagesCount = $sinceMessage ? (clone $newMessageQuery)->count() : 0;

        $orderBase = Order::query()->where('user_id', $user->id);

        $latestOrder = (clone $orderBase)
            ->latest('updated_at')
            ->first();

        $newOrderQuery = clone $orderBase;
        if ($sinceOrder) {
            $newOrderQuery->where('updated_at', '>', $sinceOrder);
        }

        $newOrder = $sinceOrder
            ? $newOrderQuery->latest('updated_at')->first()
            : null;

        return response()->json([
            'latest_message_at' => $latestMessage?->created_at?->toIso8601String(),
            'new_messages_count' => $newMessagesCount,
            'message' => $newMessage ? [
                'id' => $newMessage->id,
                'created_at' => $newMessage->created_at?->toIso8601String(),
                'preview' => Str::limit($newMessage->message, 140),
            ] : null,
            'latest_order_at' => $latestOrder?->updated_at?->toIso8601String(),
            'order' => $newOrder ? [
                'id' => $newOrder->id,
                'number' => $newOrder->number,
                'status' => $newOrder->status,
                'status_label' => OrderStatuses::label($newOrder->status),
                'updated_at' => $newOrder->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
