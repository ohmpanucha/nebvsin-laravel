@extends('layouts.storefront')

@section('content')
    <section class="address-page">
        <article class="address-card reveal in-view" aria-labelledby="account-address-title">
            <p class="address-kicker">{{ $copy['kicker'] }}</p>
            <h1 id="account-address-title" class="address-title">{{ $copy['title'] }}</h1>
            <p class="address-caption">{{ $copy['caption'] }}</p>

            <form class="address-form" method="post" action="{{ route('storefront.account.address.update', ['lang' => $storefrontLocale]) }}">
                @csrf
                <label for="full_name">{{ $copy['full_name'] }}</label>
                <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $address['full_name'] ?? '') }}" required>

                <label for="phone">{{ $copy['phone'] }}</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $address['phone'] ?? '') }}" required>

                <label for="address_line1">{{ $copy['address'] }}</label>
                <textarea id="address_line1" name="address_line1" rows="5" required>{{ old('address_line1', $address['address_line1'] ?? '') }}</textarea>

                <label for="address_line2">{{ $copy['address_line_2'] }}</label>
                <input id="address_line2" name="address_line2" type="text" value="{{ old('address_line2', $address['address_line2'] ?? '') }}">

                <div class="address-row">
                    <div>
                        <label for="district">{{ $copy['district'] }}</label>
                        <input id="district" name="district" type="text" value="{{ old('district', $address['district'] ?? '') }}" required>
                    </div>
                    <div>
                        <label for="province">{{ $copy['province'] }}</label>
                        <input id="province" name="province" type="text" value="{{ old('province', $address['province'] ?? '') }}" required>
                    </div>
                    <div>
                        <label for="postal_code">{{ $copy['postal_code'] }}</label>
                        <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $address['postal_code'] ?? '') }}" required>
                    </div>
                </div>

                <div class="address-actions">
                    <button type="submit">{{ $copy['save'] }}</button>
                </div>
            </form>

            <p class="address-status" aria-live="polite">{{ $statusMessage ?: ($address ? $copy['status_loaded'] : $copy['status_empty']) }}</p>
        </article>
    </section>
@endsection
