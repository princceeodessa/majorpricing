<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ErpOrderSyncService;
use Illuminate\Console\Command;

class SyncErpOrdersCommand extends Command
{
    protected $signature = 'orders:sync-erp
        {orderNumber? : Sync a single order number}
        {--status=* : Integration statuses to sync}
        {--limit=50 : Max orders to process in one run}';

    protected $description = 'Push pending or failed orders to the ERP endpoint';

    public function handle(ErpOrderSyncService $erpOrderSyncService): int
    {
        if (! $erpOrderSyncService->isConfigured()) {
            $this->warn('ERP_ORDERS_PUSH_URL is not configured.');

            return self::FAILURE;
        }

        $statuses = $this->option('status');
        $statuses = $statuses !== [] ? $statuses : ['pending', 'failed'];

        $orders = Order::query()
            ->with(['user', 'items.product'])
            ->when(
                filled($this->argument('orderNumber')),
                fn ($query) => $query->where('number', $this->argument('orderNumber')),
                fn ($query) => $query->whereIn('integration_status', $statuses)->limit((int) $this->option('limit')),
            )
            ->oldest('placed_at')
            ->oldest('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders matched the sync criteria.');

            return self::SUCCESS;
        }

        $synced = 0;

        foreach ($orders as $order) {
            $result = $erpOrderSyncService->push($order);
            $synced += $result ? 1 : 0;

            $this->line(sprintf(
                '[%s] %s',
                $result ? 'SYNCED' : 'FAILED',
                $order->number ?? 'order-'.$order->id,
            ));
        }

        $this->info(sprintf('Processed: %d, synced: %d, failed: %d', $orders->count(), $synced, $orders->count() - $synced));

        return self::SUCCESS;
    }
}
