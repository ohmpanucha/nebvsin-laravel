<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontCheckoutService
{
    public function createOrder(array $authUser, array $cartItems, array $address): string
    {
        if (! $cartItems) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        foreach (['full_name', 'phone', 'address_line1', 'district', 'province', 'postal_code'] as $field) {
            if (trim((string) ($address[$field] ?? '')) === '') {
                throw new \InvalidArgumentException('Address is incomplete.');
            }
        }

        $orderId = (string) Str::uuid();

        DB::transaction(function () use ($authUser, $cartItems, $address, $orderId) {
            $total = 0;
            $hasLine = false;

            DB::table('orders')->insert([
                'id' => $orderId,
                'user_id' => $authUser['id'],
                'user_email' => $authUser['email'],
                'currency' => 'THB',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_addresses')->insert([
                'id' => (string) Str::uuid(),
                'order_id' => $orderId,
                'user_id' => $authUser['id'],
                'full_name' => trim((string) $address['full_name']),
                'phone' => trim((string) $address['phone']),
                'address_line1' => trim((string) $address['address_line1']),
                'address_line2' => $this->nullableString($address['address_line2'] ?? null),
                'district' => trim((string) $address['district']),
                'province' => trim((string) $address['province']),
                'postal_code' => trim((string) $address['postal_code']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($cartItems as $item) {
                $product = DB::table('products')
                    ->select('id', 'name', 'image', 'price_thb')
                    ->where('id', (int) ($item['id'] ?? 0))
                    ->first();

                if (! $product) {
                    continue;
                }

                $hasLine = true;
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $lineTotal = ((int) $product->price_thb) * $qty;
                $total += $lineTotal;

                DB::table('order_items')->insert([
                    'id' => (string) Str::uuid(),
                    'order_id' => $orderId,
                    'product_id' => (string) $product->id,
                    'name' => $product->name,
                    'image' => $product->image,
                    'size' => strtoupper(trim((string) ($item['size'] ?? ''))),
                    'quantity' => $qty,
                    'unit_amount' => (int) $product->price_thb,
                    'line_total' => $lineTotal,
                ]);
            }

            if (! $hasLine) {
                throw new \RuntimeException('Cart is empty or invalid.');
            }

            DB::table('orders')->where('id', $orderId)->update([
                'total_amount' => $total,
                'updated_at' => now(),
            ]);
        });

        return $orderId;
    }

    protected function nullableString($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
