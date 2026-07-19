<?php

namespace App\Support\Admin;

use App\Support\ProductTierConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductsAdminService
{
    public function summary(): array
    {
        return [
            ['label' => 'Products', 'value' => $this->countTable('products')],
            ['label' => 'Public', 'value' => $this->countWhere('products', 'is_public', 1)],
            ['label' => 'Coming Soon', 'value' => $this->countWhere('products', 'coming_soon', 1)],
        ];
    }

    public function listProducts(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $hasTier = Schema::hasColumn('products', 'tier');

        $products = DB::table('products as p')
            ->leftJoin('order_items as oi', DB::raw('CAST(oi.product_id AS UNSIGNED)'), '=', 'p.id')
            ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
            ->select(array_filter([
                'p.id',
                'p.name',
                'p.price_thb',
                $hasTier ? 'p.tier' : null,
                'p.image',
                'p.alt',
                'p.description',
                'p.sort_order',
                'p.limited_qty',
                'p.is_public',
                'p.coming_soon',
                DB::raw("COALESCE(SUM(CASE WHEN o.status = 'paid' THEN oi.quantity ELSE 0 END), 0) AS paid_sold_qty"),
            ]))
            ->groupBy(array_filter([
                'p.id', 'p.name', 'p.price_thb', $hasTier ? 'p.tier' : null, 'p.image', 'p.alt',
                'p.description', 'p.sort_order', 'p.limited_qty', 'p.is_public', 'p.coming_soon',
            ]))
            ->orderBy('p.sort_order')
            ->orderBy('p.id')
            ->get();

        return $products->map(function ($item) use ($hasTier) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price_thb' => (int) $item->price_thb,
                'tier' => $hasTier ? ProductTierConfig::normalizeKey($item->tier) : ProductTierConfig::inferFromPrice((int) $item->price_thb),
                'image' => $item->image,
                'alt' => $item->alt,
                'description' => $item->description,
                'sort_order' => (int) $item->sort_order,
                'limited_qty' => (int) $item->limited_qty,
                'is_public' => (bool) $item->is_public,
                'coming_soon' => (bool) $item->coming_soon,
                'paid_sold_qty' => (int) $item->paid_sold_qty,
            ];
        })->all();
    }

    public function create(array $input): void
    {
        $payload = $this->normalizePayload($input, false);
        $name = $payload['name'];
        $image = $payload['image'];
        $description = $payload['description'] ?? null;

        if (Schema::hasColumn('products', 'slug')) {
            unset($payload['slug']);
        }

        $productId = (int) DB::table('products')->insertGetId($payload);

        $updates = [];
        if (Schema::hasColumn('products', 'slug')) {
            $updates['slug'] = Str::slug($name).'-'.$productId;
        }
        if (Schema::hasColumn('products', 'meta_title')) {
            $updates['meta_title'] = $name.' | NEBVSIN';
        }
        if (Schema::hasColumn('products', 'meta_description')) {
            $updates['meta_description'] = $description;
        }
        if (Schema::hasColumn('products', 'og_image')) {
            $updates['og_image'] = $image;
        }

        if ($updates) {
            DB::table('products')->where('id', $productId)->update($updates);
        }
    }

    public function update(int $productId, array $input): void
    {
        $payload = $this->normalizePayload($input, true);

        if (! $payload) {
            throw new \InvalidArgumentException('No fields to update.');
        }

        $updated = DB::table('products')->where('id', $productId)->update($payload);
        if (! $updated) {
            throw new \RuntimeException('Product not found.');
        }
    }

    public function delete(int $productId): void
    {
        DB::transaction(function () use ($productId) {
            if (Schema::hasTable('goods_receipt_items')) {
                DB::table('goods_receipt_items')->where('product_id', $productId)->delete();
            }

            if (Schema::hasTable('purchase_order_items')) {
                DB::table('purchase_order_items')->where('product_id', $productId)->delete();
            }

            $deleted = DB::table('products')->where('id', $productId)->delete();
            if (! $deleted) {
                throw new \RuntimeException('Product not found.');
            }
        });
    }

    protected function normalizePayload(array $input, bool $partial): array
    {
        $payload = [];
        $has = function ($key) use ($input) {
            return array_key_exists($key, $input);
        };

        if ($partial && $has('id')) {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                throw new \InvalidArgumentException('Product id must be a positive integer.');
            }
            $payload['id'] = $id;
        }

        if (! $partial || $has('name')) {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Product name is required.');
            }
            $payload['name'] = $name;

            if ($partial && Schema::hasColumn('products', 'slug')) {
                $payload['slug'] = Str::slug($name).'-'.((int) ($input['product_id'] ?? 0));
            }
            if ($partial && Schema::hasColumn('products', 'meta_title')) {
                $payload['meta_title'] = $name.' | NEBVSIN';
            }
        }

        if (! $partial || $has('price_thb')) {
            $price = (int) ($input['price_thb'] ?? -1);
            if ($price < 0) {
                throw new \InvalidArgumentException('price_thb must be a non-negative integer.');
            }
            $payload['price_thb'] = $price;
        }

        if ((! $partial || $has('tier')) && Schema::hasColumn('products', 'tier')) {
            $payload['tier'] = ProductTierConfig::normalizeKey($input['tier'] ?? null);
        }

        if (! $partial || $has('image')) {
            $image = trim((string) ($input['image'] ?? ''));
            if ($image === '') {
                throw new \InvalidArgumentException('Product image is required.');
            }
            $payload['image'] = $image;
            if ($partial && Schema::hasColumn('products', 'og_image')) {
                $payload['og_image'] = $image;
            }
        }

        if (! $partial || $has('alt')) {
            $payload['alt'] = $this->nullableString($input['alt'] ?? null);
        }

        if (! $partial || $has('description')) {
            $payload['description'] = $this->nullableString($input['description'] ?? null);
            if ($partial && Schema::hasColumn('products', 'meta_description')) {
                $payload['meta_description'] = $payload['description'];
            }
        }

        if (! $partial || $has('sort_order')) {
            $payload['sort_order'] = (int) ($input['sort_order'] ?? 0);
        }

        if (! $partial || $has('limited_qty')) {
            $limitedQty = (int) ($input['limited_qty'] ?? 40);
            if ($limitedQty < 0) {
                throw new \InvalidArgumentException('limited_qty must be a non-negative integer.');
            }
            $payload['limited_qty'] = $limitedQty;
        }

        if (! $partial || $has('is_public')) {
            $payload['is_public'] = $this->toFlag($input['is_public'] ?? 0);
        }

        if (! $partial || $has('coming_soon')) {
            $payload['coming_soon'] = $this->toFlag($input['coming_soon'] ?? 0);
        }

        return $payload;
    }

    protected function nullableString($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    protected function toFlag($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
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
}
