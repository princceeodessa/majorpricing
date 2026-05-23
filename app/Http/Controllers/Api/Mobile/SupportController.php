<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\SupportMessageResource;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $client = $this->resolveClient($user, $data['client_id'] ?? null, allowFallback: true);

        if (! $client) {
            return response()->json([
                'client' => null,
                'data' => [],
            ]);
        }

        SupportMessage::query()
            ->where('client_id', $client->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = SupportMessage::query()
            ->with('sender')
            ->where('client_id', $client->id)
            ->when(isset($data['after_id']), fn ($query) => $query->where('id', '>', (int) $data['after_id']))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'manager_id' => $client->manager_id,
            ],
            'data' => SupportMessageResource::collection($messages)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $client = $this->resolveClient($user, $data['client_id'] ?? null, allowFallback: false);
        abort_unless($client, 404);

        if ($user->canManageClients()) {
            $managerId = $client->manager_id ?: $user->id;
            $senderId = $user->id;
        } else {
            abort_unless($client->manager?->canManageClients(), 403);

            $managerId = $client->manager_id;
            $senderId = $client->id;
        }

        $message = SupportMessage::query()->create([
            'client_id' => $client->id,
            'manager_id' => $managerId,
            'sender_id' => $senderId,
            'message' => trim($data['message']),
        ]);

        $message->load('sender');

        return response()->json([
            'data' => (new SupportMessageResource($message))->resolve(),
        ], 201);
    }

    private function resolveClient(User $user, mixed $clientId, bool $allowFallback): ?User
    {
        if (! $user->canManageClients()) {
            abort_if($clientId !== null && (int) $clientId !== (int) $user->id, 403);

            return $user->loadMissing('manager');
        }

        $query = $user->visibleClients()->with('manager');

        if ($clientId !== null) {
            return $query->whereKey((int) $clientId)->firstOrFail();
        }

        return $allowFallback ? $query->first() : null;
    }
}
