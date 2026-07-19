<?php

namespace App\Http\Controllers;

use App\Support\StorefrontProductRepository;
use App\Support\StorefrontLocale;
use App\Support\ProductTierConfig;
use App\Support\HomeContentRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(Request $request, StorefrontProductRepository $products, HomeContentRepository $homeContent): View
    {
        $locale = StorefrontLocale::resolve($request);

        return view('storefront.home', [
            'products' => $products->allPublicProducts(),
            'tierConfig' => ProductTierConfig::all(),
            'selectedTier' => 'all',
            'isShopPage' => false,
            'homeContent' => $homeContent->get(),
            'pageTitle' => 'NEBVSIN Streetwear | Limited Drop Clothing Brand',
            'pageDescription' => 'Discover NEBVSIN limited drop streetwear, graphic tees, and crawlable product releases with server-rendered SEO-ready storefront pages.',
            'canonicalUrl' => route('storefront.home', ['lang' => $locale]),
            'heroVideoUrl' => $products->assetUrl('/uploads/video/video_present.mp4'),
            'ogImage' => $products->defaultOgImage(),
            'copy' => StorefrontLocale::copy('home', $locale),
        ]);
    }

    public function shop(Request $request, StorefrontProductRepository $products): View
    {
        $locale = StorefrontLocale::resolve($request);
        $requestedTier = (string) $request->query('level', '');
        $selectedTier = in_array($requestedTier, ProductTierConfig::keys(), true) ? $requestedTier : 'all';

        return view('storefront.home', [
            'products' => $products->allPublicProducts(),
            'tierConfig' => ProductTierConfig::all(),
            'selectedTier' => $selectedTier,
            'isShopPage' => true,
            'pageTitle' => 'Shop NEBVSIN | Essential Core Signature',
            'pageDescription' => 'Shop NEBVSIN by product level: Essential, Core, and Signature limited streetwear pieces.',
            'canonicalUrl' => route('storefront.shop', ['lang' => $locale]),
            'heroVideoUrl' => $products->assetUrl('/uploads/video/video_present.mp4'),
            'ogImage' => $products->defaultOgImage(),
            'copy' => StorefrontLocale::copy('home', $locale),
        ]);
    }

    public function collections(Request $request, StorefrontProductRepository $products, ?string $tier = null): View
    {
        $locale = StorefrontLocale::resolve($request);
        $selectedTier = $tier ? ProductTierConfig::normalizeKey($tier) : null;
        abort_if($tier !== null && $selectedTier !== $tier, 404);

        $allProducts = $products->allPublicProducts();

        return view('storefront.collections', [
            'products' => $selectedTier
                ? $allProducts->where('tier', $selectedTier)->values()
                : $allProducts,
            'tierConfig' => ProductTierConfig::all(),
            'selectedTier' => $selectedTier,
            'pageTitle' => ($selectedTier ? ProductTierConfig::get($selectedTier)['label'].' Collection' : 'Collections').' | NEBVSIN',
            'pageDescription' => 'Explore the NEBVSIN product tier architecture across Essential, Core, and Signature.',
            'canonicalUrl' => $selectedTier
                ? route('storefront.collections.tier', ['tier' => $selectedTier, 'lang' => $locale])
                : route('storefront.collections', ['lang' => $locale]),
            'ogImage' => $products->defaultOgImage(),
            'copy' => StorefrontLocale::copy('home', $locale),
        ]);
    }

    public function show(Request $request, string $identifier, StorefrontProductRepository $products)
    {
        $locale = StorefrontLocale::resolve($request);

        if (ctype_digit($identifier)) {
            $product = $products->findPublicProductById((int) $identifier);

            abort_if($product === null, 404);

            return redirect()->route('storefront.products.show', ['identifier' => $product['slug'], 'lang' => $locale], 301);
        }

        $product = $products->findPublicProductBySlug($identifier);

        abort_if($product === null, 404);

        return view('storefront.products.show', [
            'product' => $product,
            'pageTitle' => $product['meta_title'],
            'pageDescription' => $product['meta_description'],
            'canonicalUrl' => route('storefront.products.show', ['identifier' => $product['slug'], 'lang' => $locale]),
            'ogImage' => $product['og_image'],
            'copy' => StorefrontLocale::copy('product', $locale),
        ]);
    }
}
