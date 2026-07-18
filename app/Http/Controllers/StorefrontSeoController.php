<?php

namespace App\Http\Controllers;

use App\Support\StorefrontProductRepository;
use Illuminate\Http\Response;

class StorefrontSeoController extends Controller
{
    public function sitemap(StorefrontProductRepository $products): Response
    {
        $urls = collect([
            [
                'loc' => route('storefront.home'),
                'priority' => '1.0',
                'changefreq' => 'daily',
            ],
        ])->merge(
            $products->allPublicProducts()->map(function (array $product) {
                return [
                    'loc' => route('storefront.products.show', ['identifier' => $product['slug']]),
                    'priority' => '0.8',
                    'changefreq' => 'daily',
                ];
            })
        );

        return response()
            ->view('storefront.seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        return response(
            "User-agent: *\nAllow: /\n\nSitemap: ".route('storefront.sitemap')."\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
