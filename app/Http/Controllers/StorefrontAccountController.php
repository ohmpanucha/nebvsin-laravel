<?php

namespace App\Http\Controllers;

use App\Support\StorefrontLocale;
use App\Support\PromptPayQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StorefrontAccountController extends Controller
{
    public function address(Request $request): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('account', $locale);
        $auth = $request->attributes->get('legacy_auth');
        $address = $this->findAddress($auth['user']['id']);

        return view('storefront.account.address', [
            'copy' => $copy,
            'pageTitle' => $copy['title'].' | NEBVSIN',
            'pageDescription' => $copy['caption'],
            'canonicalUrl' => route('storefront.account.address', ['lang' => $locale]),
            'address' => $address,
            'statusMessage' => session('account_status'),
        ]);
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('account', $locale);
        $auth = $request->attributes->get('legacy_auth');

        $address = [
            'full_name' => trim((string) $request->input('full_name')),
            'phone' => trim((string) $request->input('phone')),
            'address_line1' => trim((string) $request->input('address_line1')),
            'address_line2' => trim((string) $request->input('address_line2')),
            'district' => trim((string) $request->input('district')),
            'province' => trim((string) $request->input('province')),
            'postal_code' => trim((string) $request->input('postal_code')),
        ];

        foreach (['full_name', 'phone', 'address_line1', 'district', 'province', 'postal_code'] as $field) {
            if ($address[$field] === '') {
                return back()->withInput()->with('account_status', $copy['status_fill']);
            }
        }

        DB::statement(
            "
            INSERT INTO user_addresses
            (user_id, full_name, phone, address_line1, address_line2, district, province, postal_code, updated_at)
            VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              full_name = VALUES(full_name),
              phone = VALUES(phone),
              address_line1 = VALUES(address_line1),
              address_line2 = VALUES(address_line2),
              district = VALUES(district),
              province = VALUES(province),
              postal_code = VALUES(postal_code),
              updated_at = VALUES(updated_at)
            ",
            [
                $auth['user']['id'],
                $address['full_name'],
                $address['phone'],
                $address['address_line1'],
                $address['address_line2'],
                $address['district'],
                $address['province'],
                $address['postal_code'],
                now()->format('Y-m-d H:i:s'),
            ]
        );

        return redirect()->route('storefront.account.address', ['lang' => $locale])
            ->with('account_status', $copy['status_saved']);
    }

    public function purchaseHistory(Request $request, PromptPayQrService $promptPay): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('purchase', $locale);
        $auth = $request->attributes->get('legacy_auth');
        $orders = $this->loadOrders($auth['user']['id']);
        $promptPayQrByOrder = [];

        foreach ($orders as $order) {
            if (strtolower((string) ($order['status'] ?? '')) !== 'pending') {
                continue;
            }

            try {
                $promptPayQrByOrder[$order['order_id']] = $promptPay->buildForMobile(
                    (string) config('storefront.promptpay.mobile_number'),
                    (float) ($order['total_amount'] ?? 0)
                );
            } catch (\Throwable $exception) {
                $promptPayQrByOrder[$order['order_id']] = null;
            }
        }

        return view('storefront.account.purchase-history', [
            'copy' => $copy,
            'pageTitle' => $copy['title'].' | NEBVSIN',
            'pageDescription' => $copy['caption'],
            'canonicalUrl' => route('storefront.account.purchase-history', ['lang' => $locale]),
            'orders' => $orders,
            'promptPayQrByOrder' => $promptPayQrByOrder,
            'locale' => $locale,
            'statusMessage' => session('account_status') ?: session('cart_status'),
        ]);
    }

    public function uploadSlip(Request $request, string $orderId): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('payment', $locale);
        $auth = $request->attributes->get('legacy_auth');
        /** @var UploadedFile|null $file */
        $file = $request->file('slip');

        if (! $file || ! $file->isValid()) {
            return back()->with('account_status', $copy['invalid_file']);
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $file->getSize() > 5 * 1024 * 1024) {
            return back()->with('account_status', $copy['invalid_file_type']);
        }

        $binary = @file_get_contents($file->getRealPath());
        if ($binary === false) {
            return back()->with('account_status', $copy['file_read_failed']);
        }

        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('user_id', $auth['user']['id'])
            ->update([
                'payment_slip_data' => 'data:'.$mime.';base64,'.base64_encode($binary),
                'payment_slip_mime' => $mime,
                'payment_slip_filename' => substr((string) $file->getClientOriginalName(), 0, 255) ?: null,
                'payment_slip_uploaded_at' => now()->format('Y-m-d H:i:s'),
                'status' => DB::raw("CASE WHEN status = 'pending' THEN 'awaiting_review' ELSE status END"),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

        if (! $updated) {
            return back()->with('account_status', $copy['order_missing']);
        }
        
        return redirect()->route('storefront.account.purchase-history', ['lang' => $locale])
            ->with('account_status', $copy['upload_success']);
    }

    protected function findAddress(string $userId): ?array
    {
        $saved = DB::table('user_addresses')
            ->select('full_name', 'phone', 'address_line1', 'address_line2', 'district', 'province', 'postal_code')
            ->where('user_id', $userId)
            ->first();

        if ($saved) {
            return (array) $saved;
        }

        $latest = DB::table('order_addresses as oa')
            ->join('orders as o', 'o.id', '=', 'oa.order_id')
            ->select('oa.full_name', 'oa.phone', 'oa.address_line1', 'oa.address_line2', 'oa.district', 'oa.province', 'oa.postal_code')
            ->where('oa.user_id', $userId)
            ->orderByDesc('o.created_at')
            ->first();

        return $latest ? (array) $latest : null;
    }

    protected function loadOrders(string $userId): array
    {
        $orders = DB::table('orders')
            ->select('id as order_id', 'total_amount', 'currency', 'status', 'shipping_status', 'payment_slip_data', 'payment_slip_uploaded_at', 'created_at')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        if (! $orders) {
            return [];
        }

        $lines = DB::table('order_items')
            ->select('order_id', 'product_id', 'name', 'image', 'size', 'quantity', 'unit_amount', 'line_total')
            ->whereIn('order_id', array_column($orders, 'order_id'))
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        return array_map(function (array $order) use ($lines) {
            $order['lines'] = collect($lines->get($order['order_id'], []))
                ->map(fn ($row) => (array) $row)
                ->all();

            return $order;
        }, $orders);
    }
}
