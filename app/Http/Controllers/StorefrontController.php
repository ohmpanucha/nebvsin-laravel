<?php

namespace App\Http\Controllers;

use App\Support\StorefrontProductRepository;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(Request $request, StorefrontProductRepository $products): View
    {
        $locale = StorefrontLocale::resolve($request);

        return view('storefront.home', [
            'products' => $products->allPublicProducts(),
            'pageTitle' => 'NEBVSIN Streetwear | Limited Drop Clothing Brand',
            'pageDescription' => 'Discover NEBVSIN limited drop streetwear, graphic tees, and crawlable product releases with server-rendered SEO-ready storefront pages.',
            'canonicalUrl' => route('storefront.home', ['lang' => $locale]),
            'heroVideoUrl' => $products->assetUrl('/uploads/video/video_present.mp4'),
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
