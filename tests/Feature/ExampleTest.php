<?php

namespace Tests\Feature;

use App\Support\Admin\ProductsAdminService;
use App\Support\LegacyAuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_renders()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('LAND OF SIN');
    }

    public function test_legacy_numeric_product_url_redirects_to_slug()
    {
        $response = $this->get('/products/1');

        $response->assertRedirect('/products/raven-unit-tee-1?lang=en');
    }

    public function test_product_page_renders_structured_data()
    {
        $response = $this->get('/products/raven-unit-tee-1');

        $response->assertStatus(200);
        $response->assertSee('"@type":"Product"', false);
    }

    public function test_sitemap_renders_xml()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<urlset', false);
    }

    public function test_robots_lists_sitemap()
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertSee('Sitemap: http://localhost:8000/sitemap.xml');
    }

    public function test_process_page_renders()
    {
        $response = $this->get('/process');

        $response->assertStatus(200);
        $response->assertSee('PRE-ORDER PROCESS');
    }

    public function test_login_page_is_noindex()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('content="noindex, nofollow"', false);
    }

    public function test_product_can_be_added_to_cart()
    {
        $response = $this->post('/cart/items', [
            'product_id' => 1,
            'size' => 'L',
            'quantity' => 1,
        ]);

        $response->assertRedirect('/?cart=open');
        $response->assertSessionHas('cart_status', 'Added to cart.');
        $this->assertNotEmpty(session('storefront_cart'));
    }

    public function test_buy_now_redirects_to_login_for_guest()
    {
        $response = $this->post('/cart/items', [
            'product_id' => 1,
            'size' => 'L',
            'quantity' => 1,
            'intent' => 'buy_now',
        ]);

        $response->assertRedirect('/login?lang=en&next=http%3A%2F%2Flocalhost%3A8000%2Fcheckout%3Flang%3Den');
    }

    public function test_admin_route_redirects_guest_to_login()
    {
        $response = $this->get('/admin/orders');

        $response->assertRedirect('/login?next=%2Fadmin%2Forders');
    }

    public function test_admin_route_redirects_non_admin_to_home()
    {
        $this->mockLegacyAuth([
            'token' => 'user-token',
            'user' => [
                'id' => 'usr_001',
                'email' => 'user@nebvsin.local',
                'role' => 'user',
                'display_name' => 'Mock User',
            ],
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'user-token'])->get('/admin/orders');

        $response->assertRedirect('/?lang=en');
    }

    public function test_all_admin_pages_render_for_admin()
    {
        $this->useSqliteMemory();

        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        foreach ([
            '/admin/procurement',
            '/admin/inventory',
            '/admin/products',
            '/admin/customers',
            '/admin/payments',
            '/admin/shipping',
            '/admin/orders',
        ] as $path) {
            $response = $this->withSession(['legacy_auth_token' => 'admin-token'])->get($path);

            $response->assertStatus(200);
            $response->assertSee('ADMIN');
        }
    }

    public function test_admin_products_table_includes_product_image_preview()
    {
        $this->useSqliteMemory();

        Schema::create('products', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('price_thb');
            $table->string('image')->nullable();
            $table->string('alt')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('limited_qty')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('coming_soon')->default(false);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('status')->default('pending');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('product_id')->nullable();
            $table->integer('quantity')->default(1);
        });

        DB::table('products')->insert([
            'id' => 1,
            'name' => 'Raven Unit Tee',
            'price_thb' => 1890,
            'image' => '/uploads/products/DROP01.png',
            'alt' => 'Raven Unit Tee product image',
            'description' => 'Test product',
            'sort_order' => 1,
            'limited_qty' => 40,
            'is_public' => true,
            'coming_soon' => false,
        ]);

        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee('<th>IMAGE</th>', false);
        $response->assertSee('class="admin-product-table-preview"', false);
        $response->assertSee('src="/uploads/products/DROP01.png"', false);
    }

    public function test_admin_can_update_customer_status()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email');
            $table->string('role')->default('user');
            $table->string('display_name')->nullable();
            $table->string('account_status')->default('active');
            $table->string('suspended_reason')->nullable();
            $table->timestamp('account_status_updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->string('user_id')->primary();
            $table->string('phone')->nullable();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('status')->nullable();
            $table->integer('total_amount')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('product_id')->nullable();
            $table->integer('quantity')->default(1);
        });

        DB::table('users')->insert([
            'id' => 'usr_001',
            'email' => 'user@nebvsin.local',
            'role' => 'user',
            'display_name' => 'User One',
            'account_status' => 'active',
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->patch('/admin/customers/usr_001/status', [
                'account_status' => 'suspended',
                'suspended_reason' => 'Chargeback review',
            ]);

        $response->assertSessionHas('admin_status', 'Customer updated.');
        $this->assertSame('suspended', DB::table('users')->where('id', 'usr_001')->value('account_status'));
    }

    public function test_admin_can_update_payment_status()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $this->createPaymentShippingTables();

        DB::table('orders')->insert([
            'id' => 'ord_001',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 1200,
            'currency' => 'THB',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->patch('/admin/payments/ord_001/status', [
                'status' => 'paid',
                'note' => 'verified',
            ]);

        $response->assertSessionHas('admin_status', 'Payment updated.');
        $this->assertSame('paid', DB::table('orders')->where('id', 'ord_001')->value('status'));
        $this->assertSame('pending_fulfillment', DB::table('orders')->where('id', 'ord_001')->value('shipping_status'));
        $this->assertSame(1, DB::table('order_payment_events')->count());
    }

    public function test_admin_can_update_shipping_status()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $this->createPaymentShippingTables();

        DB::table('orders')->insert([
            'id' => 'ord_002',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 2200,
            'currency' => 'THB',
            'status' => 'paid',
            'shipping_status' => 'pending_fulfillment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->patch('/admin/shipping/ord_002', [
                'shipping_status' => 'shipped',
                'tracking_number' => 'TH123456',
                'shipping_carrier' => 'Flash',
                'note' => 'packed',
            ]);

        $response->assertSessionHas('admin_status', 'Shipping updated.');
        $this->assertSame('shipped', DB::table('orders')->where('id', 'ord_002')->value('shipping_status'));
        $this->assertSame('TH123456', DB::table('orders')->where('id', 'ord_002')->value('tracking_number'));
        $this->assertSame(1, DB::table('order_shipping_events')->count());
    }

    public function test_admin_orders_page_renders_detail_payload()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $this->createPaymentShippingTables();

        DB::table('orders')->insert([
            'id' => 'ord_master_1',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 1890,
            'currency' => 'THB',
            'status' => 'awaiting_review',
            'shipping_status' => 'pending_fulfillment',
            'payment_slip_uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_addresses')->insert([
            'id' => 'addr_master_1',
            'order_id' => 'ord_master_1',
            'user_id' => 'usr_001',
            'full_name' => 'Panucha',
            'phone' => '0811111111',
            'address_line1' => '123 Raven Alley',
            'district' => 'Chatuchak',
            'province' => 'Bangkok',
            'postal_code' => '10900',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'id' => 'line_master_1',
            'order_id' => 'ord_master_1',
            'product_id' => '1',
            'name' => 'Raven Unit Tee',
            'image' => '/uploads/products/DROP01.png',
            'size' => 'L',
            'quantity' => 1,
            'unit_amount' => 1890,
            'line_total' => 1890,
        ]);

        DB::table('order_payment_events')->insert([
            'id' => 'evt_pay_master_1',
            'order_id' => 'ord_master_1',
            'from_status' => 'pending',
            'to_status' => 'awaiting_review',
            'actor_id' => 'usr_001',
            'actor_role' => 'user',
            'note' => 'uploaded slip',
            'created_at' => now(),
        ]);

        DB::table('order_shipping_events')->insert([
            'id' => 'evt_ship_master_1',
            'order_id' => 'ord_master_1',
            'from_status' => 'pending_fulfillment',
            'to_status' => 'processing',
            'tracking_number' => null,
            'shipping_carrier' => null,
            'actor_id' => 'adm_001',
            'actor_role' => 'admin',
            'note' => 'packing started',
            'created_at' => now(),
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])->get('/admin/orders');

        $response->assertStatus(200);
        $response->assertSee('data-admin-order-detail-open', false);
        $response->assertSee('id="admin-order-detail-data"', false);
        $response->assertSee('Raven Unit Tee');
        $response->assertSee('uploaded slip');
        $response->assertSee('packing started');
        $response->assertSee('Panucha');
    }

    public function test_admin_shipping_page_renders_detail_payload()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $this->createPaymentShippingTables();

        DB::table('orders')->insert([
            'id' => 'ord_ship_1',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 2200,
            'currency' => 'THB',
            'status' => 'paid',
            'shipping_status' => 'processing',
            'tracking_number' => 'TH998877',
            'shipping_carrier' => 'Flash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_addresses')->insert([
            'id' => 'addr_ship_1',
            'order_id' => 'ord_ship_1',
            'user_id' => 'usr_001',
            'full_name' => 'Panucha',
            'phone' => '0811111111',
            'address_line1' => '123 Raven Alley',
            'district' => 'Chatuchak',
            'province' => 'Bangkok',
            'postal_code' => '10900',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'id' => 'line_ship_1',
            'order_id' => 'ord_ship_1',
            'product_id' => '1',
            'name' => 'Raven Unit Tee',
            'image' => '/uploads/products/DROP01.png',
            'size' => 'L',
            'quantity' => 1,
            'unit_amount' => 2200,
            'line_total' => 2200,
        ]);

        DB::table('order_shipping_events')->insert([
            'id' => 'evt_ship_1',
            'order_id' => 'ord_ship_1',
            'from_status' => 'pending_fulfillment',
            'to_status' => 'processing',
            'tracking_number' => 'TH998877',
            'shipping_carrier' => 'Flash',
            'actor_id' => 'adm_001',
            'actor_role' => 'admin',
            'note' => 'packing started',
            'created_at' => now(),
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])->get('/admin/shipping');

        $response->assertStatus(200);
        $response->assertSee('Missing Tracking');
        $response->assertSee('data-admin-shipping-detail-open', false);
        $response->assertSee('id="admin-shipping-detail-data"', false);
        $response->assertSee('Panucha');
        $response->assertSee('Raven Unit Tee');
        $response->assertSee('packing started');
    }

    public function test_admin_can_run_procurement_flow()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $this->createProcurementTables();

        DB::table('products')->insert([
            'id' => 1,
            'name' => 'Raven Unit Tee',
            'sort_order' => 1,
        ]);

        $createSupplier = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->post('/admin/procurement/suppliers', [
                'name' => 'Supplier One',
                'contact_name' => 'Ohm',
                'phone' => '0812345678',
            ]);

        $createSupplier->assertSessionHas('admin_status', 'Supplier created.');
        $supplierId = DB::table('suppliers')->value('id');
        $this->assertNotEmpty($supplierId);

        $createPo = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->post('/admin/procurement/purchase-orders', [
                'supplier_id' => $supplierId,
                'items_text' => "1, 10, 120.50",
                'extra_costs_text' => "Shipping, 50",
                'note' => 'first po',
            ]);

        $createPo->assertSessionHas('admin_status', 'Purchase order created.');
        $poId = DB::table('purchase_orders')->value('id');
        $poItemId = DB::table('purchase_order_items')->value('id');
        $this->assertNotEmpty($poId);
        $this->assertSame('draft', DB::table('purchase_orders')->where('id', $poId)->value('status'));

        $approvePo = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->patch('/admin/procurement/purchase-orders/'.$poId.'/status', [
                'status' => 'approved',
            ]);

        $approvePo->assertSessionHas('admin_status', 'Purchase order updated.');
        $this->assertSame('approved', DB::table('purchase_orders')->where('id', $poId)->value('status'));

        $receivePo = $this->withSession(['legacy_auth_token' => 'admin-token'])
            ->post('/admin/procurement/purchase-orders/'.$poId.'/receive', [
                'receipt_items_text' => $poItemId.', 10',
                'note' => 'full receipt',
            ]);

        $receivePo->assertSessionHas('admin_status', 'Goods receipt posted.');
        $this->assertSame('received', DB::table('purchase_orders')->where('id', $poId)->value('status'));
        $this->assertSame(10, (int) DB::table('product_inventory')->where('product_id', 1)->value('qty_on_hand'));
        $this->assertSame(1, DB::table('goods_receipts')->count());
        $this->assertSame(1, DB::table('inventory_movements')->count());
    }

    public function test_product_delete_removes_goods_receipt_items_first()
    {
        $this->useSqliteMemory();
        $this->createProcurementTables();

        DB::table('products')->insert(['id' => 1, 'name' => 'Raven Unit Tee']);
        DB::table('suppliers')->insert(['id' => 'supplier-1', 'name' => 'Supplier One']);
        DB::table('purchase_orders')->insert(['id' => 'po-1', 'supplier_id' => 'supplier-1']);
        DB::table('purchase_order_items')->insert([
            'id' => 'po-item-1',
            'purchase_order_id' => 'po-1',
            'product_id' => 1,
            'ordered_qty' => 3,
        ]);
        DB::table('goods_receipts')->insert(['id' => 'gr-1', 'purchase_order_id' => 'po-1']);
        DB::table('goods_receipt_items')->insert([
            'id' => 'gr-item-1',
            'goods_receipt_id' => 'gr-1',
            'purchase_order_item_id' => 'po-item-1',
            'product_id' => 1,
            'received_qty' => 3,
        ]);

        app(ProductsAdminService::class)->delete(1);

        $this->assertSame(0, DB::table('goods_receipt_items')->where('product_id', 1)->count());
        $this->assertSame(0, DB::table('purchase_order_items')->where('product_id', 1)->count());
        $this->assertFalse(DB::table('products')->where('id', 1)->exists());
    }

    public function test_checkout_can_create_order_from_cart()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'user-token',
            'user' => [
                'id' => 'usr_001',
                'email' => 'user@nebvsin.local',
                'role' => 'user',
                'display_name' => 'Mock User',
            ],
        ]);

        Schema::create('products', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->string('image')->nullable();
            $table->integer('price_thb')->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('user_email');
            $table->integer('total_amount')->default(0);
            $table->string('currency')->default('THB');
            $table->string('status')->default('pending');
            $table->string('payment_slip_mime')->nullable();
            $table->string('payment_slip_filename')->nullable();
            $table->text('payment_slip_data')->nullable();
            $table->timestamp('payment_slip_uploaded_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('user_id');
            $table->string('full_name');
            $table->string('phone');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('district');
            $table->string('province');
            $table->string('postal_code');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('product_id');
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_amount')->default(0);
            $table->integer('line_total')->default(0);
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->string('user_id')->primary();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
        });

        DB::table('products')->insert([
            'id' => 1,
            'name' => 'Raven Unit Tee',
            'image' => '/uploads/products/DROP01.png',
            'price_thb' => 1500,
        ]);

        $session = [
            'legacy_auth_token' => 'user-token',
            'storefront_cart' => [[
                'key' => '1:L',
                'id' => 1,
                'name' => 'Raven Unit Tee',
                'price_thb' => 1500,
                'price_label' => '1,500 THB',
                'image' => '/uploads/products/DROP01.png',
                'image_url' => '/uploads/products/DROP01.png',
                'size' => 'L',
                'qty' => 2,
            ]],
        ];

        $response = $this->withSession($session)->post('/checkout', [
            'full_name' => 'User One',
            'phone' => '0812345678',
            'address_line1' => '123 Test Road',
            'address_line2' => '',
            'district' => 'Muang',
            'province' => 'Bangkok',
            'postal_code' => '10100',
        ]);

        $orderId = DB::table('orders')->value('id');

        $response->assertRedirect('/checkout/payment/'.$orderId.'?lang=en');
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('order_addresses')->count());
        $this->assertSame(1, DB::table('order_items')->count());
        $this->assertSame(3000, (int) DB::table('orders')->value('total_amount'));
    }

    public function test_payment_slip_can_be_uploaded_from_purchase_history()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'user-token',
            'user' => [
                'id' => 'usr_001',
                'email' => 'user@nebvsin.local',
                'role' => 'user',
                'display_name' => 'Mock User',
            ],
        ]);

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('user_email')->nullable();
            $table->integer('total_amount')->default(0);
            $table->string('currency')->default('THB');
            $table->string('status')->default('pending');
            $table->string('shipping_status')->nullable();
            $table->string('payment_slip_mime')->nullable();
            $table->string('payment_slip_filename')->nullable();
            $table->text('payment_slip_data')->nullable();
            $table->timestamp('payment_slip_uploaded_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('product_id')->nullable();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_amount')->default(0);
            $table->integer('line_total')->default(0);
        });

        DB::table('orders')->insert([
            'id' => 'ord_900',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 1500,
            'currency' => 'THB',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'id' => 'line_1',
            'order_id' => 'ord_900',
            'product_id' => '1',
            'name' => 'Raven Unit Tee',
            'image' => '/uploads/products/DROP01.png',
            'size' => 'L',
            'quantity' => 1,
            'unit_amount' => 1500,
            'line_total' => 1500,
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'user-token'])->post(
            '/purchase-history/ord_900/slip',
            [
                'slip' => UploadedFile::fake()->image('slip.png', 400, 600),
            ]
        );

        $response->assertRedirect('/purchase-history?lang=en');
        $this->assertSame('awaiting_review', DB::table('orders')->where('id', 'ord_900')->value('status'));
        $this->assertNotNull(DB::table('orders')->where('id', 'ord_900')->value('payment_slip_uploaded_at'));
        $this->assertStringStartsWith(
            'data:image/',
            (string) DB::table('orders')->where('id', 'ord_900')->value('payment_slip_data')
        );
    }

    public function test_payment_page_renders_promptpay_qr()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'user-token',
            'user' => [
                'id' => 'usr_001',
                'email' => 'user@nebvsin.local',
                'role' => 'user',
                'display_name' => 'Mock User',
            ],
        ]);

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('user_email')->nullable();
            $table->integer('total_amount')->default(0);
            $table->string('currency')->default('THB');
            $table->string('status')->default('pending');
            $table->string('shipping_status')->nullable();
            $table->text('payment_slip_data')->nullable();
            $table->timestamp('payment_slip_uploaded_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('product_id')->nullable();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_amount')->default(0);
            $table->integer('line_total')->default(0);
        });

        DB::table('orders')->insert([
            'id' => 'ord_qr_1',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 1890,
            'currency' => 'THB',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'id' => 'line_qr_1',
            'order_id' => 'ord_qr_1',
            'product_id' => '1',
            'name' => 'Raven Unit Tee',
            'image' => '/uploads/products/DROP01.png',
            'size' => 'L',
            'quantity' => 1,
            'unit_amount' => 1890,
            'line_total' => 1890,
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'user-token'])->get('/checkout/payment/ord_qr_1');

        $response->assertStatus(200);
        $response->assertSee('data:image/svg+xml;base64,', false);
        $response->assertSee('000201', false);
        $response->assertSee('COPY PAYLOAD');
        $response->assertSee('DOWNLOAD QR');
    }

    public function test_admin_payments_page_renders_slip_preview()
    {
        $this->useSqliteMemory();
        $this->mockLegacyAuth([
            'token' => 'admin-token',
            'user' => [
                'id' => 'adm_001',
                'email' => 'admin@nebvsin.local',
                'role' => 'admin',
                'display_name' => 'Mock Admin',
            ],
        ]);

        $this->createPaymentShippingTables();

        DB::table('orders')->insert([
            'id' => 'ord_slip_1',
            'user_id' => 'usr_001',
            'user_email' => 'user@nebvsin.local',
            'total_amount' => 2200,
            'currency' => 'THB',
            'status' => 'awaiting_review',
            'payment_slip_data' => 'data:image/png;base64,abc',
            'payment_slip_uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'id' => 'line_slip_1',
            'order_id' => 'ord_slip_1',
            'product_id' => '1',
            'name' => 'Raven Unit Tee',
            'image' => '/uploads/products/DROP01.png',
            'size' => 'L',
            'quantity' => 1,
            'unit_amount' => 2200,
            'line_total' => 2200,
        ]);

        DB::table('order_payment_events')->insert([
            'id' => 'evt_slip_1',
            'order_id' => 'ord_slip_1',
            'from_status' => 'pending',
            'to_status' => 'awaiting_review',
            'actor_id' => 'usr_001',
            'actor_role' => 'user',
            'note' => 'uploaded slip',
            'created_at' => now(),
        ]);

        $response = $this->withSession(['legacy_auth_token' => 'admin-token'])->get('/admin/payments');

        $response->assertStatus(200);
        $response->assertSee('PAYMENT SLIP');
        $response->assertSee('data:image\\/png;base64,abc', false);
        $response->assertSee('data-admin-slip-modal', false);
        $response->assertSee('data-admin-payment-detail-open', false);
        $response->assertSee('Raven Unit Tee');
        $response->assertSee('uploaded slip');
        $response->assertSee('LINE ITEMS');
        $response->assertSee('PAYMENT EVENTS');
    }

    protected function mockLegacyAuth(array $state): void
    {
        $this->app->instance(LegacyAuthService::class, new class($state) extends LegacyAuthService {
            protected $state;

            public function __construct(array $state)
            {
                $this->state = $state;
            }

            public function safeCurrentUserFromToken(?string $token): ?array
            {
                if ($token === ($this->state['token'] ?? null)) {
                    return $this->state;
                }

                return null;
            }
        });
    }

    protected function useSqliteMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
    }

    protected function createPaymentShippingTables(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->integer('total_amount')->default(0);
            $table->string('currency')->default('THB');
            $table->string('status')->default('pending');
            $table->string('shipping_status')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('payment_slip_uploaded_at')->nullable();
            $table->text('payment_slip_data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('product_id')->nullable();
            $table->string('name')->nullable();
            $table->string('image')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_amount')->default(0);
            $table->integer('line_total')->default(0);
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('user_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_payment_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('actor_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_shipping_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('tracking_number')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function createProcurementTables(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('sort_order')->default(0);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('supplier_id');
            $table->string('status')->default('draft');
            $table->string('note')->nullable();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('extra_cost_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('purchase_order_id');
            $table->integer('product_id');
            $table->integer('ordered_qty');
            $table->integer('received_qty')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('allocated_extra_cost', 12, 2)->default(0);
            $table->decimal('effective_unit_cost', 12, 4)->default(0);
            $table->decimal('effective_line_total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('purchase_order_extra_costs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('purchase_order_id');
            $table->string('cost_name');
            $table->decimal('amount', 12, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('purchase_order_id');
            $table->string('note')->nullable();
            $table->string('received_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('goods_receipt_id');
            $table->string('purchase_order_item_id');
            $table->integer('product_id');
            $table->integer('received_qty');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('product_inventory', function (Blueprint $table) {
            $table->integer('product_id')->primary();
            $table->integer('qty_on_hand')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->decimal('avg_unit_cost', 12, 2)->default(0);
            $table->decimal('last_unit_cost', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('product_id');
            $table->string('movement_type');
            $table->integer('quantity');
            $table->string('note')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
