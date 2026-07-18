<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyReferenceSeeder extends Seeder
{
    public function run()
    {
        $this->seedUsers();
        $this->seedSupplier();
        $this->seedProducts();
    }

    protected function seedUsers()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $users = [
            [
                'id' => $this->resolveUserId('user'),
                'email' => 'user@nebvsin.local',
                'password' => '1234',
                'role' => 'user',
                'display_name' => 'Mock User',
            ],
            [
                'id' => $this->resolveUserId('admin'),
                'email' => 'admin@nebvsin.local',
                'password' => '1234',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ];

        foreach ($users as $user) {
            $payload = [
                'email' => $user['email'],
            ];

            if (Schema::hasColumn('users', 'role')) {
                $payload['role'] = $user['role'];
            }

            if (Schema::hasColumn('users', 'display_name')) {
                $payload['display_name'] = $user['display_name'];
            }

            if (Schema::hasColumn('users', 'name')) {
                $payload['name'] = $user['display_name'];
            }

            if (Schema::hasColumn('users', 'password')) {
                $payload['password'] = Hash::make($user['password']);
            }

            if (Schema::hasColumn('users', 'account_status')) {
                $payload['account_status'] = 'active';
            }

            $existing = DB::table('users')->where('email', $user['email'])->first();

            if ($existing) {
                DB::table('users')->where('email', $user['email'])->update($payload);
                continue;
            }

            $payload['id'] = $user['id'];
            DB::table('users')->insert($payload);
        }
    }

    protected function seedSupplier()
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        DB::table('suppliers')->updateOrInsert(
            ['id' => 'sup_001'],
            [
                'name' => 'Default Supplier',
                'contact_name' => 'Factory Desk',
                'phone' => '0800000000',
                'note' => 'Seed supplier for procurement',
                'updated_at' => now(),
            ]
        );
    }

    protected function seedProducts()
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $products = $this->loadProducts();

        foreach ($products as $index => $product) {
            $price = $this->toAmount(isset($product['price']) ? $product['price'] : null);
            $name = isset($product['name']) ? $product['name'] : 'Untitled Product';
            $image = isset($product['image']) ? $product['image'] : null;
            $description = isset($product['description']) ? $product['description'] : null;
            $alt = isset($product['alt']) ? $product['alt'] : null;
            $limitedQty = isset($product['limited_qty']) ? (int) $product['limited_qty'] : 40;
            $isPublic = array_key_exists('is_public', $product) ? (int) (bool) $product['is_public'] : 1;
            $comingSoon = array_key_exists('coming_soon', $product) ? (int) (bool) $product['coming_soon'] : 0;

            $payload = [
                'name' => $name,
                'price_thb' => $price,
                'image' => $image,
                'alt' => $alt,
                'description' => $description,
                'sort_order' => $index,
            ];

            if (Schema::hasColumn('products', 'limited_qty')) {
                $payload['limited_qty'] = $limitedQty;
            }

            if (Schema::hasColumn('products', 'is_public')) {
                $payload['is_public'] = $isPublic;
            }

            if (Schema::hasColumn('products', 'coming_soon')) {
                $payload['coming_soon'] = $comingSoon;
            }

            if (Schema::hasColumn('products', 'slug')) {
                $payload['slug'] = Str::slug($name);
            }

            if (Schema::hasColumn('products', 'meta_title')) {
                $payload['meta_title'] = $name.' | NEBVSIN';
            }

            if (Schema::hasColumn('products', 'meta_description')) {
                $payload['meta_description'] = $description;
            }

            if (Schema::hasColumn('products', 'og_image')) {
                $payload['og_image'] = $image;
            }

            DB::table('products')->updateOrInsert(
                ['id' => $index],
                $payload
            );

            $this->seedInventoryForProduct($index);
        }
    }

    protected function seedInventoryForProduct($productId)
    {
        if (! Schema::hasTable('product_inventory')) {
            return;
        }

        DB::table('product_inventory')->updateOrInsert(
            ['product_id' => $productId],
            [
                'qty_on_hand' => max(0, 8 - (int) $productId),
                'low_stock_threshold' => 3,
                'avg_unit_cost' => 250 + ((int) $productId * 5),
                'last_unit_cost' => 260 + ((int) $productId * 5),
                'updated_at' => now(),
            ]
        );
    }

    protected function loadProducts()
    {
        $paths = [
            base_path('../version-react/backend/data/products.json'),
            base_path('../version-react/frontend/src/data/products.json'),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $content = file_get_contents($path);
                $decoded = json_decode($content, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    protected function toAmount($priceText)
    {
        $value = preg_replace('/[^\d.]/', '', (string) $priceText);
        $number = (float) $value;

        if (! is_finite($number)) {
            return 0;
        }

        return (int) round($number);
    }

    protected function resolveUserId($role)
    {
        $type = $this->getUserIdType();

        if ($type === 'bigint' || $type === 'int' || $type === 'integer') {
            return $role === 'admin' ? 9001 : 1001;
        }

        return $role === 'admin' ? 'adm_001' : 'usr_001';
    }

    protected function getUserIdType()
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'id')) {
            return 'string';
        }

        $database = DB::getDatabaseName();
        $column = DB::selectOne(
            'SELECT DATA_TYPE AS data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, 'users', 'id']
        );

        if (! $column || empty($column->data_type)) {
            return 'string';
        }

        return strtolower($column->data_type);
    }
}
