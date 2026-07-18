<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PaymentsAdminService
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
            ['label' => 'Paid', 'value' => $this->countWhere('orders', 'status', 'paid')],
            ['label' => 'Slips Uploaded', 'value' => $this->notNullCount('orders', 'payment_slip_uploaded_at')],
        ];
    }

    public function listPayments(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $rows = DB::table('orders')
            ->select('id', 'user_id', 'user_email', 'total_amount', 'currency', 'status', 'payment_slip_data', 'payment_slip_uploaded_at', 'created_at', 'updated_at')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $orderIds = $rows->pluck('id')->filter()->values()->all();
        $linesByOrderId = $this->linesByOrderId($orderIds);
        $eventsByOrderId = $this->eventsByOrderId($orderIds);

        return $rows->map(function ($row) use ($linesByOrderId, $eventsByOrderId) {
            return [
                'order_id' => $row->id,
                'user_id' => $row->user_id,
                'user_email' => $row->user_email,
                'total_amount' => $row->total_amount,
                'currency' => $row->currency,
                'status' => $row->status,
                'payment_slip_data' => $row->payment_slip_data,
                'payment_slip_uploaded_at' => $row->payment_slip_uploaded_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'lines' => $linesByOrderId[$row->id] ?? [],
                'events' => $eventsByOrderId[$row->id] ?? [],
            ];
        })->all();
    }

    public function updateStatus(string $orderId, string $nextStatus, $actorId, string $actorRole, ?string $note = null): void
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
            throw new \InvalidArgumentException('Invalid payment status.');
        }

        DB::transaction(function () use ($orderId, $nextStatus, $actorId, $actorRole, $note, $transitions) {
            $order = DB::table('orders')
                ->select('id', 'status', 'shipping_status')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('Payment not found.');
            }

            $currentStatus = strtolower((string) $order->status);
            if ($currentStatus === $nextStatus) {
                throw new \InvalidArgumentException('Status is unchanged.');
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

            if (Schema::hasTable('order_payment_events')) {
                DB::table('order_payment_events')->insert([
                    'id' => (string) Str::uuid(),
                    'order_id' => $orderId,
                    'from_status' => $currentStatus,
                    'to_status' => $nextStatus,
                    'actor_id' => (string) $actorId,
                    'actor_role' => $actorRole,
                    'note' => $this->nullableString($note),
                    'created_at' => now(),
                ]);
            }
        });
    }

    protected function nullableString($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_substr($text, 0, 512);
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

    protected function notNullCount($table, $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->whereNotNull($column)->count();
    }

    protected function linesByOrderId(array $orderIds): array
    {
        if (! $orderIds || ! Schema::hasTable('order_items')) {
            return [];
        }

        return DB::table('order_items')
            ->select('order_id', 'product_id', 'name', 'image', 'size', 'quantity', 'unit_amount', 'line_total')
            ->whereIn('order_id', $orderIds)
            ->orderBy('id')
            ->get()
            ->groupBy('order_id')
            ->map(function ($rows) {
                return $rows->map(fn ($row) => (array) $row)->all();
            })
            ->all();
    }

    protected function eventsByOrderId(array $orderIds): array
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
}
