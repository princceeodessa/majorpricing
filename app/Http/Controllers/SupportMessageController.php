<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportMessageController extends Controller
{
    public function storeForClient(Request $request): RedirectResponse
    {
        $client = $request->user()->loadMissing('manager');

        abort_if($client->canManageClients(), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        abort_unless($client->manager?->canManageClients(), 403);

        SupportMessage::query()->create([
            'client_id' => $client->id,
            'manager_id' => $client->manager_id,
            'sender_id' => $client->id,
            'message' => trim($validated['message']),
        ]);

        return redirect()
            ->route('account.show')
            ->with('status', 'Сообщение менеджеру отправлено.');
    }

    public function storeForManager(Request $request): RedirectResponse
    {
        $manager = $request->user();

        abort_unless($manager->canManageClients(), 403);

        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:2000'],
            'redirect_to' => ['nullable', 'string', 'in:account,chats'],
        ]);

        $client = $manager->visibleClients()
            ->whereKey($validated['client_id'])
            ->firstOrFail();

        SupportMessage::query()->create([
            'client_id' => $client->id,
            'manager_id' => $client->manager_id ?: $manager->id,
            'sender_id' => $manager->id,
            'message' => trim($validated['message']),
        ]);

        if (($validated['redirect_to'] ?? null) === 'chats') {
            return redirect()
                ->route('manager.chats.index', ['client' => $client])
                ->with('status', 'Ответ клиенту отправлен.');
        }

        return redirect()
            ->route('account.show')
            ->with('status', 'Ответ клиенту отправлен.');
    }
}
