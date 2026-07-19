@php
    $drawerItems = $storefrontCartItems ?? [];
    $drawerCount = $storefrontCartCount ?? 0;
    $drawerSubtotal = $storefrontCartSubtotal ?? 0;
    $drawerOpen = $cartDrawerOpen ?? false;
    $drawerCloseUrl = $cartDrawerCloseUrl ?? route('storefront.home', ['lang' => $storefrontLocale]);
@endphp

<section class="cart-page cart-page-fixed {{ $drawerOpen ? 'is-open' : '' }}" aria-hidden="{{ $drawerOpen ? 'false' : 'true' }}">
    <a href="{{ $drawerCloseUrl }}" class="cart-backdrop" aria-label="Close cart and return to storefront"></a>

    <article class="cart-card reveal in-view" aria-labelledby="cart-title">
        <header class="cart-head">
            <div>
                <p class="eyebrow">STORE CART</p>
                <h2 id="cart-title" class="cart-title">CART</h2>
            </div>
            <a href="{{ $drawerCloseUrl }}" class="cart-close">CLOSE</a>
        </header>

        <div class="cart-body" data-cart-drawer>
            <p class="cart-status{{ session('cart_status') ? '' : ' is-hidden' }}" data-cart-status>{{ session('cart_status') }}</p>

            <div class="cart-list{{ $drawerItems ? '' : ' is-hidden' }}" data-cart-list>
                @foreach ($drawerItems as $item)
                    <section class="cart-item" data-cart-item data-cart-key="{{ $item['key'] }}" data-cart-price="{{ (int) $item['price_thb'] }}">
                        <div class="cart-item-media">
                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
                        </div>
                        <div class="cart-item-main">
                            <div class="cart-item-topline">
                                <div class="cart-item-meta">
                                    <p class="cart-item-tier">{{ $item['tier_label'] ?? 'CORE' }}</p>
                                    <p>{{ $item['name'] }}</p>
                                    <p>{{ $item['price_label'] }}</p>
                                    <p>SIZE / {{ $item['size'] }}</p>
                                    @if (($item['tier'] ?? '') === 'signature' || ($item['is_limited'] ?? false))
                                        <p class="cart-item-limited">{{ ($item['tier'] ?? '') === 'signature' ? 'SIGNATURE PRODUCT' : 'LIMITED EDITION' }}</p>
                                    @endif
                                    @if (($item['packaging'] ?? '') === 'premium')
                                        <p>PREMIUM PACKAGING INCLUDED</p>
                                    @endif
                                    <p class="cart-item-line-total" data-cart-line-subtotal>{{ number_format(((int) $item['price_thb']) * ((int) $item['qty'])) }} THB</p>
                                </div>
                                <form method="post" action="{{ route('storefront.cart.remove', ['key' => $item['key'], 'lang' => $storefrontLocale, 'cart' => 'open']) }}" class="cart-remove-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cart-remove-btn" aria-label="Remove {{ $item['name'] }}">REMOVE</button>
                                </form>
                            </div>

                            <form method="post" action="{{ route('storefront.cart.update', ['key' => $item['key'], 'lang' => $storefrontLocale, 'cart' => 'open']) }}" class="cart-inline-form" data-cart-qty-form>
                                @csrf
                                @method('PATCH')
                                <span class="cart-inline-label">QTY</span>
                                <div class="cart-qty-field">
                                    <button type="submit" class="cart-step-btn" name="qty" value="{{ max(0, ((int) $item['qty']) - 1) }}" data-cart-step="decrement" aria-label="Decrease quantity for {{ $item['name'] }}">−</button>
                                    <span class="cart-qty-value" data-cart-item-qty>{{ (int) $item['qty'] }}</span>
                                    <button type="submit" class="cart-step-btn" name="qty" value="{{ ((int) $item['qty']) + 1 }}" data-cart-step="increment" aria-label="Increase quantity for {{ $item['name'] }}">+</button>
                                </div>
                            </form>
                        </div>
                    </section>
                @endforeach
            </div>

            <p class="cart-empty{{ $drawerItems ? ' is-hidden' : '' }}" data-cart-empty>No items in cart.</p>
        </div>

        <footer class="cart-foot{{ $drawerItems ? '' : ' is-empty' }}" data-cart-summary>
            <div class="cart-summary-row">
                <p>TOTAL ITEMS</p>
                <strong data-cart-total-items>{{ str_pad((string) $drawerCount, 2, '0', STR_PAD_LEFT) }}</strong>
            </div>
            <div class="cart-summary-row is-total">
                <p>SUBTOTAL</p>
                <strong data-cart-subtotal>{{ number_format($drawerSubtotal) }} THB</strong>
            </div>

            @if ($drawerItems)
                @if ($storefrontAuthUser)
                    <a
                        href="{{ route('storefront.checkout', ['lang' => $storefrontLocale]) }}"
                        class="cart-checkout-btn"
                        data-cart-checkout
                    >CHECKOUT</a>
                @else
                    <a
                        href="{{ route('storefront.login', ['next' => route('storefront.checkout', ['lang' => $storefrontLocale]), 'lang' => $storefrontLocale]) }}"
                        class="cart-checkout-btn"
                        data-cart-checkout
                    >LOGIN TO CHECKOUT</a>
                @endif
            @endif
        </footer>
    </article>
</section>

<div class="admin-slip-modal" data-cart-confirm-modal hidden>
    <button type="button" class="admin-slip-modal-backdrop" data-cart-confirm-close aria-label="Close checkout confirmation"></button>
    <section class="admin-slip-modal-card cart-confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="cart-confirm-title">
        <header class="admin-slip-modal-head">
            <h2 id="cart-confirm-title">CHECKOUT</h2>
            <button type="button" data-cart-confirm-close aria-label="Close checkout confirmation">X</button>
        </header>
        <p class="cart-confirm-copy">Review your cart before continuing.</p>
        <div class="cart-confirm-actions">
            <button type="button" class="cart-remove-btn cart-confirm-cancel" data-cart-confirm-close>CANCEL</button>
            <a href="#" class="cart-checkout-btn" data-cart-confirm-submit>CONTINUE</a>
        </div>
    </section>
</div>

@push('meta')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var drawer = document.querySelector('[data-cart-drawer]');
            if (!drawer) {
                return;
            }

            var totalItems = document.querySelector('[data-cart-total-items]');
            var subtotal = document.querySelector('[data-cart-subtotal]');
            var emptyState = document.querySelector('[data-cart-empty]');
            var list = document.querySelector('[data-cart-list]');
            var status = document.querySelector('[data-cart-status]');
            var summary = document.querySelector('[data-cart-summary]');
            var countBadge = document.querySelector('.cart-count-badge');
            var checkout = document.querySelector('.cart-checkout-btn');
            var checkoutTriggers = document.querySelectorAll('[data-cart-checkout]');
            var confirmModal = document.querySelector('[data-cart-confirm-modal]');
            var confirmSubmit = confirmModal ? confirmModal.querySelector('[data-cart-confirm-submit]') : null;
            var confirmClose = confirmModal ? confirmModal.querySelectorAll('[data-cart-confirm-close]') : [];
            if (confirmModal && confirmSubmit && checkoutTriggers.length) {
                checkoutTriggers.forEach(function (trigger) {
                    trigger.addEventListener('click', function (event) {
                        event.preventDefault();
                        confirmSubmit.setAttribute('href', trigger.getAttribute('href') || '#');
                        confirmModal.hidden = false;
                    });
                });

                confirmClose.forEach(function (control) {
                    control.addEventListener('click', function () {
                        confirmModal.hidden = true;
                    });
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && confirmModal.hidden === false) {
                        confirmModal.hidden = true;
                    }
                });
            }

            drawer.querySelectorAll('[data-cart-qty-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    var submitter = event.submitter;
                    if (!submitter || !submitter.name || submitter.name !== 'qty') {
                        return;
                    }

                    event.preventDefault();

                    var formData = new FormData(form);
                    formData.set('qty', submitter.value);

                    var buttons = form.querySelectorAll('button');
                    buttons.forEach(function (button) {
                        button.disabled = true;
                    });

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: (function () {
                            formData.set('_method', 'PATCH');
                            return formData;
                        })()
                    }).then(function (response) {
                        if (!response.ok) {
                            throw new Error('Cart update failed');
                        }

                        return response.json();
                    }).then(function (payload) {
                        var item = form.closest('[data-cart-item]');
                        if (!item || !payload || !payload.cart) {
                            return;
                        }

                        if (payload.removed) {
                            item.remove();
                        } else if (payload.item) {
                            var qtyValue = item.querySelector('[data-cart-item-qty]');
                            var subtotalValue = item.querySelector('[data-cart-line-subtotal]');
                            var decrement = item.querySelector('[data-cart-step="decrement"]');
                            var increment = item.querySelector('[data-cart-step="increment"]');

                            if (qtyValue) {
                                qtyValue.textContent = String(payload.item.qty);
                            }

                            if (subtotalValue) {
                                subtotalValue.textContent = payload.item.line_subtotal_label;
                            }

                            if (decrement) {
                                decrement.value = String(Math.max(0, payload.item.qty - 1));
                            }

                            if (increment) {
                                increment.value = String(payload.item.qty + 1);
                            }
                        }

                        if (totalItems) {
                            totalItems.textContent = payload.cart.total_items_label;
                        }

                        if (subtotal) {
                            subtotal.textContent = payload.cart.subtotal_label;
                        }

                        if (countBadge) {
                            countBadge.textContent = String(payload.cart.total_items).padStart(2, '0');
                        }

                        if (status) {
                            status.textContent = payload.status || '';
                            status.classList.toggle('is-hidden', !payload.status);
                        }

                        var hasItems = !payload.cart.is_empty;
                        if (list) {
                            list.classList.toggle('is-hidden', !hasItems);
                        }

                        if (emptyState) {
                            emptyState.classList.toggle('is-hidden', hasItems);
                        }

                        if (summary) {
                            summary.classList.toggle('is-empty', !hasItems);
                        }

                        if (checkout) {
                            checkout.classList.toggle('is-hidden', !hasItems);
                        }
                    }).catch(function () {
                        form.submit();
                    }).finally(function () {
                        buttons.forEach(function (button) {
                            button.disabled = false;
                        });
                    });
                });
            });
        });
    </script>
@endpush
