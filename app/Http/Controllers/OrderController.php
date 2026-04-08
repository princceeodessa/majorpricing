<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderStatuses;
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
            ->with(['user', 'items.product'])
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
            'statusOptions' => OrderStatuses::options(),
            'orderStats' => [
                'total' => (clone $baseQuery)->count(),
                'new' => (clone $baseQuery)->where('status', OrderStatuses::NEW)->count(),
                'processing' => (clone $baseQuery)->whereIn('status', OrderStatuses::inProgress())->count(),
            ],
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(OrderStatuses::allowed())],
            'manager_comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $order->update([
            'status' => OrderStatuses::normalize($validated['status']) ?? OrderStatuses::NEW,
            'manager_comment' => filled($validated['manager_comment'] ?? null) ? trim($validated['manager_comment']) : null,
        ]);

        return redirect()
            ->route('orders.index')
            ->with('status', 'Заказ обновлен.');
    }
}
