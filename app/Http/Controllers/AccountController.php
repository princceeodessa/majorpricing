<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderStatuses;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load([
            'addresses',
            'manager',
            'supportMessages.sender',
        ]);

        $managedUsers = collect();
        $supportMessages = $user->supportMessages;
        $managementStats = [
            'totalUsers' => 0,
            'activeUsers' => 0,
            'disabledUsers' => 0,
            'newOrders' => 0,
            'processingOrders' => 0,
        ];

        if ($user->isManager()) {
            $managedUsers = $user->managedClients()
                ->with(['addresses', 'supportMessages.sender'])
                ->withCount('orders')
                ->get();

            $managedUserIds = $managedUsers->modelKeys();

            $managementStats = [
                'totalUsers' => $managedUsers->count(),
                'activeUsers' => $managedUsers->where('is_active', true)->count(),
                'disabledUsers' => $managedUsers->where('is_active', false)->count(),
                'newOrders' => Order::query()
                    ->whereIn('user_id', $managedUserIds)
                    ->where('status', OrderStatuses::NEW)
                    ->count(),
                'processingOrders' => Order::query()
                    ->whereIn('user_id', $managedUserIds)
                    ->whereIn('status', OrderStatuses::inProgress())
                    ->count(),
            ];
        }

        return view('account.show', [
            'managedUsers' => $managedUsers,
            'userAddresses' => $user->addresses,
            'managementStats' => $managementStats,
            'assignedManager' => $user->manager,
            'supportMessages' => $supportMessages,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'contact_people' => ['nullable', 'array', 'max:10'],
            'contact_people.*' => ['nullable', 'string', 'max:255'],
            'messengers' => ['nullable', 'array', 'max:10'],
            'messengers.*' => ['nullable', 'string', 'max:255'],
        ]);

        $contactPeople = $this->normalizeStringList($validated['contact_people'] ?? []);
        $messengers = $this->normalizeStringList($validated['messengers'] ?? []);

        $request->user()->update([
            'name' => trim($validated['name']),
            'company' => filled($validated['company'] ?? null) ? trim($validated['company']) : null,
            'contact_person' => $contactPeople[0] ?? null,
            'contact_people' => $contactPeople !== [] ? $contactPeople : null,
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'telegram' => $messengers[0] ?? null,
            'messengers' => $messengers !== [] ? $messengers : null,
        ]);

        return redirect()
            ->route('account.show')
            ->with('status', 'Контактные данные обновлены.');
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function normalizeStringList(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
