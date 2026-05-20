<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1500'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Укажите название адреса.',
            'address.required' => 'Укажите адрес доставки.',
        ]);

        $user = $request->user();

        if (! $user->addresses()->exists()) {
            $validated['is_default'] = true;
        }

        if ($request->boolean('is_default')) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create([
            'title' => trim($validated['title']),
            'address' => trim($validated['address']),
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'sort_order' => (int) $user->addresses()->count(),
        ]);

        $this->syncUserDeliveryAddress($user->fresh());

        return redirect()
            ->route('account.show')
            ->with('status', 'Адрес добавлен.');
    }

    public function makeDefault(Request $request, UserAddress $userAddress): RedirectResponse
    {
        abort_unless($userAddress->user_id === $request->user()->id, 404);

        $request->user()->addresses()->update(['is_default' => false]);

        $userAddress->update([
            'is_default' => true,
        ]);

        $this->syncUserDeliveryAddress($request->user()->fresh());

        return redirect()
            ->route('account.show')
            ->with('status', 'Адрес по умолчанию обновлен.');
    }

    public function destroy(Request $request, UserAddress $userAddress): RedirectResponse
    {
        abort_unless($userAddress->user_id === $request->user()->id, 404);

        $user = $request->user();

        $wasDefault = $userAddress->is_default;

        $userAddress->delete();

        if ($wasDefault) {
            $fallback = $user->addresses()->first();

            if ($fallback) {
                $fallback->update(['is_default' => true]);
            }
        }

        $this->syncUserDeliveryAddress($user->fresh());

        return redirect()
            ->route('account.show')
            ->with('status', 'Адрес удален.');
    }

    private function syncUserDeliveryAddress(User $user): void
    {
        $defaultAddress = $user->addresses()->first();

        $user->forceFill([
            'delivery_address' => $defaultAddress?->address,
        ])->save();
    }
}
