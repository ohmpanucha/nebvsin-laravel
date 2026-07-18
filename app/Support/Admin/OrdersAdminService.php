<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdersAdminService
{
    protected $inventorySync;

    public function __construct(OrderInventorySyncService $inventorySync)
    {
        $this->inventorySync = $inventorySync;
    }

    public function summary(): array
    {
        return [
            ['label' => 'Orders', 'value' => $this->countTable('orders')],
            ['label' => 'Pending', 'value' => $this->countWhere('orders', 'status', 'pending')],
            ['label' => 'Revenue THB', 'value' => number_format($this->sumColumn('orders', 'total_amount'))],
        ];
    }

    public function listOrders(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $rows = DB::table('orders')
            ->select('id', 'user_id', 'user_email', 'total_amount', 'currency', 'status', 'shipping_status', 'payment_slip_uploaded_at', 'created_at', 'updated_at')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $orderIds = $rows->pluck('id')->filter()->values()->all();
        $linesByOrderId = $this->linesByOrderId($orderIds);
        $addressesByOrderId = $this->addressesByOrderId($orderIds);
        $paymentEventsByOrderId = $this->paymentEventsByOrderId($orderIds);
        $shippingEventsByOrderId = $this->shippingEventsByOrderId($orderIds);

        return $rows->map(function ($row) use ($linesByOrderId, $addressesByOrderId, $paymentEventsByOrderId, $shippingEventsByOrderId) {
            $lines = $linesByOrderId[$row->id] ?? [];

            return [
                'order_id' => $row->id,
                'user_id' => $row->user_id,
                'user_email' => $row->user_email,
                'total_amount' => $row->total_amount,
                'currency' => $row->currency,
                'status' => $row->status,
                'shipping_status' => $row->shipping_status,
                'payment_slip_uploaded_at' => $row->payment_slip_uploaded_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'has_slip' => $row->payment_slip_uploaded_at !== null,
                'item_count' => array_sum(array_map(function (array $line) {
                    return (int) ($line['quantity'] ?? 0);
                }, $lines)),
                'address' => $addressesByOrderId[$row->id] ?? null,
                'lines' => $lines,
                'payment_events' => $paymentEventsByOrderId[$row->id] ?? [],
                'shipping_events' => $shippingEventsByOrderId[$row->id] ?? [],
            ];
        })->all();
    }

    public function updateStatus(string $orderId, string $nextStatus, $actorId): void
    {
        $allowedStatuses = ['pending', 'awaiting_review', 'paid', 'failed', 'canceled'];
        $transitions = [
            'pending' => ['awaiting_review', 'paid', 'failed', 'canceled'],
            'awaiting_review' => ['paid', 'failed'],
            'paid' => ['canceled'],
            'failed' => [],
            'canceled' => [],
        ];

        if (! in_array($nextStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        DB::transaction(function () use ($orderId, $nextStatus, $actorId, $transitions) {
            $order = DB::table('orders')
                ->select('id', 'status', 'shipping_status')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('Order not found.');
            }

            $currentStatus = strtolower((string) $order->status);
            if ($currentStatus === $nextStatus) {
                return;
            }

            $allowedNext = $transitions[$currentStatus] ?? [];
            if (! in_array($nextStatus, $allowedNext, true)) {
                throw new \InvalidArgumentException('Invalid status transition: '.$currentStatus.' -> '.$nextStatus.'.');
            }

            $payload = [
                'status' => $nextStatus,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('orders', 'shipping_status')
                && $nextStatus === 'paid'
                && (! $order->shipping_status || $order->shipping_status === '')
            ) {
                $payload['shipping_status'] = 'pending_fulfillment';
            }

            DB::table('orders')->where('id', $orderId)->update($payload);

            if (Schema::hasTable('product_inventory') && Schema::hasTable('inventory_movements')) {
                $this->inventorySync->syncForPaymentTransition($orderId, $currentStatus, $nextStatus, $actorId);
            }
        });
    }

    protected function countTable($table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    protected function countWhere($table, $column, $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->where($column, $value)->count();
    }

    protected function sumColumn($table, $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->sum($column);
    }

    protected function linesByOrderId(array $orderIds): array
    {
        if (! $orderIds || ! Schema::hasTable('order_items')) {
            return [];
        }

        return DB::table('order_items')
            ->select('order_id', 'name', 'image', 'size', 'quantity', 'unit_amount', 'line_total')
            ->whereIn('order_id', $orderIds)
            ->orderBy('id')
            ->get()
            ->groupBy('order_id')
            ->map(function ($rows) {
                return $rows->map(fn ($row) => (array) $row)->all();
            })
            ->all();
    }

    protected function addressesByOrderId(array $orderIds): array
    {
        if (! $orderIds || ! Schema::hasTable('order_addresses')) {
            return [];
        }

        return DB::table('order_addresses')
            ->select('order_id', 'full_name', 'phone', 'address_line1', 'address_line2', 'district', 'province', 'postal_code')
            ->whereIn('order_id', $orderIds)
            ->get()
            ->keyBy('order_id')
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    protected function paymentEventsByOrderId(array $orderIds): array
    {
        if (! $orderIds || ! Schema::hasTable('order_payment_events')) {
            return [];
        }

        return DB::table('order_payment_events')
            ->select('order_id', 'created_at', 'from_status', 'to_status', 'actor_id', 'actor_role', 'note')
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('order_id')
            ->map(function ($rows) {
                return $rows->map(fn ($row) => (array) $row)->all();
            })
            ->all();
    }

    protected function shippingEventsByOrderId(array $orderIds): array
    {
        if (! $orderIds || ! Schema::hasTable('order_shipping_events')) {
            return [];
        }

        return DB::table('order_shipping_events')
            ->select('order_id', 'created_at', 'from_status', 'to_status', 'tracking_number', 'shipping_carrier', 'actor_id', 'actor_role', 'note')
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('order_id')
            ->map(function ($rows) {
                return $rows->map(fn ($row) => (array) $row)->all();
            })
            ->all();
    }
}
