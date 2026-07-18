<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InventoryAdminService
{
    public function summary(): array
    {
        return [
            ['label' => 'Tracked Products', 'value' => $this->countTable('product_inventory')],
            ['label' => 'Low Stock', 'value' => $this->lowStockCount()],
            ['label' => 'Movements', 'value' => $this->countTable('inventory_movements')],
        ];
    }

    public function snapshot(): array
    {
        if (! Schema::hasTable('product_inventory')) {
            return [];
        }

        $rows = DB::table('products as p')
            ->leftJoin('product_inventory as pi', 'pi.product_id', '=', 'p.id')
            ->select(
                'p.id as product_id',
                'p.name',
                'p.image',
                DB::raw('COALESCE(pi.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('COALESCE(pi.low_stock_threshold, 5) as low_stock_threshold'),
                DB::raw('(
                    COALESCE(pi.low_stock_threshold, 5) > 0
                    AND COALESCE(pi.qty_on_hand, 0) <= COALESCE(pi.low_stock_threshold, 5)
                ) as is_low_stock'),
                DB::raw('COALESCE(pi.avg_unit_cost, 0) as avg_unit_cost'),
                DB::raw('COALESCE(pi.last_unit_cost, 0) as last_unit_cost'),
                'pi.updated_at'
            )
            ->orderBy('p.sort_order')
            ->orderBy('p.id')
            ->get();

        return $rows->map(function ($row) {
            $item = (array) $row;
            $item['is_low_stock'] = (bool) $item['is_low_stock'];

            return $item;
        })->all();
    }

    public function lowStockItems(): array
    {
        return array_values(array_filter($this->snapshot(), function (array $item) {
            return ! empty($item['is_low_stock']);
        }));
    }

    public function recentMovements(int $limit = 30): array
    {
        if (! Schema::hasTable('inventory_movements')) {
            return [];
        }

        $rows = DB::table('inventory_movements as im')
            ->join('products as p', 'p.id', '=', 'im.product_id')
            ->select(
                'im.id',
                'im.product_id',
                'p.name as product_name',
                'im.movement_type',
                'im.quantity',
                'im.note',
                'im.created_by_user_id',
                'im.created_at'
            )
            ->orderByDesc('im.created_at')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return (array) $row;
        })->all();
    }

    public function receive(int $productId, int $quantity, ?string $note, $actorId): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('quantity must be a positive integer.');
        }

        DB::transaction(function () use ($productId, $quantity, $note, $actorId) {
            $this->ensureProductExists($productId);
            $this->ensureInventoryRow($productId);

            DB::table('product_inventory')
                ->where('product_id', $productId)
                ->update([
                    'qty_on_hand' => DB::raw('qty_on_hand + '.$quantity),
                    'updated_at' => now(),
                ]);

            DB::table('inventory_movements')->insert([
                'id' => (string) Str::uuid(),
                'product_id' => $productId,
                'movement_type' => 'receive',
                'quantity' => $quantity,
                'note' => $note,
                'created_by_user_id' => $actorId,
                'created_at' => now(),
            ]);
        });
    }

    public function deduct(int $productId, int $quantity, ?string $note, $actorId): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('quantity must be a positive integer.');
        }

        DB::transaction(function () use ($productId, $quantity, $note, $actorId) {
            $this->ensureProductExists($productId);
            $this->ensureInventoryRow($productId);

            $stock = DB::table('product_inventory')
                ->select('qty_on_hand')
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $currentQty = (int) ($stock->qty_on_hand ?? 0);
            if ($currentQty < $quantity) {
                throw new \RuntimeException('Insufficient stock.');
            }

            DB::table('product_inventory')
                ->where('product_id', $productId)
                ->update([
                    'qty_on_hand' => DB::raw('qty_on_hand - '.$quantity),
                    'updated_at' => now(),
                ]);

            DB::table('inventory_movements')->insert([
                'id' => (string) Str::uuid(),
                'product_id' => $productId,
                'movement_type' => 'deduct',
                'quantity' => $quantity,
                'note' => $note,
                'created_by_user_id' => $actorId,
                'created_at' => now(),
            ]);
        });
    }

    public function updateThreshold(int $productId, int $threshold): void
    {
        if ($threshold < 0) {
            throw new \InvalidArgumentException('low_stock_threshold must be a non-negative integer.');
        }

        DB::transaction(function () use ($productId, $threshold) {
            $this->ensureProductExists($productId);
            $this->ensureInventoryRow($productId);

            DB::table('product_inventory')
                ->where('product_id', $productId)
                ->update([
                    'low_stock_threshold' => $threshold,
                    'updated_at' => now(),
                ]);
        });
    }

    protected function ensureProductExists(int $productId): void
    {
        $exists = Schema::hasTable('products') && DB::table('products')->where('id', $productId)->exists();
        if (! $exists) {
            throw new \RuntimeException('Product not found.');
        }
    }

    protected function ensureInventoryRow(int $productId): void
    {
        DB::table('product_inventory')->updateOrInsert(
            ['product_id' => $productId],
            ['updated_at' => now()]
        );
    }

    protected function countTable($table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    protected function lowStockCount(): int
    {
        if (! Schema::hasTable('product_inventory')) {
            return 0;
        }

        return (int) DB::table('product_inventory')
            ->whereColumn('qty_on_hand', '<=', 'low_stock_threshold')
            ->count();
    }
}
