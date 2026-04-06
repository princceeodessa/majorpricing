<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('priceProfile');
        $priceProfiles = PriceProfile::query()
            ->orderBy('column_index')
            ->get();

        $managedUsers = collect();
        $managementStats = [
            'totalUsers' => 0,
            'activeUsers' => 0,
            'disabledUsers' => 0,
            'profilesCount' => $priceProfiles->count(),
            'newOrders' => 0,
            'processingOrders' => 0,
        ];

        if ($user->isManager()) {
            $managedUsers = User::query()
                ->with('priceProfile')
                ->withCount('orders')
                ->orderByDesc('id')
                ->get();

            $managementStats = [
                'totalUsers' => $managedUsers->count(),
                'activeUsers' => $managedUsers->where('is_active', true)->count(),
                'disabledUsers' => $managedUsers->where('is_active', false)->count(),
                'profilesCount' => $priceProfiles->count(),
                'newOrders' => Order::query()->where('status', 'new')->count(),
                'processingOrders' => Order::query()->where('status', 'processing')->count(),
            ];
        }

        return view('account.show', [
            'profile' => $user->priceProfile,
            'priceProfiles' => $priceProfiles,
            'managedUsers' => $managedUsers,
            'managementStats' => $managementStats,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'telegram' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:1500'],
        ]);

        $request->user()->update([
            'name' => trim($validated['name']),
            'company' => filled($validated['company'] ?? null) ? trim($validated['company']) : null,
            'contact_person' => filled($validated['contact_person'] ?? null) ? trim($validated['contact_person']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'telegram' => filled($validated['telegram'] ?? null) ? trim($validated['telegram']) : null,
            'delivery_address' => filled($validated['delivery_address'] ?? null) ? trim($validated['delivery_address']) : null,
        ]);

        return redirect()
            ->route('account.show')
            ->with('status', 'Контактные данные обновлены.');
    }
}
