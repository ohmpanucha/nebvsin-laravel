@extends('layouts.storefront')

@php
    $formatAmount = function ($amount) {
        return number_format((float) $amount, 2).' THB';
    };
@endphp

@section('content')
    <section class="checkout-shell">
        <article class="checkout-card reveal in-view">
            <div class="checkout-intro">
                <p class="address-kicker">{{ $copy['kicker'] }}</p>
                <h1 class="address-title">{{ $copy['title'] }}</h1>
                <p class="address-caption">{{ $copy['caption'] }}</p>
            </div>

            @if ($cartStatus)
                <p class="address-status">{{ $cartStatus }}</p>
            @endif

            @if ($cartItems)
                <div class="checkout-layout">
                    <section class="checkout-panel" aria-labelledby="checkout-summary-title">
                        <div class="checkout-summary-head">
                            <h2 id="checkout-summary-title">{{ $copy['summary_title'] }}</h2>
                            <p>{{ str_replace(':count', str_pad((string) $cartTotalItems, 2, '0', STR_PAD_LEFT), $copy['summary_items']) }}</p>
                        </div>

                        <div class="checkout-summary-table-wrap">
                            <table class="checkout-summary-table">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ $copy['header_image'] }}</th>
                                        <th scope="col">{{ $copy['header_item'] }}</th>
                                        <th scope="col">{{ $copy['header_size'] }}</th>
                                        <th scope="col">{{ $copy['header_qty'] }}</th>
                                        <th scope="col">{{ $copy['header_unit_price'] }}</th>
                                        <th scope="col">{{ $copy['header_total'] }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cartItems as $item)
                                        <tr>
                                            <td>
                                                @if (!empty($item['image_url'] ?? $item['image']))
                                                    <img
                                                        class="checkout-summary-thumb"
                                                        src="{{ $item['image_url'] ?? $item['image'] }}"
                                                        alt="{{ $item['name'] }}"
                                                        loading="lazy"
                                                    >
                                                @endif
                                            </td>
                                            <td>{{ $item['name'] }}</td>
                                            <td>{{ $item['size'] ?? '-' }}</td>
                                            <td>{{ $item['qty'] }}</td>
                                            <td>{{ $formatAmount($item['price_thb']) }}</td>
                                            <td>{{ $formatAmount(($item['price_thb'] ?? 0) * ($item['qty'] ?? 0)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="checkout-summary-total-row">
                            <p class="checkout-summary-total">{{ $copy['summary_subtotal'] }}</p>
                            <p class="checkout-summary-total">{{ $formatAmount($cartSubtotal) }}</p>
                        </div>

                        <div class="checkout-highlight-grid">
                            <div class="checkout-highlight-card">
                                <p class="detail-label">{{ $copy['payment_label'] }}</p>
                                <p>{{ $copy['payment_copy'] }}</p>
                            </div>
                            <div class="checkout-highlight-card">
                                <p class="detail-label">{{ $copy['delivery_label'] }}</p>
                                <p>{{ $copy['delivery_copy'] }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="checkout-panel" aria-labelledby="shipping-address-title">
                        <div class="checkout-summary-head">
                            <h2 id="shipping-address-title">{{ $copy['shipping_title'] }}</h2>
                            <p>{{ $copy['shipping_live'] }}</p>
                        </div>

                        <form method="post" action="{{ route('storefront.checkout.submit', ['lang' => $storefrontLocale]) }}" class="address-form checkout-form-grid" data-checkout-form>
                            @csrf

                            <label for="checkout-full-name">{{ $copy['full_name'] }}</label>
                            <input id="checkout-full-name" type="text" name="full_name" placeholder="{{ $copy['full_name'] }}" value="{{ old('full_name', $address['full_name'] ?? '') }}" required>

                            <label for="checkout-phone">{{ $copy['phone'] }}</label>
                            <input id="checkout-phone" type="text" name="phone" placeholder="{{ $copy['phone'] }}" value="{{ old('phone', $address['phone'] ?? '') }}" required>

                            <label for="checkout-address-line1">{{ $copy['address'] }}</label>
                            <textarea id="checkout-address-line1" name="address_line1" rows="5" placeholder="{{ $copy['address'] }}" required>{{ old('address_line1', $address['address_line1'] ?? '') }}</textarea>

                            <label for="checkout-address-line2">{{ $copy['address_line_2'] }}</label>
                            <input id="checkout-address-line2" type="text" name="address_line2" placeholder="{{ $copy['address_line_2'] }}" value="{{ old('address_line2', $address['address_line2'] ?? '') }}">

                            <div class="checkout-field-row">
                                <div>
                                    <label for="checkout-district">{{ $copy['district'] }}</label>
                                    <input id="checkout-district" type="text" name="district" placeholder="{{ $copy['district'] }}" value="{{ old('district', $address['district'] ?? '') }}" required>
                                </div>
                                <div>
                                    <label for="checkout-province">{{ $copy['province'] }}</label>
                                    <input id="checkout-province" type="text" name="province" placeholder="{{ $copy['province'] }}" value="{{ old('province', $address['province'] ?? '') }}" required>
                                </div>
                            </div>

                            <label for="checkout-postal-code">{{ $copy['postal_code'] }}</label>
                            <input id="checkout-postal-code" type="text" name="postal_code" placeholder="{{ $copy['postal_code'] }}" value="{{ old('postal_code', $address['postal_code'] ?? '') }}" required>

                            <div class="checkout-actions">
                                <button type="submit" class="cta add-cart luxury-hover" data-checkout-submit>{{ $copy['place_order'] }}</button>
                                <a href="{{ route('storefront.cart', ['lang' => $storefrontLocale]) }}" class="detail-secondary-link">{{ $copy['back_to_cart'] }}</a>
                            </div>
                        </form>
                    </section>
                </div>
            @else
                <p class="admin-empty">{{ $copy['summary_empty'] }}</p>
            @endif
        </article>
    </section>

    @if ($cartItems)
        <div class="admin-slip-modal" data-checkout-confirm-modal hidden>
            <button type="button" class="admin-slip-modal-backdrop" data-checkout-confirm-close aria-label="Close order confirmation"></button>
            <section class="admin-slip-modal-card cart-confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="checkout-confirm-title">
                <header class="admin-slip-modal-head">
                    <h2 id="checkout-confirm-title">{{ $copy['confirm_title'] }}</h2>
                    <button type="button" data-checkout-confirm-close aria-label="Close order confirmation">X</button>
                </header>
                <p class="cart-confirm-copy">{{ $copy['confirm_copy'] }}</p>
                <div class="cart-confirm-actions">
                    <button type="button" class="cart-remove-btn cart-confirm-cancel" data-checkout-confirm-close>{{ $copy['confirm_cancel'] }}</button>
                    <button type="button" class="cart-checkout-btn" data-checkout-confirm-submit>{{ $copy['place_order'] }}</button>
                </div>
            </section>
        </div>
    @endif
@endsection

@if ($cartItems)
    @push('meta')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.querySelector('[data-checkout-form]');
                var modal = document.querySelector('[data-checkout-confirm-modal]');
                var confirmButton = document.querySelector('[data-checkout-confirm-submit]');
                var closeButtons = document.querySelectorAll('[data-checkout-confirm-close]');
                var pendingSubmitter = null;
                var isConfirmed = false;

                if (!form || !modal || !confirmButton) {
                    return;
                }

                var closeModal = function () {
                    modal.hidden = true;
                };

                form.addEventListener('submit', function (event) {
                    if (isConfirmed) {
                        isConfirmed = false;
                        return;
                    }

                    event.preventDefault();
                    pendingSubmitter = event.submitter || form.querySelector('[data-checkout-submit]');
                    modal.hidden = false;
                });

                closeButtons.forEach(function (button) {
                    button.addEventListener('click', closeModal);
                });

                confirmButton.addEventListener('click', function () {
                    isConfirmed = true;
                    closeModal();

                    if (typeof form.requestSubmit === 'function' && pendingSubmitter) {
                        form.requestSubmit(pendingSubmitter);
                        return;
                    }

                    form.submit();
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.hidden === false) {
                        closeModal();
                    }
                });
            });
        </script>
    @endpush
@endif
