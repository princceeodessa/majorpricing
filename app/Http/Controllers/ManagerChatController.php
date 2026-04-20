<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ManagerChatController extends Controller
{
    public function index(Request $request, ?User $client = null): View
    {
        $manager = $request->user();

        abort_unless($manager->canManageClients(), 403);

        $clients = $manager->visibleClients()
            ->with(['addresses', 'supportMessages.sender'])
            ->withCount('supportMessages')
            ->get()
            ->sortByDesc(fn (User $client): int => $client->supportMessages->last()?->created_at?->timestamp ?? 0)
            ->values();

        $activeClient = $client
            ? $clients->firstWhere('id', $client->id)
            : $clients->first();

        if ($client && ! $activeClient) {
            abort(404);
        }

        $messages = collect();

        if ($activeClient) {
            $activeClient->supportMessages()
                ->where('sender_id', '!=', $manager->id)
                ->whereNull('read_at')
                ->update([
                    'read_at' => now(),
                ]);

            $activeClient->unsetRelation('supportMessages');
            $activeClient->load(['supportMessages.sender']);

            $messages = $activeClient->supportMessages;
        }

        return view('manager.chats.index', [
            'clients' => $clients,
            'activeClient' => $activeClient,
            'messages' => $messages,
        ]);
    }
}
