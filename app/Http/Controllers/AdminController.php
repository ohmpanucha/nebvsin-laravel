<?php

namespace App\Http\Controllers;

use App\Support\Admin\CustomersAdminService;
use App\Support\Admin\InventoryAdminService;
use App\Support\Admin\OrdersAdminService;
use App\Support\Admin\PaymentsAdminService;
use App\Support\Admin\ProcurementAdminService;
use App\Support\Admin\ProductsAdminService;
use App\Support\Admin\ShippingAdminService;
use App\Support\StorefrontLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    protected $orders;
    protected $products;
    protected $inventory;
    protected $customers;
    protected $payments;
    protected $shipping;
    protected $procurement;

    public function __construct(
        OrdersAdminService $orders,
        ProductsAdminService $products,
        InventoryAdminService $inventory,
        CustomersAdminService $customers,
        PaymentsAdminService $payments,
        ShippingAdminService $shipping,
        ProcurementAdminService $procurement
    ) {
        $this->orders = $orders;
        $this->products = $products;
        $this->inventory = $inventory;
        $this->customers = $customers;
        $this->payments = $payments;
        $this->shipping = $shipping;
        $this->procurement = $procurement;
    }

    public function procurement(Request $request): View
    {
        return $this->renderModule($request, 'procurement');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        try {
            $this->procurement->createSupplier($request->all());
            return back()->with('admin_status', 'Supplier created.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        try {
            $this->procurement->createPurchaseOrder($request->all(), $this->actorId($request));
            return back()->with('admin_status', 'Purchase order created.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function updatePurchaseOrderStatus(Request $request, string $poId): RedirectResponse
    {
        try {
            $this->procurement->updatePurchaseOrderStatus($poId, strtolower(trim((string) $request->input('status'))));
            return back()->with('admin_status', 'Purchase order updated.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function receivePurchaseOrder(Request $request, string $poId): RedirectResponse
    {
        try {
            $this->procurement->receivePurchaseOrder($poId, $request->all(), $this->actorId($request));
            return back()->with('admin_status', 'Goods receipt posted.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function inventory(Request $request): View
    {
        return $this->renderModule($request, 'inventory');
    }

    public function products(Request $request): View
    {
        return $this->renderModule($request, 'products');
    }

    public function customers(Request $request): View
    {
        return $this->renderModule($request, 'customers');
    }

    public function payments(Request $request): View
    {
        return $this->renderModule($request, 'payments');
    }

    public function shipping(Request $request): View
    {
        return $this->renderModule($request, 'shipping');
    }

    public function orders(Request $request): View
    {
        return $this->renderModule($request, 'orders');
    }

    public function updateOrderStatus(Request $request, string $orderId): RedirectResponse
    {
        try {
            $this->orders->updateStatus($orderId, strtolower(trim((string) $request->input('status'))), $this->actorId($request));
            return back()->with('admin_status', 'Order updated.');
        } catch (\Throwable $exception) {
            return back()->with('admin_status', $exception->getMessage());
        }
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        try {
            $this->products->create($this->prepareProductInput($request, false));
            return back()->with('admin_status', 'Product created.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function updateProduct(Request $request, int $productId): RedirectResponse
    {
        try {
            $input = $this->prepareProductInput($request, true);
            $input['product_id'] = $productId;
            $this->products->update($productId, $input);
            return back()->with('admin_status', 'Product updated.');
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->with('admin_status', $exception->getMessage())
                ->with('admin_edit_product', $productId);
        }
    }

    public function deleteProduct(int $productId): RedirectResponse
    {
        try {
            $this->products->delete($productId);
            return back()->with('admin_status', 'Product deleted.');
        } catch (\Throwable $exception) {
            return back()->with('admin_status', $exception->getMessage());
        }
    }

    public function updateCustomerStatus(Request $request, $userId): RedirectResponse
    {
        try {
            $this->customers->updateStatus(
                $userId,
                strtolower(trim((string) $request->input('account_status'))),
                $this->nullableString($request->input('suspended_reason')),
                $this->actorId($request)
            );
            return back()->with('admin_status', 'Customer updated.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function updatePaymentStatus(Request $request, string $orderId): RedirectResponse
    {
        try {
            $this->payments->updateStatus(
                $orderId,
                strtolower(trim((string) $request->input('status'))),
                $this->actorId($request),
                $this->actorRole($request),
                $this->nullableString($request->input('note'))
            );
            return back()->with('admin_status', 'Payment updated.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function updateShipping(Request $request, string $orderId): RedirectResponse
    {
        try {
            $this->shipping->updateShipment(
                $orderId,
                strtolower(trim((string) $request->input('shipping_status'))),
                $this->nullableString($request->input('tracking_number')),
                $this->nullableString($request->input('shipping_carrier')),
                $this->actorId($request),
                $this->actorRole($request),
                $this->nullableString($request->input('note'))
            );
            return back()->with('admin_status', 'Shipping updated.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function receiveInventory(Request $request): RedirectResponse
    {
        try {
            $this->inventory->receive(
                (int) $request->input('product_id'),
                (int) $request->input('quantity'),
                $this->nullableString($request->input('note')),
                $this->actorId($request)
            );
            return back()->with('admin_status', 'Stock received.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function deductInventory(Request $request): RedirectResponse
    {
        try {
            $this->inventory->deduct(
                (int) $request->input('product_id'),
                (int) $request->input('quantity'),
                $this->nullableString($request->input('note')),
                $this->actorId($request)
            );
            return back()->with('admin_status', 'Stock deducted.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('admin_status', $exception->getMessage());
        }
    }

    public function updateInventoryThreshold(Request $request, int $productId): RedirectResponse
    {
        try {
            $this->inventory->updateThreshold($productId, (int) $request->input('low_stock_threshold'));
            return back()->with('admin_status', 'Low stock threshold updated.');
        } catch (\Throwable $exception) {
            return back()->with('admin_status', $exception->getMessage());
        }
    }

    protected function renderModule(Request $request, $module): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('admin', $locale);
        $config = $this->moduleConfig($module, $copy);

        return view('admin.module', [
            'pageTitle' => $config['title'].' | NEBVSIN',
            'pageDescription' => $config['description'],
            'canonicalUrl' => route($config['route'], ['lang' => $locale]),
            'robotsMeta' => 'noindex, nofollow',
            'adminCopy' => $copy,
            'moduleKey' => $module,
            'moduleConfig' => $config,
            'navItems' => $this->navItems($locale, $copy),
            'stats' => $this->moduleStats($module),
            'rows' => $this->moduleRows($module),
            'moduleData' => $this->moduleData($module),
            'adminStatus' => session('admin_status'),
        ]);
    }

    protected function navItems($locale, array $copy): array
    {
        return [
            ['key' => 'products', 'label' => $copy['nav_products'], 'route' => route('admin.products', ['lang' => $locale])],
            ['key' => 'procurement', 'label' => $copy['nav_procurement'], 'route' => route('admin.procurement', ['lang' => $locale])],
            ['key' => 'inventory', 'label' => $copy['nav_inventory'], 'route' => route('admin.inventory', ['lang' => $locale])],
            ['key' => 'customers', 'label' => $copy['nav_customers'], 'route' => route('admin.customers', ['lang' => $locale])],
            ['key' => 'payments', 'label' => $copy['nav_payments'], 'route' => route('admin.payments', ['lang' => $locale])],
            ['key' => 'shipping', 'label' => $copy['nav_shipping'], 'route' => route('admin.shipping', ['lang' => $locale])],
            ['key' => 'orders', 'label' => $copy['nav_orders'], 'route' => route('admin.orders', ['lang' => $locale])],
        ];
    }

    protected function moduleConfig($module, array $copy): array
    {
        $map = [
            'procurement' => ['title' => $copy['title_procurement'], 'description' => $copy['description_procurement'], 'route' => 'admin.procurement'],
            'inventory' => ['title' => $copy['title_inventory'], 'description' => $copy['description_inventory'], 'route' => 'admin.inventory'],
            'products' => ['title' => $copy['title_products'], 'description' => $copy['description_products'], 'route' => 'admin.products'],
            'customers' => ['title' => $copy['title_customers'], 'description' => $copy['description_customers'], 'route' => 'admin.customers'],
            'payments' => ['title' => $copy['title_payments'], 'description' => $copy['description_payments'], 'route' => 'admin.payments'],
            'shipping' => ['title' => $copy['title_shipping'], 'description' => $copy['description_shipping'], 'route' => 'admin.shipping'],
            'orders' => ['title' => $copy['title_orders'], 'description' => $copy['description_orders'], 'route' => 'admin.orders'],
        ];

        return $map[$module];
    }

    protected function moduleStats($module): array
    {
        switch ($module) {
            case 'procurement':
                return $this->procurement->summary();
            case 'inventory':
                return $this->inventory->summary();
            case 'products':
                return $this->products->summary();
            case 'customers':
                return $this->customers->summary();
            case 'payments':
                return $this->payments->summary();
            case 'shipping':
                return $this->shipping->summary();
            case 'orders':
            default:
                return $this->orders->summary();
        }
    }

    protected function moduleRows($module): array
    {
        switch ($module) {
            case 'procurement':
                return $this->procurement->listPurchaseOrders();
            case 'inventory':
                return $this->inventory->snapshot();
            case 'products':
                return $this->products->listProducts();
            case 'customers':
                return $this->customers->listCustomers();
            case 'payments':
                return $this->payments->listPayments();
            case 'shipping':
                return $this->shipping->listShipments();
            case 'orders':
            default:
                return $this->orders->listOrders();
        }
    }

    protected function recentRows($table, array $columns, $orderBy, $limit = 8): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $available = array_values(array_filter($columns, function ($column) use ($table) {
            return Schema::hasColumn($table, $column);
        }));

        if (! $available) {
            return [];
        }

        $query = DB::table($table)->select($available);

        if (Schema::hasColumn($table, $orderBy)) {
            $query->orderByDesc($orderBy);
        }

        return $query->limit($limit)->get()->map(function ($row) {
            return (array) $row;
        })->all();
    }

    protected function recentInventoryRows(): array
    {
        if (! Schema::hasTable('product_inventory')) {
            return [];
        }

        $query = DB::table('product_inventory as pi')
            ->leftJoin('products as p', 'p.id', '=', 'pi.product_id')
            ->select('pi.product_id', 'p.name', 'pi.qty_on_hand', 'pi.low_stock_threshold', 'pi.avg_unit_cost', 'pi.last_unit_cost', 'pi.updated_at')
            ->orderBy('pi.qty_on_hand')
            ->limit(8)
            ->get();

        return $query->map(function ($row) {
            return (array) $row;
        })->all();
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

    protected function sumColumn($table, $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->sum($column);
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

    protected function actorId(Request $request)
    {
        $auth = $request->attributes->get('legacy_auth');
        return is_array($auth) ? ($auth['user']['id'] ?? null) : null;
    }

    protected function actorRole(Request $request): string
    {
        $auth = $request->attributes->get('legacy_auth');
        return is_array($auth) ? (string) ($auth['user']['role'] ?? 'admin') : 'admin';
    }

    protected function moduleData($module): array
    {
        switch ($module) {
            case 'procurement':
                return [
                    'suppliers' => $this->procurement->suppliers(),
                    'productCosts' => $this->procurement->productCosts(),
                    'selectedPurchaseOrder' => $this->procurement->purchaseOrderDetail((string) request()->query('purchase_order_id', '')),
                ];
            case 'inventory':
                return [
                    'lowStockItems' => $this->inventory->lowStockItems(),
                    'recentMovements' => $this->inventory->recentMovements(),
                ];
            case 'payments':
                return [
                    'payments' => $this->payments->listPayments(),
                ];
            default:
                return [];
        }
    }

    protected function nullableString($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    protected function prepareProductInput(Request $request, bool $partial): array
    {
        $input = $request->except(['image_file']);
        /** @var UploadedFile|null $file */
        $file = $request->file('image_file');

        if ($file instanceof UploadedFile && $file->isValid()) {
            $input['image'] = $this->storeProductImage($file);
        } elseif (! $partial) {
            $input['image'] = trim((string) ($input['image'] ?? ''));
        }

        return $input;
    }

    protected function storeProductImage(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Product image must be PNG, JPEG, or WEBP.');
        }

        if (($file->getSize() ?? 0) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Product image must be 5MB or smaller.');
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'png'));
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $filename = 'product-'.Str::lower((string) Str::uuid()).'.'.$extension;
        $targetDirectory = public_path('uploads/products');

        if (! is_dir($targetDirectory) && ! @mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
            throw new \RuntimeException('Unable to create product upload directory.');
        }

        $file->move($targetDirectory, $filename);

        return '/uploads/products/'.$filename;
    }
}
