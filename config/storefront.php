<?php

$fallbackProductsPath = env('STOREFRONT_FALLBACK_PRODUCTS_PATH');

return [
    'asset_base_url' => env('STOREFRONT_ASSET_BASE_URL', ''),
    'fallback_products_path' => $fallbackProductsPath ?: base_path('../version-react/frontend/src/data/products.json'),
    'promptpay' => [
        'mobile_number' => env('STOREFRONT_PROMPTPAY_MOBILE', '0823454460'),
        'account_name' => env('STOREFRONT_PROMPTPAY_ACCOUNT_NAME', 'NEBVSIN STORE'),
        'bank_name' => env('STOREFRONT_PROMPTPAY_BANK_NAME', 'KBank'),
    ],
];
