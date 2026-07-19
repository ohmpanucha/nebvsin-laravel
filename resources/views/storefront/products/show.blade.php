@extends('layouts.storefront')

@push('meta')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $product['description'],
            'image' => array_map(function ($image) {
                return $image['url'];
            }, $product['gallery'] ?? [['url' => $product['image_url']]]),
            'sku' => (string) $product['id'],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'THB',
                'price' => $product['price_thb'],
                'availability' => $product['coming_soon'] ? 'https://schema.org/PreOrder' : 'https://schema.org/InStock',
                'url' => $canonicalUrl,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => route('storefront.home', ['lang' => $storefrontLocale]),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Drop',
                    'item' => route('storefront.home', ['lang' => $storefrontLocale]).'#drop',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $product['name'],
                    'item' => $canonicalUrl,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($copy['faq_items'] ?? [])->map(function (array $item) {
                return [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ];
            })->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    @php
        $descriptionLines = array_values(array_filter(preg_split('/\r\n|\r|\n/', (string) ($product['description'] ?? ''))));
        $sizes = ['S', 'M', 'L', 'XL', '2XL'];
        $limitedQty = (int) ($product['limited_qty'] ?? 0);
        $editionTotal = (int) ($product['edition_total'] ?? 0);
        $limitedTotal = $editionTotal > 0 ? $editionTotal : $limitedQty;
        $paidSoldQty = (int) ($product['paid_sold_qty'] ?? 0);
        $soldProgress = $limitedQty > 0 ? min($paidSoldQty, $limitedQty).' / '.$limitedQty : null;
        $isSignature = ($product['tier'] ?? '') === 'signature';
        $productStory = trim((string) ($product['story'] ?: $product['description']));
        $sizeChart = [
            ['size' => 'S', 'chest' => '44', 'length' => '28.5'],
            ['size' => 'M', 'chest' => '46', 'length' => '29.5'],
            ['size' => 'L', 'chest' => '48', 'length' => '30'],
            ['size' => 'XL', 'chest' => '50', 'length' => '30.5'],
        ];
    @endphp

    <section class="detail-page">
        <section class="detail-layout reveal in-view">
            <div class="detail-image-wrap">
                <div class="detail-image-frame">
                    @if ($product['coming_soon'])
                        <span class="detail-status-badge">{{ $copy['coming_soon'] }}</span>
                    @elseif ($soldProgress)
                        <span class="detail-status-badge">{{ $soldProgress }}</span>
                    @endif
                    <img class="detail-image" src="{{ $product['gallery'][0]['url'] ?? $product['image_url'] }}" alt="{{ $product['alt'] }}" data-detail-main-image>
                </div>
                @if (!empty($product['gallery']) && count($product['gallery']) > 1)
                    <div class="detail-gallery" role="list" aria-label="Product gallery">
                        @foreach ($product['gallery'] as $index => $image)
                            <button
                                type="button"
                                class="detail-thumb {{ $index === 0 ? 'is-active' : '' }}"
                                data-detail-thumb
                                data-image-url="{{ $image['url'] }}"
                                data-image-alt="{{ $image['alt'] }}"
                                aria-label="View image {{ $index + 1 }}"
                            >
                                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <article class="detail-info detail-info--{{ $product['tier'] }}">
                <p class="detail-kicker">{{ $copy['eyebrow'] }}</p>
                <p class="detail-tier-line">
                    <span class="tier-badge tier-badge--{{ $product['tier'] }}">{{ $product['tier_number'] }} / {{ $product['tier_label'] }}</span>
                </p>
                <h1 class="detail-title">{{ $product['name'] }}</h1>
                <p class="detail-price">{{ $product['price_label'] }}</p>
                <p class="detail-tier-tagline">{{ $product['tier_tagline'] }}</p>

                <div class="detail-desc">
                    @forelse ($descriptionLines as $line)
                        <p>{{ $line }}</p>
                    @empty
                        <p>{{ $product['description'] }}</p>
                    @endforelse
                </div>

                @if ($isSignature)
                    <section class="signature-panel" aria-label="Signature edition details">
                        <p class="detail-label">LIMITED EDITION</p>
                        @if ($limitedTotal > 0)
                            <strong>{{ $limitedTotal }} PIECES WORLDWIDE</strong>
                        @else
                            <strong>COLLECTIBLE PIECE</strong>
                        @endif
                        <span>NO RESTOCK</span>
                        <span>PREMIUM PACKAGING INCLUDED</span>
                        @if (!empty($product['edition_label']))
                            <p>CURRENT PIECE {{ $product['edition_label'] }}</p>
                        @endif
                    </section>
                @endif

                <div class="detail-meta-grid">
                    <div class="detail-meta-card">
                        <p class="detail-label">STATUS</p>
                        <p>{{ $product['coming_soon'] ? ($copy['coming_soon']) : strtoupper(str_replace('_', ' ', $product['status'] ?? 'available')) }}</p>
                    </div>
                    <div class="detail-meta-card">
                        <p class="detail-label">LEVEL</p>
                        <p>{{ $product['tier_label'] }}</p>
                    </div>
                </div>

                <section aria-labelledby="size-select-title">
                    <h2 id="size-select-title" class="detail-label">SIZE SELECT</h2>
                    <form method="post" action="{{ route('storefront.cart.add', ['lang' => $storefrontLocale]) }}" class="product-purchase-form">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                        <input type="hidden" name="quantity" value="1">
                        <div class="size-picker">
                            <div class="size-options" role="radiogroup" aria-label="Available sizes">
                                @foreach ($sizes as $index => $size)
                                    <input
                                        class="size-input"
                                        type="radio"
                                        name="size"
                                        id="size-{{ strtolower($size) }}"
                                        value="{{ $size }}"
                                        {{ $index === 2 ? 'checked' : '' }}
                                    >
                                    <label class="size-btn" for="size-{{ strtolower($size) }}">{{ $size }}</label>
                                @endforeach
                            </div>
                            <p class="selected-size">
                                <span class="selected-size-label">SELECTED SIZE</span>
                                <span class="selected-size-value" data-selected-size>L</span>
                            </p>
                        </div>

                        <div class="detail-cta-row">
                            <button type="submit" name="intent" value="cart" class="cta add-cart luxury-hover" {{ $product['coming_soon'] ? 'disabled' : '' }}>
                                {{ $product['coming_soon'] ? strtoupper($copy['coming_soon']) : 'ADD TO CART' }}
                            </button>
                            <button type="submit" name="intent" value="buy_now" class="cta detail-buy-now luxury-hover" {{ $product['coming_soon'] ? 'disabled' : '' }}>
                                {{ $product['coming_soon'] ? strtoupper($copy['coming_soon']) : 'BUY NOW' }}
                            </button>
                            <a href="{{ request()->fullUrlWithQuery(['cart' => 'open']) }}" class="detail-secondary-link">VIEW CART</a>
                            <p class="detail-support-copy">{{ $copy['legacy_note'] }}</p>
                        </div>
                    </form>
                </section>
            </article>
        </section>

        @if (in_array($product['tier'] ?? '', ['core', 'signature'], true) && $productStory !== '')
            <section class="product-story reveal in-view" aria-labelledby="product-story-title">
                <div class="section-head">
                    <h2 id="product-story-title" class="detail-label">PRODUCT STORY</h2>
                </div>
                <p>{{ $productStory }}</p>
            </section>
        @endif

        <section class="size-chart reveal in-view" aria-labelledby="size-chart-title">
            <div class="section-head">
                <h2 id="size-chart-title" class="detail-label">SIZE CHART</h2>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">SIZE</th>
                            <th scope="col">CHEST (cm)</th>
                            <th scope="col">LENGTH (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sizeChart as $row)
                            <tr>
                                <th scope="row">{{ $row['size'] }}</th>
                                <td>{{ $row['chest'] }}</td>
                                <td>{{ $row['length'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="product-info-grid reveal in-view">
            {{-- <article class="product-copy-card" aria-labelledby="shipping-info-title">
                <div class="section-head">
                    <h2 id="shipping-info-title" class="detail-label">{{ $copy['shipping_title'] }}</h2>
                </div>
                <p class="product-copy-body">{{ $copy['shipping_copy'] }}</p>
            </article> --}}

            <article class="product-copy-card" aria-labelledby="faq-title">
                <div class="section-head">
                    <h2 id="faq-title" class="detail-label">{{ $copy['faq_title'] }}</h2>
                </div>
                <div class="product-faq-list">
                    @foreach ($copy['faq_items'] ?? [] as $item)
                        <article class="product-faq-item">
                            <h3 class="product-faq-question">{{ $item['question'] }}</h3>
                            <p class="product-faq-answer">{{ $item['answer'] }}</p>
                        </article>
                    @endforeach
                </div>
            </article>
        </section>
    </section>
@endsection

@push('meta')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var inputs = document.querySelectorAll('.size-input');
            var output = document.querySelector('[data-selected-size]');
            var mainImage = document.querySelector('[data-detail-main-image]');
            var thumbs = document.querySelectorAll('[data-detail-thumb]');

            if (!inputs.length || !output) {
                return;
            }

            var syncSelectedSize = function () {
                var active = document.querySelector('.size-input:checked');
                output.textContent = active ? active.value : '-';
            };

            inputs.forEach(function (input) {
                input.addEventListener('change', syncSelectedSize);
            });

            syncSelectedSize();

            if (mainImage && thumbs.length) {
                thumbs.forEach(function (thumb) {
                    thumb.addEventListener('click', function () {
                        mainImage.src = thumb.getAttribute('data-image-url') || mainImage.src;
                        mainImage.alt = thumb.getAttribute('data-image-alt') || mainImage.alt;

                        thumbs.forEach(function (item) {
                            item.classList.remove('is-active');
                        });

                        thumb.classList.add('is-active');
                    });
                });
            }
        });
    </script>
@endpush
