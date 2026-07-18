@extends('layouts.storefront')

@push('meta')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'NEBVSIN',
            'url' => $canonicalUrl ?? request()->url(),
            'logo' => $ogImage ?? null,
            'sameAs' => ['https://instagram.com/nebvsin'],
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'NEBVSIN',
            'url' => $canonicalUrl ?? request()->url(),
            'inLanguage' => $storefrontLocale,
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'NEBVSIN drop archive',
            'url' => $canonicalUrl ?? request()->url(),
            'numberOfItems' => count($products),
            'itemListElement' => collect($products)->values()->map(function ($product, $index) use ($storefrontLocale) {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => route('storefront.products.show', ['identifier' => $product['slug'], 'lang' => $storefrontLocale]),
                    'name' => $product['name'],
                    'image' => $product['image_url'],
                ];
            })->all(),
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    {{-- <section class="hero reveal in-view" aria-labelledby="hero-title">
        <div class="hero-inner">
            <p class="hero-subtitle hero-intro">{{ $copy['subtitle'] }}</p>
            <h1 id="hero-title" class="hero-intro">{{ $copy['seo_heading'] }}</h1>
            <p class="microtext hero-intro">{{ $copy['microtext'] }}</p>
            <p class="hero-copy hero-intro">{{ $copy['seo_copy'] }}</p>
            <a class="cta hero-intro" href="#drop">{{ $copy['cta'] }}</a>

            <figure class="hero-intro hero-video-soon-wrap" aria-label="NEBVSIN campaign video">
                @if ($heroVideoUrl)
                    <video
                        class="hero-video"
                        autoplay
                        muted
                        loop
                        playsinline
                        controls
                        preload="metadata"
                    >
                        <source src="{{ $heroVideoUrl }}" type="video/mp4">
                    </video>
                @endif
                <span class="hero-video-soon-label">SOON</span>
            </figure>
        </div>
    </section> --}}

    <section class="drop reveal in-view" id="drop" aria-labelledby="drop-title">
        <div class="section-head">
            <h2 id="drop-title">{{ $copy['drop_title'] }}</h2>
        </div>
        <div class="product-grid" id="productGrid">
            @forelse ($products as $product)
                <article class="card {{ $product['coming_soon'] ? 'is-coming-soon' : '' }}">
                    <p class="card-name">{{ $product['name'] }}</p>
                    <p class="card-price">{{ $product['price_label'] }}</p>
                    @if ($product['coming_soon'])
                        <div class="card-link is-coming-soon">
                            <img src="{{ $product['image_url'] }}" alt="{{ $product['alt'] }}">
                        </div>
                        <h4 class="card-soon">{{ $copy['coming_soon'] }}</h4>
                    @else
                        <a class="card-link" href="{{ route('storefront.products.show', ['identifier' => $product['slug'], 'lang' => $storefrontLocale]) }}">
                            @if ($product['limited_qty'] > 0)
                                <span class="card-status">{{ $copy['limited_drop'] }}</span>
                                <span class="card-limited-tag">{{ $copy['limited'] }} {{ $product['limited_qty'] }}</span>
                            @endif
                            <img src="{{ $product['image_url'] }}" alt="{{ $product['alt'] }}">
                            <span class="card-view">{{ $copy['view_case'] }}</span>
                        </a>
                    @endif
                </article>
            @empty
                <p>No products are available yet.</p>
            @endforelse
        </div>
    </section>

    <section class="manifesto reveal in-view" id="manifesto" aria-labelledby="manifesto-title">
        <h2 id="manifesto-title" class="manifesto-quote">
            {{ $copy['manifesto_line_1'] }}
            <br>
            {{ $copy['manifesto_line_2'] }}
        </h2>
        <p class="manifesto-copy">{{ $copy['manifesto_copy'] }}</p>
    </section>
@endsection
