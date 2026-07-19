@extends('layouts.storefront')

@section('content')
    <section class="collections-page reveal in-view" aria-labelledby="collections-title">
        <div class="collections-intro">
            <p class="eyebrow">PRODUCT LEVEL ARCHITECTURE</p>
            <h1 id="collections-title" class="collections-title">COLLECTION</h1>
            <p class="collections-copy">350 = ESSENTIAL. 550 = CORE. 750 = SIGNATURE.</p>
        </div>

        <div class="collection-tier-grid">
            @foreach ($tierConfig as $tierKey => $tier)
                <a class="collection-tier collection-tier--{{ $tierKey }} {{ $selectedTier === $tierKey ? 'is-active' : '' }}" href="{{ route('storefront.collections.tier', ['tier' => $tierKey, 'lang' => $storefrontLocale]) }}">
                    <span>{{ $tier['number'] }}</span>
                    <h2>{{ $tier['label'] }}</h2>
                    <p>{{ number_format($tier['starting_price']) }} THB</p>
                    <p>{{ $tier['short'] }}</p>
                    <strong>VIEW COLLECTION</strong>
                </a>
            @endforeach
        </div>

        @if ($selectedTier)
            <section class="collection-products" aria-labelledby="collection-products-title">
                <div class="section-head">
                    <h2 id="collection-products-title">{{ $tierConfig[$selectedTier]['label'] }} PRODUCTS</h2>
                </div>
                <div class="product-grid">
                    @forelse ($products as $product)
                        @include('storefront.products._card', ['product' => $product])
                    @empty
                        <p class="admin-empty">No products are available in this level yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </section>
@endsection
