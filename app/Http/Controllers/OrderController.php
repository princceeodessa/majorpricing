<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with('items.product')
            ->where('user_id', $request->user()->id)
            ->latest('placed_at')
            ->latest('id')
            ->paginate(12);

        return view('orders.index', [
            'orders' => $orders,
        ]);
    }
}
