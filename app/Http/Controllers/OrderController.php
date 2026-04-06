<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = $user->isManager();

        $orders = Order::query()
            ->with(['user.priceProfile', 'items.product'])
            ->when(! $isManager, fn ($query) => $query->where('user_id', $user->id))
            ->latest('placed_at')
            ->latest('id')
            ->paginate(12);

        $baseQuery = Order::query();

        if (! $isManager) {
            $baseQuery->where('user_id', $user->id);
        }

        return view('orders.index', [
            'orders' => $orders,
            'isManager' => $isManager,
            'statusOptions' => [
                'new' => 'Новый',
                'processing' => 'Подтвержден',
                'completed' => 'Выполнен',
                'canceled' => 'Отменен',
                'payment_failed' => 'Проблема с оплатой',
            ],
            'orderStats' => [
                'total' => (clone $baseQuery)->count(),
                'new' => (clone $baseQuery)->where('status', 'new')->count(),
                'processing' => (clone $baseQuery)->where('status', 'processing')->count(),
            ],
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['new', 'processing', 'completed', 'canceled', 'payment_failed'])],
            'manager_comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $order->update([
            'status' => $validated['status'],
            'manager_comment' => filled($validated['manager_comment'] ?? null) ? trim($validated['manager_comment']) : null,
        ]);

        return redirect()
            ->route('orders.index')
            ->with('status', 'Заказ обновлен.');
    }
}
