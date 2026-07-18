<?php

namespace App\Support;

use Illuminate\Http\Request;

class StorefrontCartService
{
    protected const SESSION_KEY = 'storefront_cart';

    public function items(Request $request): array
    {
        $items = $request->session()->get(self::SESSION_KEY, []);

        return is_array($items) ? array_values($items) : [];
    }

    public function add(Request $request, array $product, string $size, int $quantity = 1): void
    {
        $quantity = max(1, $quantity);
        $items = $this->items($request);
        $key = $this->itemKey((int) $product['id'], $size);

        $found = false;
        foreach ($items as &$item) {
            if (($item['key'] ?? null) !== $key) {
                continue;
            }

            $item['qty'] = (int) ($item['qty'] ?? 1) + $quantity;
            $found = true;
            break;
        }
        unset($item);

        if (! $found) {
            $items[] = [
                'key' => $key,
                'id' => (int) $product['id'],
                'name' => $product['name'],
                'price_thb' => (int) $product['price_thb'],
                'price_label' => $product['price_label'],
                'image' => $product['image'],
                'image_url' => $product['image_url'],
                'size' => $size,
                'qty' => $quantity,
            ];
        }

        $request->session()->put(self::SESSION_KEY, $items);
    }

    public function updateQuantity(Request $request, string $key, int $quantity): void
    {
        $items = $this->items($request);

        foreach ($items as $index => $item) {
            if (($item['key'] ?? null) !== $key) {
                continue;
            }

            if ($quantity <= 0) {
                unset($items[$index]);
            } else {
                $items[$index]['qty'] = $quantity;
            }

            break;
        }

        $request->session()->put(self::SESSION_KEY, array_values($items));
    }

    public function remove(Request $request, string $key): void
    {
        $this->updateQuantity($request, $key, 0);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function totalItems(Request $request): int
    {
        return array_reduce($this->items($request), function ($sum, $item) {
            return $sum + (int) ($item['qty'] ?? 0);
        }, 0);
    }

    public function subtotal(Request $request): int
    {
        return array_reduce($this->items($request), function ($sum, $item) {
            return $sum + ((int) ($item['price_thb'] ?? 0) * (int) ($item['qty'] ?? 0));
        }, 0);
    }

    protected function itemKey(int $productId, string $size): string
    {
        return $productId.':'.strtoupper($size);
    }
}
