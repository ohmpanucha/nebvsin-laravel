<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\StorefrontAccountController;
use App\Http\Controllers\StorefrontAuthController;
use App\Http\Controllers\StorefrontCartController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StorefrontPageController;
use App\Http\Controllers\StorefrontSeoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Route::match(['get', 'post'], '/dev/hash-password', function (Request $request) {
//     abort_unless(config('app.debug'), 404);

//     $password = (string) $request->input('password', '');
//     if ($password === '') {
//         return response()->json([
//             'message' => 'Send password as ?password=your-password or POST password.',
//         ], 422);
//     }

//     return response()->json([
//         'hash' => Hash::make($password),
//     ]);
// })->middleware('throttle:20,1')->name('dev.hash-password');

Route::get('/', [StorefrontController::class, 'index'])->name('storefront.home');
Route::get('/shop', [StorefrontController::class, 'shop'])->name('storefront.shop');
Route::get('/collections', [StorefrontController::class, 'collections'])->name('storefront.collections');
Route::get('/collections/{tier}', [StorefrontController::class, 'collections'])
    ->where('tier', 'essential|core|signature')
    ->name('storefront.collections.tier');
Route::get('/process', [StorefrontPageController::class, 'process'])->name('storefront.process');
Route::get('/login', [StorefrontAuthController::class, 'showLogin'])->name('storefront.login');
Route::post('/login', [StorefrontAuthController::class, 'login'])->name('storefront.login.submit');
Route::get('/register', [StorefrontAuthController::class, 'showRegister'])->name('storefront.register');
Route::post('/register', [StorefrontAuthController::class, 'register'])->name('storefront.register.submit');
Route::get('/forgot-password', [StorefrontAuthController::class, 'showForgotPassword'])->name('storefront.password.request');
Route::post('/forgot-password', [StorefrontAuthController::class, 'sendPasswordResetLink'])
    ->middleware('throttle:5,1')
    ->name('storefront.password.email');
Route::get('/reset-password/{token}', [StorefrontAuthController::class, 'showResetPassword'])->name('storefront.password.reset');
Route::post('/reset-password', [StorefrontAuthController::class, 'resetPassword'])
    ->middleware('throttle:10,1')
    ->name('storefront.password.update');
Route::post('/logout', [StorefrontAuthController::class, 'logout'])->name('storefront.logout');
Route::get('/cart', [StorefrontCartController::class, 'cart'])->name('storefront.cart');
Route::post('/cart/items', [StorefrontCartController::class, 'add'])->name('storefront.cart.add');
Route::patch('/cart/items/{key}', [StorefrontCartController::class, 'update'])->name('storefront.cart.update');
Route::delete('/cart/items/{key}', [StorefrontCartController::class, 'remove'])->name('storefront.cart.remove');
Route::middleware('legacy.auth')->group(function () {
    Route::get('/purchase-history', [StorefrontAccountController::class, 'purchaseHistory'])->name('storefront.account.purchase-history');
    Route::post('/purchase-history/{orderId}/slip', [StorefrontAccountController::class, 'uploadSlip'])->name('storefront.account.purchase-history.slip');
    Route::get('/account/address', [StorefrontAccountController::class, 'address'])->name('storefront.account.address');
    Route::post('/account/address', [StorefrontAccountController::class, 'updateAddress'])->name('storefront.account.address.update');
    Route::get('/checkout', [StorefrontCartController::class, 'checkout'])->name('storefront.checkout');
    Route::post('/checkout', [StorefrontCartController::class, 'placeOrder'])->name('storefront.checkout.submit');
    Route::get('/checkout/payment/{orderId}', [StorefrontCartController::class, 'payment'])->name('storefront.checkout.payment');
});
Route::middleware(['legacy.auth', 'legacy.admin'])->prefix('admin')->group(function () {
    Route::get('/home', [AdminController::class, 'home'])->name('admin.home');
    Route::patch('/home', [AdminController::class, 'updateHomeContent'])->name('admin.home.update');
    Route::get('/procurement', [AdminController::class, 'procurement'])->name('admin.procurement');
    Route::post('/procurement/suppliers', [AdminController::class, 'storeSupplier'])->name('admin.procurement.suppliers.store');
    Route::post('/procurement/purchase-orders', [AdminController::class, 'storePurchaseOrder'])->name('admin.procurement.purchase-orders.store');
    Route::patch('/procurement/purchase-orders/{poId}/status', [AdminController::class, 'updatePurchaseOrderStatus'])->name('admin.procurement.purchase-orders.status');
    Route::post('/procurement/purchase-orders/{poId}/receive', [AdminController::class, 'receivePurchaseOrder'])->name('admin.procurement.purchase-orders.receive');
    Route::get('/inventory', [AdminController::class, 'inventory'])->name('admin.inventory');
    Route::post('/inventory/receive', [AdminController::class, 'receiveInventory'])->name('admin.inventory.receive');
    Route::post('/inventory/deduct', [AdminController::class, 'deductInventory'])->name('admin.inventory.deduct');
    Route::patch('/inventory/{productId}/threshold', [AdminController::class, 'updateInventoryThreshold'])->name('admin.inventory.threshold');
    Route::get('/products', [AdminController::class, 'products'])->name('admin.products');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('admin.products.store');
    Route::patch('/products/{productId}', [AdminController::class, 'updateProduct'])->name('admin.products.update');
    Route::delete('/products/{productId}', [AdminController::class, 'deleteProduct'])->name('admin.products.delete');
    Route::get('/customers', [AdminController::class, 'customers'])->name('admin.customers');
    Route::patch('/customers/{userId}/status', [AdminController::class, 'updateCustomerStatus'])->name('admin.customers.status');
    Route::get('/payments', [AdminController::class, 'payments'])->name('admin.payments');
    Route::patch('/payments/{orderId}/status', [AdminController::class, 'updatePaymentStatus'])->name('admin.payments.status');
    Route::get('/shipping', [AdminController::class, 'shipping'])->name('admin.shipping');
    Route::patch('/shipping/{orderId}', [AdminController::class, 'updateShipping'])->name('admin.shipping.update');
    Route::get('/orders', [AdminController::class, 'orders'])->name('admin.orders');
    Route::patch('/orders/{orderId}/status', [AdminController::class, 'updateOrderStatus'])->name('admin.orders.status');
});
Route::get('/sitemap.xml', [StorefrontSeoController::class, 'sitemap'])->name('storefront.sitemap');
Route::get('/robots.txt', [StorefrontSeoController::class, 'robots'])->name('storefront.robots');
Route::get('/products/{identifier}', [StorefrontController::class, 'show'])
    ->where('identifier', '[A-Za-z0-9-]+')
    ->name('storefront.products.show');
