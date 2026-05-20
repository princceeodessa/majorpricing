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
        $isManager = $user->canManageClients();

        $orders = Order::query()
            ->with(['user', 'items.product'])
            ->when(
                $isManager,
                fn ($query) => $query->whereIn('user_id', $user->visibleClients()->select('id')),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->latest('placed_at')
            ->latest('id')
            ->paginate(12);

        $baseQuery = Order::query();

        if ($isManager) {
            $baseQuery->whereIn('user_id', $user->visibleClients()->select('id'));
        } else {
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
        $this->ensureManagerCanManageOrder($request, $order);

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

    public function queueOneCExport(Request $request, Order $order): RedirectResponse
    {
        $this->ensureManagerCanManageOrder($request, $order);

        $order->forceFill([
            'one_c_exported_at' => null,
            'one_c_updated_at' => now(),
        ])->save();

        return redirect()
            ->route('orders.index')
            ->with('status', 'Заказ добавлен в очередь выгрузки 1С.');
    }

    private function ensureManagerCanManageOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        abort_unless($user->canManageClients(), 403);

        if (! $user->isAdmin()) {
            abort_unless((int) $order->user?->manager_id === (int) $user->id, 403);
        }
    }
}
