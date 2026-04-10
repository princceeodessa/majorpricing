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

        return view('manager.chats.index', [
            'clients' => $clients,
            'activeClient' => $activeClient,
            'messages' => $activeClient?->supportMessages ?? collect(),
        ]);
    }
}
