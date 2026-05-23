<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RegistrationRequest;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\OrderStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationsController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->canManageClients()) {
            return $this->pollForManager($request, $user);
        }

        return $this->pollForClient($request, $user);
    }

    private function pollForClient(Request $request, User $user): JsonResponse
    {
        $sinceMessage = $request->query('since_message');
        $sinceOrder = $request->query('since_order');

        $messageBase = SupportMessage::query()
            ->where('sender_id', '!=', $user->id)
            ->where('client_id', $user->id);

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
            'is_manager' => false,
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

    private function pollForManager(Request $request, User $user): JsonResponse
    {
        $sinceClientMessage = $request->query('since_client_message');
        $sinceRequest       = $request->query('since_request');

        // ── Сообщения от клиентов ────────────────────────────────────
        $messageBase = SupportMessage::query()
            ->where('sender_id', '!=', $user->id);

        if (! $user->isAdmin()) {
            // Менеджер видит только своих клиентов
            $messageBase->where('manager_id', $user->id);
        }

        $latestClientMessage = (clone $messageBase)->latest('created_at')->first();

        $newClientMessageQuery = clone $messageBase;
        if ($sinceClientMessage) {
            $newClientMessageQuery->where('created_at', '>', $sinceClientMessage);
        }
        $newClientMessage      = $sinceClientMessage ? $newClientMessageQuery->latest('created_at')->first() : null;
        $newClientMessagesCount = $sinceClientMessage ? (clone $newClientMessageQuery)->count() : 0;

        // Количество непрочитанных для бейджа в шапке
        $unreadClientMessages = (clone $messageBase)->whereNull('read_at')->count();

        // ── Заявки на регистрацию ────────────────────────────────────
        $latestRequest = RegistrationRequest::query()
            ->pending()
            ->latest('created_at')
            ->first();

        $pendingRequestsCount = RegistrationRequest::query()->pending()->count();

        $newRequestQuery = RegistrationRequest::query()->pending();
        if ($sinceRequest) {
            $newRequestQuery->where('created_at', '>', $sinceRequest);
        }
        $newRequest      = $sinceRequest ? $newRequestQuery->latest('created_at')->first() : null;
        $newRequestsCount = $sinceRequest ? (clone $newRequestQuery)->count() : 0;

        // ── Новые заказы (статус NEW) ────────────────────────────────
        $newOrdersBase = Order::query()->where('status', OrderStatuses::NEW);
        if (! $user->isAdmin()) {
            $clientIds = $user->managedClients()->pluck('id')->toArray();
            $newOrdersBase->whereIn('user_id', $clientIds ?: [0]);
        }
        $newOrdersCount = $newOrdersBase->count();

        return response()->json([
            'is_manager' => true,

            // Сообщения от клиентов
            'unread_client_messages_count'  => $unreadClientMessages,
            'latest_client_message_at'      => $latestClientMessage?->created_at?->toIso8601String(),
            'new_client_messages_count'     => $newClientMessagesCount,
            'client_message'                => $newClientMessage ? [
                'id'         => $newClientMessage->id,
                'client_id'  => $newClientMessage->client_id,
                'created_at' => $newClientMessage->created_at?->toIso8601String(),
                'preview'    => Str::limit($newClientMessage->message, 140),
            ] : null,

            // Заявки на регистрацию
            'pending_requests_count'  => $pendingRequestsCount,
            'latest_request_at'       => $latestRequest?->created_at?->toIso8601String(),
            'new_requests_count'      => $newRequestsCount,
            'pending_request'         => $newRequest ? [
                'id'         => $newRequest->id,
                'name'       => $newRequest->name,
                'company'    => $newRequest->company,
                'created_at' => $newRequest->created_at?->toIso8601String(),
            ] : null,

            // Новые заказы
            'new_orders_count' => $newOrdersCount,
        ]);
    }
}
