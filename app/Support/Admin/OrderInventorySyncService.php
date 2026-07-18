<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderInventorySyncService
{
    public function syncForPaymentTransition(string $orderId, string $fromStatus, string $toStatus, $actorId): array
    {
        $from = strtolower(trim($fromStatus));
        $to = strtolower(trim($toStatus));

        $shouldDeduct = $from !== 'paid' && $to === 'paid';
        $shouldRestock = $from === 'paid' && in_array($to, ['canceled', 'failed'], true);

        if (! $shouldDeduct && ! $shouldRestock) {
            return ['action' => 'none'];
        }

        $order = DB::table('orders')
            ->select('id', 'inventory_applied_at', 'inventory_reverted_at')
            ->where('id', $orderId)
            ->lockForUpdate()
            ->first();

        if (! $order) {
            throw new \RuntimeException('Order not found.');
        }

        $lineItems = $this->loadOrderLines($orderId);
        if (! $lineItems) {
            return ['action' => 'none'];
        }

        if ($shouldDeduct) {
            if ($order->inventory_applied_at && ! $order->inventory_reverted_at) {
                return ['action' => 'already_applied'];
            }

            foreach ($lineItems as $item) {
                $this->ensureInventoryRow($item['product_id']);

                $stock = DB::table('product_inventory')
                    ->select('qty_on_hand')
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                $onHand = (int) ($stock->qty_on_hand ?? 0);
                if ($onHand < $item['quantity']) {
                    throw new \RuntimeException('Insufficient stock for product #'.$item['product_id'].'.');
                }
            }

            foreach ($lineItems as $item) {
                DB::table('product_inventory')
                    ->where('product_id', $item['product_id'])
                    ->update([
                        'qty_on_hand' => DB::raw('qty_on_hand - '.$item['quantity']),
                        'updated_at' => now(),
                    ]);

                DB::table('inventory_movements')->insert([
                    'id' => (string) Str::uuid(),
                    'product_id' => $item['product_id'],
                    'movement_type' => 'order_deduct',
                    'quantity' => $item['quantity'],
                    'note' => 'Order '.$orderId.' paid',
                    'created_by_user_id' => $actorId ?: null,
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'created_at' => now(),
                ]);
            }

            DB::table('orders')->where('id', $orderId)->update([
                'inventory_applied_at' => now(),
                'inventory_reverted_at' => null,
            ]);

            return ['action' => 'deducted'];
        }

        if (! $order->inventory_applied_at) {
            return ['action' => 'not_applied'];
        }

        if ($order->inventory_reverted_at) {
            return ['action' => 'already_reverted'];
        }

        foreach ($lineItems as $item) {
            $this->ensureInventoryRow($item['product_id']);

            DB::table('product_inventory')
                ->where('product_id', $item['product_id'])
                ->update([
                    'qty_on_hand' => DB::raw('qty_on_hand + '.$item['quantity']),
                    'updated_at' => now(),
                ]);

            DB::table('inventory_movements')->insert([
                'id' => (string) Str::uuid(),
                'product_id' => $item['product_id'],
                'movement_type' => 'order_restock',
                'quantity' => $item['quantity'],
                'note' => 'Order '.$orderId.' '.$to,
                'created_by_user_id' => $actorId ?: null,
                'reference_type' => 'order',
                'reference_id' => $orderId,
                'created_at' => now(),
            ]);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'inventory_reverted_at' => now(),
        ]);

        return ['action' => 'restocked'];
    }

    protected function loadOrderLines(string $orderId): array
    {
        $rows = DB::table('order_items')
            ->select('product_id', 'quantity')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        $byProduct = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $quantity = (int) $row->quantity;

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            if (! isset($byProduct[$productId])) {
                $byProduct[$productId] = 0;
            }

            $byProduct[$productId] += $quantity;
        }

        $items = [];
        foreach ($byProduct as $productId => $quantity) {
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        return $items;
    }

    protected function ensureInventoryRow(int $productId): void
    {
        DB::table('product_inventory')->updateOrInsert(
            ['product_id' => $productId],
            ['updated_at' => now()]
        );
    }
}
