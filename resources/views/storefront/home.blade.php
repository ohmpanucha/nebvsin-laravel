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
            'name' => 'NEBVSIN featured core stories',
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
    @php
        $selectedTier = $selectedTier ?? 'all';
        $isShopPage = $isShopPage ?? false;
        $homeContent = $homeContent ?? [];
        $productCollection = collect($products);

        $productMatch = function (array $needles) use ($productCollection) {
            return $productCollection->first(function ($product) use ($needles) {
                $name = strtolower((string) ($product['name'] ?? ''));
                $slug = strtolower((string) ($product['slug'] ?? ''));

                foreach ($needles as $needle) {
                    $needle = strtolower($needle);

                    if (str_contains($name, $needle) || str_contains($slug, $needle)) {
                        return true;
                    }
                }

                return false;
            });
        };

        $coreFallback = $productCollection->firstWhere('tier', 'core') ?? $productCollection->first();
        $signatureFallback = $productCollection->firstWhere('tier', 'signature') ?? $coreFallback;
        $imageFallbacks = [
            'hero' => '/uploads/products/DROP01.png',
            'feature' => '/uploads/products/DROP01_shirt.png',
            'signature' => '/uploads/products/ChatGPT-Image-24-2569-13_50_58.png',
        ];

        $makeProductLink = function ($product, string $fallbackTier = 'core') use ($storefrontLocale) {
            if ($product && empty($product['coming_soon'])) {
                return route('storefront.products.show', ['identifier' => $product['slug'], 'lang' => $storefrontLocale]);
            }

            return route('storefront.shop', ['level' => $fallbackTier, 'lang' => $storefrontLocale]);
        };

        $heroProduct = $productMatch(['fuck the world', 'ftw']) ?? $coreFallback;
        $featureProduct = $productMatch(['shadow in my teeth', 'shadow']) ?? $coreFallback;
        $signatureProduct = ($signatureFallback && ($signatureFallback['tier'] ?? null) === 'signature') ? $signatureFallback : null;

        $heroImage = $homeContent['hero_image'] ?? $heroProduct['image_url'] ?? $imageFallbacks['hero'];
        $featureImage = $homeContent['feature_image'] ?? $featureProduct['image_url'] ?? $imageFallbacks['feature'];
        $featureUrl = $makeProductLink($featureProduct, 'core');
        $signatureImage = $homeContent['signature_image'] ?? $signatureProduct['image_url'] ?? $imageFallbacks['signature'];
        $signatureUrl = $makeProductLink($signatureProduct, 'signature');
        $coreProducts = $productCollection->where('tier', 'core')->values();

        $tierEyebrow = [
            'essential' => 'ENTRY LEVEL',
            'core' => 'BRAND CORE',
            'signature' => 'LIMITED',
        ];

        $manifestoCopyByLocale = [
            'en' => [
                'eyebrow' => $homeContent['manifesto_eyebrow'] ?? 'NEBVSIN MANIFESTO',
                'line1' => $homeContent['manifesto_line1'] ?? "WE DON'T SELL CLOTHES.",
                'line2_prefix' => $homeContent['manifesto_line2_prefix'] ?? 'WE SELL ',
                'highlight' => $homeContent['manifesto_highlight'] ?? 'CONFLICT.',
                'intro' => [
                    'NEBVSIN is a world for people shaped by pressure.',
                    'People who are not perfect. People who have lived through pain, mistakes, and emptiness.',
                    'But still choose to move forward. This brand does not talk about being “good.”',
                    'It talks about truth: even a star has a dark side.',
                ],
                'meaning_aria' => 'NEBVSIN meaning',
                'values_aria' => 'NEBVSIN values',
                'neb_label' => 'FROM NEBULA.',
                'neb_copy' => [
                    'Dust, gas, and the remains of dead stars.',
                    'A place where “origin” and “collapse”',
                    'exist at the same time.',
                ],
                'rift_title' => 'THE V',
                'rift_label' => 'THE RIFT.',
                'rift_copy' => [
                    'Not just a separator. It is a “rift”',
                    'between light and darkness,',
                    'heaven and humanity,',
                    'beauty and decay.',
                ],
                'sin_label' => 'OUR HUMAN SINS.',
                'sin_copy' => [
                    'Greed',
                    'Obsession',
                    'Desire',
                    'Violence',
                    'Pain',
                    '',
                    'The darkness everyone carries,',
                    'but no one dares to admit.',
                ],
                'values' => [
                    ['icon' => '✦', 'lines' => ['DARK LUXURY', 'STREETWEAR']],
                    ['icon' => '†', 'lines' => ['LIMITED EDITION', 'COLLECTIBLE']],
                    ['icon' => '∞', 'lines' => ['HONEST', 'DESIGN']],
                    ['icon' => '♨', 'lines' => ['CONFLICT', 'IS BEAUTIFUL'], 'red' => true],
                    ['icon' => '◎', 'lines' => ['FROM THE DARK SIDE', 'OF THE LAND OF SMILES']],
                ],
            ],
            'th' => [
                'eyebrow' => 'NEBVSIN MANIFESTO',
                'line1' => 'เราไม่ได้ขายเสื้อผ้า.',
                'line2_prefix' => 'เราใส่ ',
                'highlight' => 'ความขัดแย้ง.',
                'intro' => [
                    'NEBVSIN คือโลกของคนที่เติบโตจากแรงกดดัน',
                    'คนที่ไม่ได้สมบูรณ์แบบ คนที่ผ่านความเจ็บ ความผิดพลาด ความว่างเปล่า',
                    'แต่ยังเลือกเดินต่อ แบรนด์นี้ไม่ได้พูดเรื่อง “ความดี”',
                    'มันพูดถึงความจริง ว่าแม้แต่ดวงดาว ก็ยังมีด้านมืด',
                ],
                'meaning_aria' => 'ความหมายของ NEBVSIN',
                'values_aria' => 'คุณค่าของ NEBVSIN',
                'neb_label' => 'FROM NEBULA.',
                'neb_copy' => [
                    'กลุ่มฝุ่น ก๊าซ และซากของดวงดาว',
                    'ที่ตายไปแล้ว สถานที่ที่',
                    '“จุดกำเนิด” และ “จุดดับสลาย”',
                    'อยู่พร้อมกัน',
                ],
                'rift_title' => 'THE V',
                'rift_label' => 'THE RIFT.',
                'rift_copy' => [
                    'ไม่ใช่แค่ตัวคั่น แต่มันคือ “รอยแยก”',
                    'ระหว่าง แสง กับ ความมืด',
                    'สวรรค์ กับ มนุษย์',
                    'ความงาม กับ ความเสื่อมสลาย',
                ],
                'sin_label' => 'OUR HUMAN SINS.',
                'sin_copy' => [
                    'ความโลภ',
                    'ความหลง',
                    'ความต้องการ',
                    'ความรุนแรง',
                    'ความเจ็บปวด',
                    '',
                    'ด้านมืดที่ทุกคนมี',
                    'แต่ไม่มีใครกล้ายอมรับ',
                ],
                'values' => [
                    ['icon' => '✦', 'lines' => ['DARK LUXURY', 'STREETWEAR']],
                    ['icon' => '†', 'lines' => ['LIMITED EDITION', 'COLLECTIBLE']],
                    ['icon' => '∞', 'lines' => ['HONEST', 'DESIGN']],
                    ['icon' => '♨', 'lines' => ['CONFLICT', 'IS BEAUTIFUL'], 'red' => true],
                    ['icon' => '◎', 'lines' => ['FROM THE DARK SIDE', 'OF THE LAND OF SMILES']],
                ],
            ],
        ];

        $manifestoCopy = $manifestoCopyByLocale[$storefrontLocale] ?? $manifestoCopyByLocale['en'];
    @endphp

    @if ($isShopPage)
        <section class="drop reveal in-view" id="drop" aria-labelledby="drop-title">
            <div class="section-head">
                <h1 id="drop-title">SHOP</h1>
            </div>
            <nav class="tier-filter" aria-label="Product level filter">
                <a class="{{ $selectedTier === 'all' ? 'is-active' : '' }}" href="{{ route('storefront.shop', ['lang' => $storefrontLocale]) }}" data-tier-filter="all">ALL</a>
                @foreach ($tierConfig as $tierKey => $tier)
                    <a class="{{ $selectedTier === $tierKey ? 'is-active' : '' }}" href="{{ route('storefront.shop', ['level' => $tierKey, 'lang' => $storefrontLocale]) }}" data-tier-filter="{{ $tierKey }}">{{ $tier['label'] }}</a>
                @endforeach
            </nav>
            <div class="product-grid" id="productGrid">
                @forelse ($products as $product)
                    @include('storefront.products._card', ['product' => $product])
                @empty
                    <p>No products are available yet.</p>
                @endforelse
            </div>
        </section>
    @else
    <div class="hp">
        <section class="hp-hero reveal in-view" aria-labelledby="hp-hero-title">
            <img class="hp-hero-bg" src="{{ $heroImage }}" alt="" aria-hidden="true" fetchpriority="high">
            <div class="hp-hero-inner">
                <div class="hp-hero-copy">
                    <p class="hp-eyebrow">{{ $homeContent['hero_eyebrow'] ?? 'NEW COLLECTION / 3 LEVELS OF EXPRESSION' }}</p>
                    <h1 id="hp-hero-title">NEBVSIN</h1>
                    <p class="hp-hero-sub">{{ $homeContent['hero_subtitle'] ?? '' }}</p>
                    <div class="hp-cta">
                        <a class="hp-btn" href="{{ route('storefront.shop', ['lang' => $storefrontLocale]) }}">{{ $homeContent['hero_cta_primary_label'] ?? 'Shop the Collection' }}</a>
                        {{-- <a class="hp-btn hp-btn--ghost" href="#hp-signature">{{ $homeContent['hero_cta_secondary_label'] ?? 'Explore Signature' }}</a> --}}
                    </div>
                </div>
            </div>
        </section>

        {{-- Start 3 Levels --}}
        {{-- เก็บไว้ก่อน ห้ามลบ --}}
        {{-- <section class="hp-section reveal in-view" id="levels" aria-labelledby="hp-levels-title">
            <div class="hp-container">
                <div class="hp-section-head">
                    <div>
                        <p class="hp-eyebrow">SHOP BY LEVEL</p>
                        <h2 class="hp-section-title" id="hp-levels-title">3 LEVELS OF EXPRESSION</h2>
                    </div>
                    <p class="hp-section-note">NEBVSIN moves from everyday minimal pieces to story-led core graphics and rare collectible artwork.</p>
                </div>
                <div class="hp-tiers">
                    @foreach ($tierConfig as $tierKey => $tier)
                        <a class="hp-tier @if ($tierKey === 'signature') hp-tier--signature @endif" href="{{ route('storefront.shop', ['level' => $tierKey, 'lang' => $storefrontLocale]) }}">
                            <div>
                                <p class="hp-tier-number">{{ $tier['number'] }} / {{ $tierEyebrow[$tierKey] ?? strtoupper($tierKey) }}</p>
                                <p class="hp-tier-name">{{ $tier['label'] }}</p>
                                <p class="hp-tier-tagline">{{ $tier['tagline'] }}</p>
                            </div>
                            <div>
                                <p class="hp-tier-price">{{ number_format($tier['starting_price']) }}<small>THB / STARTING PRICE</small></p>
                                <p class="hp-tier-link"><span>EXPLORE {{ $tier['label'] }}</span><span>&#8599;</span></p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section> --}}
        {{-- End 3 Levels --}}

        <section class="hp-section reveal in-view" aria-labelledby="hp-levels-title">
            <div class="hp-container">
                <div class="hp-section-head">
                    <div>
                        <p class="hp-eyebrow"></p>
                        <h2 class="hp-section-title">CORE COLLECTION</h2>
                    </div>
                    <a class="hp-section-link" href="{{ route('storefront.shop', ['level' => 'core', 'lang' => $storefrontLocale]) }}">VIEW ALL CORE</a>
                </div>
                <div class="hp-product-grid">
                    @forelse ($coreProducts as $product)
                        @include('storefront.products._card', ['product' => $product])
                    @empty
                        <p class="hp-empty">No core products are available yet.</p>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Start Section CORE --}}
        {{-- เก็บไว้ก่อน ห้ามลบ --}}
        {{-- <section class="hp-section reveal in-view" id="shop" aria-labelledby="hp-feature-title">
            <div class="hp-container">
                <div class="hp-section-head">
                    <div>
                        <p class="hp-eyebrow">{{ $homeContent['feature_eyebrow'] ?? '02 / CORE' }}</p>
                        <h2 class="hp-section-title" id="hp-feature-title">{{ $homeContent['feature_title'] ?? 'FEATURED CORE' }}</h2>
                    </div>
                    <p class="hp-section-note">{{ $homeContent['feature_note'] ?? '' }}</p>
                </div>
                <div class="hp-feature">
                    <a class="hp-feature-media" href="{{ $featureUrl }}">
                        <img src="{{ $featureImage }}" alt="{{ $featureProduct['name'] ?? ($homeContent['feature_heading_line1'] ?? 'Core') }} core artwork by NEBVSIN" loading="lazy">
                    </a>
                    <div class="hp-feature-copy">
                        <p class="hp-kicker">{{ $homeContent['feature_kicker'] ?? '02 / CORE COLLECTION' }}</p>
                        <h3>{{ $homeContent['feature_heading_line1'] ?? 'SHADOW' }}<br>{{ $homeContent['feature_heading_line2'] ?? 'IN MY TEETH' }}</h3>
                        <p>{{ $homeContent['feature_copy'] ?? '' }}</p>
                        <div class="hp-cta">
                            <a class="hp-btn" href="{{ $featureUrl }}">{{ $homeContent['feature_cta_label'] ?? 'Discover the Piece' }}</a>
                            <a class="hp-btn hp-btn--ghost" href="{{ route('storefront.shop', ['level' => 'core', 'lang' => $storefrontLocale]) }}">View All Core</a>
                        </div>
                    </div>
                </div>
            </div>
        </section> --}}
        {{-- End Section CORE --}}


        {{-- Start Section Signature --}}
        {{-- เก็บไว้ก่อน ห้ามลบ --}}
        {{-- <section class="hp-section reveal in-view" id="hp-signature" aria-labelledby="hp-signature-title">
            <div class="hp-container">
                <h2 class="hp-section-title">LIMITED EDITION</h2><br>
                <div class="hp-signature">
                    <div class="hp-signature-copy">
                        <p class="hp-kicker">{{ $homeContent['signature_kicker'] ?? '03 / SIGNATURE' }}</p>
                        <h3 id="hp-signature-title">{{ $homeContent['signature_heading_line1'] ?? 'SPLIT' }}<br>{{ $homeContent['signature_heading_line2'] ?? 'MIND' }}</h3>
                        <p class="hp-limited">{{ $homeContent['signature_limited_line1'] ?? 'LIMITED EDITION' }}<br>{{ $homeContent['signature_limited_line2'] ?? '60 PIECES WORLDWIDE' }}<br>{{ $homeContent['signature_limited_line3'] ?? 'NO RESTOCK' }}</p>
                        <p>{{ $homeContent['signature_copy'] ?? '' }}</p>
                        <div class="hp-cta">
                            <a class="hp-btn" href="{{ $signatureUrl }}">{{ $homeContent['signature_cta_label'] ?? 'View Signature Piece' }}</a>
                        </div>
                    </div>
                    <a class="hp-signature-media" href="{{ $signatureUrl }}">
                        <img src="{{ $signatureImage }}" alt="NEBVSIN signature limited artwork" loading="lazy">
                    </a>
                </div>
            </div>
        </section> --}}
        {{-- End Section Signature --}}

        
        <section style="border-bottom: 1px solid var(--hp-line);">
            <div class="hp-manifesto-values" aria-label="{{ $manifestoCopy['values_aria'] }}">
                @foreach ($manifestoCopy['values'] as $value)
                    <div class="hp-manifesto-value @if (! empty($value['red'])) hp-manifesto-value--red @endif">
                        <span>{{ $value['icon'] }}</span>
                        <p>
                            @foreach ($value['lines'] as $line)
                                {{ $line }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
        
        <section class="hp-manifesto reveal in-view" id="manifesto" aria-labelledby="hp-manifesto-title">
            <div class="hp-container">
                <div class="hp-manifesto-hero">
                    <p class="hp-eyebrow">{{ $manifestoCopy['eyebrow'] }}</p>
                    <blockquote id="hp-manifesto-title">
                        <span>{{ $manifestoCopy['line1'] }}</span>
                        <span>{{ $manifestoCopy['line2_prefix'] }}<b>{{ $manifestoCopy['highlight'] }}</b></span>
                    </blockquote>
                    <p class="hp-manifesto-intro">
                        @foreach ($manifestoCopy['intro'] as $line)
                            {{ $line }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </p>
                </div>

                <div class="hp-manifesto-core" aria-label="{{ $manifestoCopy['meaning_aria'] }}">
                    <article class="hp-manifesto-panel hp-manifesto-panel--neb">
                        <h3>NEB</h3>
                        <p class="hp-manifesto-label">{{ $manifestoCopy['neb_label'] }}</p>
                        <span class="hp-manifesto-divider" aria-hidden="true"></span>
                        <p>
                            @foreach ($manifestoCopy['neb_copy'] as $line)
                                {{ $line }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </p>
                    </article>

                    <div class="hp-manifesto-rift">
                        <span class="hp-manifesto-v" aria-hidden="true">V</span>
                        <div class="hp-manifesto-rift-copy">
                            <h3>{{ $manifestoCopy['rift_title'] }}</h3>
                            <p class="hp-manifesto-rift-label">{{ $manifestoCopy['rift_label'] }}</p>
                            <p>
                                @foreach ($manifestoCopy['rift_copy'] as $line)
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </p>
                        </div>
                    </div>

                    <article class="hp-manifesto-panel hp-manifesto-panel--sin">
                        <h3>SIN</h3>
                        <p class="hp-manifesto-label">{{ $manifestoCopy['sin_label'] }}</p>
                        <span class="hp-manifesto-divider" aria-hidden="true"></span>
                        <p>
                            @foreach ($manifestoCopy['sin_copy'] as $line)
                                @if ($line === '')
                                    <br>
                                @else
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endif
                            @endforeach
                        </p>
                    </article>
                </div>
            </div>
        </section>
    </div>
    @endif
@endsection

@push('meta')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var filters = document.querySelectorAll('[data-tier-filter]');
            var cards = document.querySelectorAll('[data-product-card]');

            if (filters.length && cards.length) {
                function applyTierFilter(tier) {
                    filters.forEach(function (filter) {
                        filter.classList.toggle('is-active', filter.getAttribute('data-tier-filter') === tier);
                    });

                    cards.forEach(function (card) {
                        var matches = tier === 'all' || card.getAttribute('data-tier') === tier;
                        card.hidden = !matches;
                    });
                }

                applyTierFilter(@json($selectedTier));

                filters.forEach(function (filter) {
                    filter.addEventListener('click', function (event) {
                        if (!window.history || !window.history.pushState) {
                            return;
                        }

                        event.preventDefault();
                        var tier = filter.getAttribute('data-tier-filter') || 'all';
                        var url = new URL(filter.href);
                        window.history.pushState({ tier: tier }, '', url.toString());
                        applyTierFilter(tier);
                    });
                });

                window.addEventListener('popstate', function (event) {
                    applyTierFilter((event.state && event.state.tier) || new URL(window.location.href).searchParams.get('level') || 'all');
                });
            }
        });
    </script>
@endpush
