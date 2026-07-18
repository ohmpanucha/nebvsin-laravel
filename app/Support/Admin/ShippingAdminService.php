<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ShippingAdminService
{
    public function summary(): array
    {
        return [
            ['label' => 'Queued', 'value' => $this->countWhere('orders', 'shipping_status', 'pending_fulfillment')],
            ['label' => 'Shipped', 'value' => $this->countWhere('orders', 'shipping_status', 'shipped')],
            ['label' => 'Delivered', 'value' => $this->countWhere('orders', 'shipping_status', 'delivered')],
        ];
    }

    public function listShipments(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $rows = DB::table('orders')
            ->select('id', 'user_id', 'user_email', 'total_amount', 'currency', 'status', 'shipping_status', 'tracking_number', 'shipping_carrier', 'shipped_at', 'delivered_at', 'created_at')
            ->where('status', 'paid')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $orderIds = $rows->pluck('id')->filter()->values()->all();
        $linesByOrderId = $this->linesByOrderId($orderIds);
        $addressesByOrderId = $this->addressesByOrderId($orderIds);
        $eventsByOrderId = $this->eventsByOrderId($orderIds);

        return $rows->map(function ($row) use ($linesByOrderId, $addressesByOrderId, $eventsByOrderId) {
            $lines = $linesByOrderId[$row->id] ?? [];
            $shippingStatus = (string) ($row->shipping_status ?: 'pending_fulfillment');
            $trackingNumber = $row->tracking_number ?: null;
            $createdAt = $row->created_at ? strtotime((string) $row->created_at) : null;
            $isOverdue = in_array($shippingStatus, ['pending_fulfillment', 'processing'], true)
                && $createdAt !== null
                && $createdAt <= strtotime('-48 hours');

            return [
                'order_id' => $row->id,
                'user_id' => $row->user_id,
                'user_email' => $row->user_email,
                'total_amount' => $row->total_amount,
                'currency' => $row->currency,
                'payment_status' => $row->status,
                'shipping_status' => $shippingStatus,
                'tracking_number' => $trackingNumber,
                'shipping_carrier' => $row->shipping_carrier,
                'shipped_at' => $row->shipped_at,
                'delivered_at' => $row->delivered_at,
                'created_at' => $row->created_at,
                'item_count' => array_sum(array_map(function (array $line) {
                    return (int) ($line['quantity'] ?? 0);
                }, $lines)),
                'has_tracking' => $trackingNumber !== null,
                'is_overdue' => $isOverdue,
                'address' => $addressesByOrderId[$row->id] ?? null,
                'lines' => $lines,
                'events' => $eventsByOrderId[$row->id] ?? [],
            ];
        })->all();
    }

    public function updateShipment(string $orderId, string $nextStatus, ?string $trackingNumber, ?string $shippingCarrier, $actorId, string $actorRole, ?string $note = null): void
    {
        $allowedStatuses = ['pending_fulfillment', 'processing', 'shipped', 'delivered'];
        $transitions = [
            'pending_fulfillment' => ['processing', 'shipped'],
            'processing' => ['shipped'],
            'shipped' => ['delivered'],
            'delivered' => [],
        ];

        if (! in_array($nextStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid shipping status.');
        }

        $trackingNumber = $this->trimmed($trackingNumber, 191);
        $shippingCarrier = $this->trimmed($shippingCarrier, 191);

        if ($nextStatus === 'shipped' && ! $trackingNumber) {
            throw new \InvalidArgumentException('Tracking number is required for shipped status.');
        }

        DB::transaction(function () use ($orderId, $nextStatus, $trackingNumber, $shippingCarrier, $actorId, $actorRole, $note, $transitions) {
            $order = DB::table('orders')
                ->select('id', 'status', 'shipping_status')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('Shipment not found.');
            }

            if (strtolower((string) $order->status) !== 'paid') {
                throw new \InvalidArgumentException('Shipping is available only for paid orders.');
            }

            $currentStatus = strtolower((string) ($order->shipping_status ?: 'pending_fulfillment'));
            $hasMetaChanges = $trackingNumber || $shippingCarrier;
            if ($currentStatus === $nextStatus && ! $hasMetaChanges) {
                throw new \InvalidArgumentException('No changes detected.');
            }

            $allowedNext = $transitions[$currentStatus] ?? [];
            if ($currentStatus !== $nextStatus && ! in_array($nextStatus, $allowedNext, true)) {
                throw new \InvalidArgumentException('Invalid status transition: '.$currentStatus.' -> '.$nextStatus.'.');
            }

            $payload = [
                'shipping_status' => $nextStatus,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('orders', 'tracking_number') && $trackingNumber) {
                $payload['tracking_number'] = $trackingNumber;
            }

            if (Schema::hasColumn('orders', 'shipping_carrier') && $shippingCarrier) {
                $payload['shipping_carrier'] = $shippingCarrier;
            }

            if (Schema::hasColumn('orders', 'shipped_at') && $nextStatus === 'shipped') {
                $payload['shipped_at'] = DB::raw('COALESCE(shipped_at, CURRENT_TIMESTAMP)');
            }

            if (Schema::hasColumn('orders', 'delivered_at') && $nextStatus === 'delivered') {
                $payload['delivered_at'] = DB::raw('COALESCE(delivered_at, CURRENT_TIMESTAMP)');
            }

            DB::table('orders')->where('id', $orderId)->update($payload);

            if (Schema::hasTable('order_shipping_events')) {
                DB::table('order_shipping_events')->insert([
                    'id' => (string) Str::uuid(),
                    'order_id' => $orderId,
                    'from_status' => $currentStatus,
                    'to_status' => $nextStatus,
                    'tracking_number' => $trackingNumber,
                    'shipping_carrier' => $shippingCarrier,
                    'actor_id' => (string) $actorId,
                    'actor_role' => $actorRole,
                    'note' => $this->trimmed($note, 512),
                    'created_at' => now(),
                ]);
            }
        });
    }

    protected function trimmed($value, int $limit): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    protected function countWhere($table, $column, $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->where($column, $value)->count();
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

    protected function eventsByOrderId(array $orderIds): array
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
