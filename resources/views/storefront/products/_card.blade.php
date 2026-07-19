@php
    $tierClass = 'card--'.($product['tier'] ?? 'core');
    $colorCount = count($product['colors'] ?? []);
    $editionTotal = (int) ($product['edition_total'] ?? 0);
    $limitedQty = (int) ($product['limited_qty'] ?? 0);
    $limitedTotal = $editionTotal > 0 ? $editionTotal : $limitedQty;
@endphp

<article class="card product-card {{ $tierClass }} {{ $product['coming_soon'] ? 'is-coming-soon' : '' }}" data-product-card data-tier="{{ $product['tier'] }}">
    <div class="card-head">
        <span class="tier-badge tier-badge--{{ $product['tier'] }}">
            {{-- {{ $product['tier_number'] }}  --}}
            {{ $product['tier_label'] }}
        </span>
        @if (($product['tier'] ?? '') === 'signature' && ($product['is_limited'] ?? false))
            <span class="card-edition-label">LIMITED</span>
        @endif
    </div>

    <p class="card-name">{{ $product['name'] }}</p>
    <p class="card-price">{{ $product['price_label'] }}</p>

    @if ($product['coming_soon'])
        <div class="card-link is-coming-soon">
            <img src="{{ $product['image_url'] }}" alt="{{ $product['alt'] }}" loading="lazy">
        </div>
        <h4 class="card-soon">{{ $copy['coming_soon'] ?? 'SOON' }}</h4>
    @else
        <a class="card-link" href="{{ route('storefront.products.show', ['identifier' => $product['slug'], 'lang' => $storefrontLocale]) }}">
            @if (($product['tier'] ?? '') === 'signature' && $limitedTotal > 0)
                <span class="card-status">LIMITED {{ $limitedTotal }} PIECES</span>
            @elseif (($product['is_limited'] ?? false) && $limitedQty > 0)
                <span class="card-status">{{ $copy['limited'] ?? 'LIMITED' }} {{ $limitedQty }}</span>
            @endif
            <img src="{{ $product['image_url'] }}" alt="{{ $product['alt'] }}" loading="lazy">
            <span class="card-view">{{ $copy['view_case'] ?? 'VIEW CASE' }}</span>
        </a>
    @endif

    <div class="card-foot">
        <p>{{ $product['meta_description'] }}</p>
        @if ($colorCount > 0)
            <span>{{ $colorCount }} {{ $colorCount === 1 ? 'COLOR' : 'COLORS' }}</span>
        @elseif (($product['tier'] ?? '') === 'signature' && $limitedTotal > 0)
            <span>NO RESTOCK</span>
        @endif
    </div>
</article>
