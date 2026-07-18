<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcurementAdminService
{
    public function summary(): array
    {
        return [
            ['label' => 'Suppliers', 'value' => $this->countTable('suppliers')],
            ['label' => 'Purchase Orders', 'value' => $this->countTable('purchase_orders')],
            ['label' => 'Goods Receipts', 'value' => $this->countTable('goods_receipts')],
        ];
    }

    public function suppliers(): array
    {
        if (! Schema::hasTable('suppliers')) {
            return [];
        }

        return DB::table('suppliers')
            ->select('id', 'name', 'contact_name', 'phone', 'note', 'created_at', 'updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    public function productCosts(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        return DB::table('products as p')
            ->leftJoin('product_inventory as pi', 'pi.product_id', '=', 'p.id')
            ->select(
                'p.id as product_id',
                'p.name',
                DB::raw('COALESCE(pi.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('COALESCE(pi.avg_unit_cost, 0) as avg_unit_cost'),
                DB::raw('COALESCE(pi.last_unit_cost, 0) as last_unit_cost')
            )
            ->orderBy('p.sort_order')
            ->orderBy('p.id')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    public function listPurchaseOrders(): array
    {
        if (! Schema::hasTable('purchase_orders')) {
            return [];
        }

        $rows = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('purchase_order_items as poi', 'poi.purchase_order_id', '=', 'po.id')
            ->select(
                'po.id',
                'po.supplier_id',
                's.name as supplier_name',
                'po.status',
                'po.note',
                'po.subtotal_amount',
                'po.extra_cost_amount',
                'po.total_amount',
                'po.created_at',
                'po.updated_at',
                DB::raw('COALESCE(SUM(poi.ordered_qty), 0) as ordered_units'),
                DB::raw('COALESCE(SUM(poi.received_qty), 0) as received_units')
            )
            ->groupBy('po.id', 'po.supplier_id', 's.name', 'po.status', 'po.note', 'po.subtotal_amount', 'po.extra_cost_amount', 'po.total_amount', 'po.created_at', 'po.updated_at')
            ->orderByDesc('po.created_at')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();

        if (! $rows) {
            return [];
        }

        $poIds = array_map(function ($row) {
            return $row['id'];
        }, $rows);

        $itemsByPo = Schema::hasTable('purchase_order_items')
            ? DB::table('purchase_order_items as poi')
                ->leftJoin('products as p', 'p.id', '=', 'poi.product_id')
                ->select('poi.id', 'poi.purchase_order_id', 'poi.product_id', 'p.name as product_name', 'poi.ordered_qty', 'poi.received_qty', 'poi.unit_cost', 'poi.effective_unit_cost')
                ->whereIn('poi.purchase_order_id', $poIds)
                ->orderBy('poi.created_at')
                ->get()
                ->groupBy('purchase_order_id')
            : collect();

        $receiptsByPo = Schema::hasTable('goods_receipts')
            ? DB::table('goods_receipts as gr')
                ->leftJoin('goods_receipt_items as gri', 'gri.goods_receipt_id', '=', 'gr.id')
                ->select('gr.id', 'gr.purchase_order_id', 'gr.note', 'gr.created_at', DB::raw('COALESCE(SUM(gri.received_qty), 0) as total_units'))
                ->whereIn('gr.purchase_order_id', $poIds)
                ->groupBy('gr.id', 'gr.purchase_order_id', 'gr.note', 'gr.created_at')
                ->orderByDesc('gr.created_at')
                ->get()
                ->groupBy('purchase_order_id')
            : collect();

        $extraCostsByPo = Schema::hasTable('purchase_order_extra_costs')
            ? DB::table('purchase_order_extra_costs')
                ->select('id', 'purchase_order_id', 'cost_name', 'amount', 'sort_order', 'created_at')
                ->whereIn('purchase_order_id', $poIds)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get()
                ->groupBy('purchase_order_id')
            : collect();

        foreach ($rows as &$row) {
            $row['items'] = collect($itemsByPo->get($row['id'], []))->map(function ($item) {
                return (array) $item;
            })->all();
            $row['extra_costs'] = collect($extraCostsByPo->get($row['id'], []))->map(function ($item) {
                return (array) $item;
            })->all();
            $row['receipts'] = collect($receiptsByPo->get($row['id'], []))->map(function ($item) {
                return (array) $item;
            })->all();
        }

        return $rows;
    }

    public function purchaseOrderDetail(string $poId): ?array
    {
        if (! Schema::hasTable('purchase_orders')) {
            return null;
        }

        $header = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->select(
                'po.id',
                'po.supplier_id',
                's.name as supplier_name',
                'po.status',
                'po.note',
                'po.subtotal_amount',
                'po.extra_cost_amount',
                'po.total_amount',
                'po.created_at',
                'po.updated_at'
            )
            ->where('po.id', $poId)
            ->first();

        if (! $header) {
            return null;
        }

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->select(
                'poi.id',
                'poi.product_id',
                'p.name as product_name',
                'poi.ordered_qty',
                'poi.received_qty',
                'poi.unit_cost',
                'poi.line_total',
                'poi.allocated_extra_cost',
                'poi.effective_unit_cost',
                'poi.effective_line_total'
            )
            ->where('poi.purchase_order_id', $poId)
            ->orderBy('poi.created_at')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();

        $extraCosts = Schema::hasTable('purchase_order_extra_costs')
            ? DB::table('purchase_order_extra_costs')
                ->select('id', 'cost_name', 'amount', 'sort_order', 'created_at', 'updated_at')
                ->where('purchase_order_id', $poId)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all()
            : [];

        $receipts = Schema::hasTable('goods_receipts')
            ? DB::table('goods_receipts as gr')
                ->leftJoin('goods_receipt_items as gri', 'gri.goods_receipt_id', '=', 'gr.id')
                ->select('gr.id', 'gr.note', 'gr.created_at', DB::raw('COALESCE(SUM(gri.received_qty), 0) as total_units'))
                ->where('gr.purchase_order_id', $poId)
                ->groupBy('gr.id', 'gr.note', 'gr.created_at')
                ->orderByDesc('gr.created_at')
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->all()
            : [];

        return [
            'purchase_order' => (array) $header,
            'items' => $items,
            'extra_costs' => $extraCosts,
            'receipts' => $receipts,
        ];
    }

    public function createSupplier(array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Supplier name is required.');
        }

        DB::table('suppliers')->insert([
            'id' => (string) Str::uuid(),
            'name' => mb_substr($name, 0, 255),
            'contact_name' => $this->trimmed($input['contact_name'] ?? null, 255),
            'phone' => $this->trimmed($input['phone'] ?? null, 32),
            'note' => $this->trimmed($input['note'] ?? null, 512),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function createPurchaseOrder(array $input, $actorId): void
    {
        $supplierId = trim((string) ($input['supplier_id'] ?? ''));
        if ($supplierId === '') {
            throw new \InvalidArgumentException('supplier_id is required.');
        }

        $items = $this->parsePoItemsInput($input['items'] ?? null, (string) ($input['items_text'] ?? ''));
        $extraCosts = $this->parseExtraCostsInput($input['extra_costs'] ?? null, (string) ($input['extra_costs_text'] ?? ''));
        $allocated = $this->allocatePoCosts($items, $extraCosts);
        $poId = (string) Str::uuid();
        $note = $this->trimmed($input['note'] ?? null, 512);

        DB::transaction(function () use ($supplierId, $items, $extraCosts, $allocated, $poId, $note, $actorId) {
            if (! DB::table('suppliers')->where('id', $supplierId)->exists()) {
                throw new \RuntimeException('Supplier not found.');
            }

            $productIds = array_values(array_unique(array_map(function ($item) {
                return $item['product_id'];
            }, $items)));
            if ($productIds && DB::table('products')->whereIn('id', $productIds)->count() !== count($productIds)) {
                throw new \RuntimeException('One or more products were not found.');
            }

            DB::table('purchase_orders')->insert([
                'id' => $poId,
                'supplier_id' => $supplierId,
                'status' => 'draft',
                'note' => $note,
                'subtotal_amount' => $allocated['subtotal_amount'],
                'extra_cost_amount' => $allocated['extra_cost_amount'],
                'total_amount' => $allocated['total_amount'],
                'created_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($allocated['items'] as $item) {
                DB::table('purchase_order_items')->insert([
                    'id' => (string) Str::uuid(),
                    'purchase_order_id' => $poId,
                    'product_id' => $item['product_id'],
                    'ordered_qty' => $item['ordered_qty'],
                    'received_qty' => 0,
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['line_total'],
                    'allocated_extra_cost' => $item['allocated_extra_cost'],
                    'effective_unit_cost' => $item['effective_unit_cost'],
                    'effective_line_total' => $item['effective_line_total'],
                    'created_at' => now(),
                ]);
            }

            foreach ($extraCosts as $index => $cost) {
                DB::table('purchase_order_extra_costs')->insert([
                    'id' => (string) Str::uuid(),
                    'purchase_order_id' => $poId,
                    'cost_name' => $cost['cost_name'],
                    'amount' => $cost['amount'],
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function updatePurchaseOrderStatus(string $poId, string $nextStatus): void
    {
        $allowedStatuses = ['draft', 'approved', 'partially_received', 'received', 'canceled'];
        $transitions = [
            'draft' => ['approved', 'canceled'],
            'approved' => ['partially_received', 'received', 'canceled'],
            'partially_received' => ['received'],
            'received' => [],
            'canceled' => [],
        ];

        if (! in_array($nextStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid purchase order status.');
        }

        DB::transaction(function () use ($poId, $nextStatus, $transitions) {
            $po = DB::table('purchase_orders')
                ->select('id', 'status')
                ->where('id', $poId)
                ->lockForUpdate()
                ->first();

            if (! $po) {
                throw new \RuntimeException('Purchase order not found.');
            }

            $currentStatus = strtolower((string) $po->status);
            if ($currentStatus === $nextStatus) {
                return;
            }

            $allowed = $transitions[$currentStatus] ?? [];
            if (! in_array($nextStatus, $allowed, true)) {
                throw new \InvalidArgumentException('Invalid status transition: '.$currentStatus.' -> '.$nextStatus.'.');
            }

            DB::table('purchase_orders')->where('id', $poId)->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);
        });
    }

    public function receivePurchaseOrder(string $poId, array $input, $actorId): void
    {
        $receiptItems = $this->parseReceiptItemsInput($input['items'] ?? null, (string) ($input['receipt_items_text'] ?? ''));
        $note = $this->trimmed($input['note'] ?? null, 512);

        DB::transaction(function () use ($poId, $receiptItems, $note, $actorId) {
            $po = DB::table('purchase_orders')
                ->select('id', 'status')
                ->where('id', $poId)
                ->lockForUpdate()
                ->first();

            if (! $po) {
                throw new \RuntimeException('Purchase order not found.');
            }

            $poStatus = strtolower((string) $po->status);
            if (! in_array($poStatus, ['approved', 'partially_received'], true)) {
                throw new \InvalidArgumentException('Only approved purchase orders can receive stock.');
            }

            $poLines = DB::table('purchase_order_items')
                ->select('id', 'product_id', 'ordered_qty', 'received_qty', 'unit_cost', 'effective_unit_cost')
                ->where('purchase_order_id', $poId)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $receiptId = (string) Str::uuid();
            DB::table('goods_receipts')->insert([
                'id' => $receiptId,
                'purchase_order_id' => $poId,
                'note' => $note,
                'received_by_user_id' => $actorId,
                'created_at' => now(),
            ]);

            foreach ($receiptItems as $raw) {
                $poItem = $poLines->get($raw['purchase_order_item_id']);
                if (! $poItem) {
                    throw new \RuntimeException('Purchase order item not found.');
                }

                $remaining = ((int) $poItem->ordered_qty) - ((int) $poItem->received_qty);
                if ($raw['received_qty'] > $remaining) {
                    throw new \InvalidArgumentException('Receive quantity exceeds remaining quantity for product #'.$poItem->product_id.'.');
                }

                $this->ensureInventoryRow((int) $poItem->product_id);

                $inventory = DB::table('product_inventory')
                    ->select('qty_on_hand', 'avg_unit_cost')
                    ->where('product_id', $poItem->product_id)
                    ->lockForUpdate()
                    ->first();

                $currentQty = (int) ($inventory->qty_on_hand ?? 0);
                $currentAvg = (float) ($inventory->avg_unit_cost ?? 0);
                $unitCost = (float) ($poItem->effective_unit_cost ?: $poItem->unit_cost ?: 0);
                $nextQty = $currentQty + $raw['received_qty'];
                $nextAvg = $nextQty > 0
                    ? round((($currentQty * $currentAvg) + ($raw['received_qty'] * $unitCost)) / $nextQty, 2)
                    : 0;

                DB::table('product_inventory')->where('product_id', $poItem->product_id)->update([
                    'qty_on_hand' => $nextQty,
                    'avg_unit_cost' => $nextAvg,
                    'last_unit_cost' => $unitCost,
                    'updated_at' => now(),
                ]);

                DB::table('purchase_order_items')->where('id', $poItem->id)->update([
                    'received_qty' => DB::raw('received_qty + '.$raw['received_qty']),
                ]);

                DB::table('goods_receipt_items')->insert([
                    'id' => (string) Str::uuid(),
                    'goods_receipt_id' => $receiptId,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'received_qty' => $raw['received_qty'],
                    'unit_cost' => $unitCost,
                    'line_total' => round($raw['received_qty'] * $unitCost, 2),
                    'created_at' => now(),
                ]);

                DB::table('inventory_movements')->insert([
                    'id' => (string) Str::uuid(),
                    'product_id' => $poItem->product_id,
                    'movement_type' => 'procurement_receive',
                    'quantity' => $raw['received_qty'],
                    'note' => 'PO '.$poId.' receipt',
                    'created_by_user_id' => $actorId,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $receiptId,
                    'created_at' => now(),
                ]);
            }

            $totals = DB::table('purchase_order_items')
                ->where('purchase_order_id', $poId)
                ->selectRaw('COALESCE(SUM(ordered_qty), 0) as ordered_total, COALESCE(SUM(received_qty), 0) as received_total')
                ->first();

            $orderedTotal = (int) ($totals->ordered_total ?? 0);
            $receivedTotal = (int) ($totals->received_total ?? 0);
            $nextStatus = $receivedTotal >= $orderedTotal ? 'received' : 'partially_received';

            DB::table('purchase_orders')->where('id', $poId)->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);
        });
    }

    protected function ensureInventoryRow(int $productId): void
    {
        if (DB::table('product_inventory')->where('product_id', $productId)->exists()) {
            return;
        }

        DB::table('product_inventory')->insert([
            'product_id' => $productId,
            'qty_on_hand' => 0,
            'low_stock_threshold' => 5,
            'avg_unit_cost' => 0,
            'last_unit_cost' => 0,
            'updated_at' => now(),
        ]);
    }

    protected function parsePoItems(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $items = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 3) {
                throw new \InvalidArgumentException('Each PO line must be product_id, ordered_qty, unit_cost.');
            }

            $productId = (int) $parts[0];
            $orderedQty = (int) $parts[1];
            $unitCost = (float) $parts[2];

            if ($productId < 0) {
                throw new \InvalidArgumentException('product_id must be zero or greater.');
            }
            if ($orderedQty <= 0) {
                throw new \InvalidArgumentException('ordered_qty must be a positive integer.');
            }
            if ($unitCost < 0) {
                throw new \InvalidArgumentException('unit_cost must be zero or greater.');
            }

            $items[] = [
                'product_id' => $productId,
                'ordered_qty' => $orderedQty,
                'unit_cost' => round($unitCost, 2),
                'line_total' => round($orderedQty * $unitCost, 2),
            ];
        }

        if (! $items) {
            throw new \InvalidArgumentException('At least one PO line is required.');
        }

        return $items;
    }

    protected function parsePoItemsInput($structured, string $raw): array
    {
        if (is_array($structured)) {
            $items = [];

            foreach ($structured as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $productId = (int) ($item['product_id'] ?? 0);
                $orderedQty = (int) ($item['ordered_qty'] ?? 0);
                $unitCost = round((float) ($item['unit_cost'] ?? 0), 2);

                if ($productId < 0) {
                    throw new \InvalidArgumentException('product_id must be zero or greater.');
                }
                if ($orderedQty <= 0) {
                    throw new \InvalidArgumentException('ordered_qty must be a positive integer.');
                }
                if ($unitCost < 0) {
                    throw new \InvalidArgumentException('unit_cost must be zero or greater.');
                }

                $items[] = [
                    'product_id' => $productId,
                    'ordered_qty' => $orderedQty,
                    'unit_cost' => $unitCost,
                    'line_total' => round($orderedQty * $unitCost, 2),
                ];
            }

            if (! $items) {
                throw new \InvalidArgumentException('At least one PO line is required.');
            }

            return $items;
        }

        return $this->parsePoItems($raw);
    }

    protected function parseExtraCosts(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $items = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 2) {
                throw new \InvalidArgumentException('Each extra cost line must be cost_name, amount.');
            }

            $costName = mb_substr($parts[0], 0, 255);
            $amount = (float) $parts[1];
            if ($costName === '') {
                throw new \InvalidArgumentException('cost_name is required.');
            }
            if ($amount < 0) {
                throw new \InvalidArgumentException('extra cost amount must be zero or greater.');
            }

            $items[] = [
                'cost_name' => $costName,
                'amount' => round($amount, 2),
            ];
        }

        return $items;
    }

    protected function parseExtraCostsInput($structured, string $raw): array
    {
        if (is_array($structured)) {
            $items = [];

            foreach ($structured as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $costName = mb_substr(trim((string) ($item['cost_name'] ?? '')), 0, 255);
                $amountRaw = $item['amount'] ?? null;
                $amountText = trim((string) $amountRaw);

                if ($costName === '' && $amountText === '') {
                    continue;
                }
                if ($costName === '') {
                    throw new \InvalidArgumentException('cost_name is required.');
                }
                if ($amountText === '') {
                    throw new \InvalidArgumentException('extra cost amount is required.');
                }

                $amount = round((float) $amountRaw, 2);
                if ($amount < 0) {
                    throw new \InvalidArgumentException('extra cost amount must be zero or greater.');
                }

                $items[] = [
                    'cost_name' => $costName,
                    'amount' => $amount,
                ];
            }

            return $items;
        }

        return $this->parseExtraCosts($raw);
    }

    protected function parseReceiptItems(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $items = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 2) {
                throw new \InvalidArgumentException('Each receipt line must be purchase_order_item_id, received_qty.');
            }

            $poItemId = $parts[0];
            $receivedQty = (int) $parts[1];

            if ($poItemId === '') {
                throw new \InvalidArgumentException('purchase_order_item_id is required.');
            }
            if ($receivedQty <= 0) {
                throw new \InvalidArgumentException('received_qty must be a positive integer.');
            }

            $items[] = [
                'purchase_order_item_id' => $poItemId,
                'received_qty' => $receivedQty,
            ];
        }

        if (! $items) {
            throw new \InvalidArgumentException('At least one receipt line is required.');
        }

        return $items;
    }

    protected function parseReceiptItemsInput($structured, string $raw): array
    {
        if (is_array($structured)) {
            $items = [];

            foreach ($structured as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $poItemId = trim((string) ($item['purchase_order_item_id'] ?? ''));
                $receivedQty = (int) ($item['received_qty'] ?? 0);

                if ($poItemId === '' || $receivedQty <= 0) {
                    continue;
                }

                $items[] = [
                    'purchase_order_item_id' => $poItemId,
                    'received_qty' => $receivedQty,
                ];
            }

            if (! $items) {
                throw new \InvalidArgumentException('At least one receipt line is required.');
            }

            return $items;
        }

        return $this->parseReceiptItems($raw);
    }

    protected function allocatePoCosts(array $items, array $extraCosts): array
    {
        $subtotalCents = 0;
        foreach ($items as $item) {
            $subtotalCents += (int) round($item['line_total'] * 100);
        }

        $extraCostCents = 0;
        foreach ($extraCosts as $item) {
            $extraCostCents += (int) round($item['amount'] * 100);
        }

        if ($extraCostCents > 0 && $subtotalCents <= 0) {
            throw new \InvalidArgumentException('Extra costs require a positive goods subtotal.');
        }

        $remainingExtraCostCents = $extraCostCents;
        $allocatedItems = [];

        foreach ($items as $index => $item) {
            $lineTotalCents = (int) round($item['line_total'] * 100);
            $allocatedExtraCostCents = 0;

            if ($extraCostCents > 0) {
                if ($index === count($items) - 1) {
                    $allocatedExtraCostCents = $remainingExtraCostCents;
                } else {
                    $allocatedExtraCostCents = (int) round(($extraCostCents * $lineTotalCents) / $subtotalCents);
                    if ($allocatedExtraCostCents > $remainingExtraCostCents) {
                        $allocatedExtraCostCents = $remainingExtraCostCents;
                    }
                }
                $remainingExtraCostCents -= $allocatedExtraCostCents;
            }

            $effectiveLineTotalCents = $lineTotalCents + $allocatedExtraCostCents;
            $allocatedItems[] = [
                'product_id' => $item['product_id'],
                'ordered_qty' => $item['ordered_qty'],
                'unit_cost' => $item['unit_cost'],
                'line_total' => $item['line_total'],
                'allocated_extra_cost' => round($allocatedExtraCostCents / 100, 2),
                'effective_line_total' => round($effectiveLineTotalCents / 100, 2),
                'effective_unit_cost' => $item['ordered_qty'] > 0 ? round(($effectiveLineTotalCents / 100) / $item['ordered_qty'], 4) : 0,
            ];
        }

        return [
            'items' => $allocatedItems,
            'subtotal_amount' => round($subtotalCents / 100, 2),
            'extra_cost_amount' => round($extraCostCents / 100, 2),
            'total_amount' => round(($subtotalCents + $extraCostCents) / 100, 2),
        ];
    }

    protected function trimmed($value, int $limit): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    protected function countTable($table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }
}
