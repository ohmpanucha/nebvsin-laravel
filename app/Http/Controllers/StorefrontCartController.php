<?php

namespace App\Http\Controllers;

use App\Support\StorefrontCartService;
use App\Support\StorefrontCheckoutService;
use App\Support\StorefrontLocale;
use App\Support\PromptPayQrService;
use App\Support\StorefrontProductRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontCartController extends Controller
{
    protected function cartDrawerRedirect(Request $request, string $locale): RedirectResponse
    {
        $target = url()->previous() ?: route('storefront.home', ['lang' => $locale]);

        $separator = parse_url($target, PHP_URL_QUERY) ? '&' : '?';
        $target .= $separator.'cart=open';

        return redirect()->to($target);
    }

    public function cart(Request $request, StorefrontCartService $cart): View
    {
        $locale = StorefrontLocale::resolve($request);
        $items = $cart->items($request);

        return view('storefront.cart.index', [
            'pageTitle' => 'Cart | NEBVSIN',
            'pageDescription' => 'Review selected products before checkout.',
            'canonicalUrl' => route('storefront.cart', ['lang' => $locale]),
            'cartItems' => $items,
            'cartTotalItems' => $cart->totalItems($request),
            'cartSubtotal' => $cart->subtotal($request),
            'cartStatus' => session('cart_status'),
        ]);
    }

    public function add(Request $request, StorefrontProductRepository $products, StorefrontCartService $cart): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('checkout', $locale);
        $product = $products->findPublicProductById((int) $request->input('product_id'));
        $size = strtoupper(trim((string) $request->input('size')));
        $quantity = max(1, (int) $request->input('quantity', 1));
        $intent = (string) $request->input('intent', 'cart');

        if (! $product) {
            return back()->with('cart_status', $copy['cart_missing']);
        }

        if (! in_array($size, ['S', 'M', 'L', 'XL', '2XL'], true)) {
            return back()->with('cart_status', $copy['cart_invalid_size']);
        }

        if ($product['coming_soon']) {
            return back()->with('cart_status', $copy['cart_coming_soon']);
        }

        $cart->add($request, $product, $size, $quantity);

        if ($intent === 'buy_now') {
            if (! $request->attributes->get('legacy_auth')) {
                return redirect()->route('storefront.login', [
                    'lang' => $locale,
                    'next' => route('storefront.checkout', ['lang' => $locale]),
                ])->with('cart_status', $copy['buy_now_guest']);
            }

            return redirect()->route('storefront.checkout', ['lang' => $locale])
                ->with('cart_status', $copy['buy_now_ready']);
        }

        return $this->cartDrawerRedirect($request, $locale)
            ->with('cart_status', $copy['cart_added']);
    }

    public function update(Request $request, string $key, StorefrontCartService $cart)
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('checkout', $locale);
        $cart->updateQuantity($request, $key, (int) $request->input('qty', 1));

        if ($request->expectsJson()) {
            $items = $cart->items($request);
            $updatedItem = collect($items)->firstWhere('key', $key);

            return response()->json([
                'item' => $updatedItem ? [
                    'key' => $updatedItem['key'],
                    'qty' => (int) ($updatedItem['qty'] ?? 0),
                    'line_subtotal' => (int) ($updatedItem['price_thb'] ?? 0) * (int) ($updatedItem['qty'] ?? 0),
                    'line_subtotal_label' => number_format((int) ($updatedItem['price_thb'] ?? 0) * (int) ($updatedItem['qty'] ?? 0)).' THB',
                ] : null,
                'removed' => ! $updatedItem,
                'cart' => [
                    'total_items' => $cart->totalItems($request),
                    'total_items_label' => str_pad((string) $cart->totalItems($request), 2, '0', STR_PAD_LEFT),
                    'subtotal' => $cart->subtotal($request),
                    'subtotal_label' => number_format($cart->subtotal($request)).' THB',
                    'is_empty' => ! $items,
                ],
                'status' => ! $updatedItem ? $copy['cart_removed'] : $copy['cart_updated'],
            ]);
        }

        return $this->cartDrawerRedirect($request, $locale)->with('cart_status', $copy['cart_updated']);
    }

    public function remove(Request $request, string $key, StorefrontCartService $cart): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('checkout', $locale);
        $cart->remove($request, $key);

        return $this->cartDrawerRedirect($request, $locale)->with('cart_status', $copy['cart_removed']);
    }

    public function checkout(Request $request, StorefrontCartService $cart): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('checkout', $locale);
        $auth = $request->attributes->get('legacy_auth');
        $address = $this->findAddress((string) ($auth['user']['id'] ?? ''));
        $items = $cart->items($request);

        return view('storefront.cart.checkout', [
            'pageTitle' => $copy['title'].' | NEBVSIN',
            'pageDescription' => $copy['caption'],
            'canonicalUrl' => route('storefront.checkout', ['lang' => $locale]),
            'cartItems' => $items,
            'cartSubtotal' => $cart->subtotal($request),
            'cartTotalItems' => $cart->totalItems($request),
            'cartStatus' => session('cart_status'),
            'address' => $address,
            'copy' => $copy,
        ]);
    }

    public function placeOrder(Request $request, StorefrontCartService $cart, StorefrontCheckoutService $checkout): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('checkout', $locale);
        $auth = $request->attributes->get('legacy_auth');

        foreach (['full_name', 'phone', 'address_line1', 'district', 'province', 'postal_code'] as $field) {
            if (trim((string) $request->input($field)) === '') {
                return back()->withInput()->with('cart_status', $copy['status_fill_address']);
            }
        }

        try {
            $orderId = $checkout->createOrder(
                $auth['user'],
                $cart->items($request),
                [
                    'full_name' => $request->input('full_name'),
                    'phone' => $request->input('phone'),
                    'address_line1' => $request->input('address_line1'),
                    'address_line2' => $request->input('address_line2'),
                    'district' => $request->input('district'),
                    'province' => $request->input('province'),
                    'postal_code' => $request->input('postal_code'),
                ]
            );

            $cart->clear($request);

            return redirect()->route('storefront.checkout.payment', ['lang' => $locale, 'orderId' => $orderId])
                ->with('cart_status', str_replace(':id', $orderId, $copy['status_created']));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();

            if ($message === 'Cart is empty.' || $message === 'Cart is empty or invalid.') {
                $message = $copy['status_invalid_cart'];
            } elseif ($message === 'Address is incomplete.') {
                $message = $copy['status_fill_address'];
            }

            return back()->withInput()->with('cart_status', $message);
        }
    }

    public function payment(Request $request, string $orderId, PromptPayQrService $promptPay): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('payment', $locale);
        $auth = $request->attributes->get('legacy_auth');
        $order = $this->findOrderForUser($orderId, (string) ($auth['user']['id'] ?? ''));

        abort_unless($order, 404);

        $promptPayData = null;
        try {
            $promptPayData = $promptPay->buildForMobile(
                (string) config('storefront.promptpay.mobile_number'),
                (float) $order['total_amount']
            );
        } catch (\Throwable $exception) {
            $promptPayData = null;
        }

        return view('storefront.cart.payment', [
            'pageTitle' => $copy['title'].' | NEBVSIN',
            'pageDescription' => $copy['caption'],
            'canonicalUrl' => route('storefront.checkout.payment', ['lang' => $locale, 'orderId' => $orderId]),
            'cartStatus' => session('cart_status'),
            'order' => $order,
            'copy' => $copy,
            'promptPayData' => $promptPayData,
            'promptPayAccountName' => (string) config('storefront.promptpay.account_name'),
            'promptPayBankName' => (string) config('storefront.promptpay.bank_name'),
        ]);
    }

    protected function findAddress(string $userId): ?array
    {
        if ($userId === '') {
            return null;
        }

        $saved = \Illuminate\Support\Facades\DB::table('user_addresses')
            ->select('full_name', 'phone', 'address_line1', 'address_line2', 'district', 'province', 'postal_code')
            ->where('user_id', $userId)
            ->first();

        return $saved ? (array) $saved : null;
    }

    protected function findOrderForUser(string $orderId, string $userId): ?array
    {
        if ($orderId === '' || $userId === '') {
            return null;
        }

        $order = DB::table('orders')
            ->select('id as order_id', 'user_email', 'total_amount', 'currency', 'status', 'shipping_status', 'payment_slip_data', 'payment_slip_uploaded_at', 'created_at')
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->first();

        if (! $order) {
            return null;
        }

        $lines = DB::table('order_items')
            ->select('order_id', 'product_id', 'name', 'image', 'size', 'quantity', 'unit_amount', 'line_total')
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return array_merge((array) $order, ['lines' => $lines]);
    }
}
