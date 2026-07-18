<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? 'NEBVSIN' }}</title>
    <meta name="description" content="{{ $pageDescription ?? 'NEBVSIN storefront' }}">
    <link rel="canonical" href="{{ $canonicalUrl ?? request()->url() }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $pageTitle ?? 'NEBVSIN' }}">
    <meta property="og:description" content="{{ $pageDescription ?? 'NEBVSIN storefront' }}">
    <meta property="og:url" content="{{ $canonicalUrl ?? request()->url() }}">
    @if (! empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    @if (!empty($robotsMeta))
        <meta name="robots" content="{{ $robotsMeta }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
    @stack('meta')
</head>
@php
    $cartDrawerOpen = request()->routeIs('storefront.cart') || request()->query('cart') === 'open';
    $cartDrawerCloseUrl = request()->routeIs('storefront.cart')
        ? route('storefront.home', ['lang' => $storefrontLocale])
        : request()->fullUrlWithQuery(['cart' => null]);
    $isProcessPage = request()->routeIs('storefront.process');
    $showFloatingCart = ! request()->routeIs('storefront.checkout')
        && ! request()->routeIs('storefront.checkout.payment')
        && ! request()->routeIs('admin.*');
@endphp
<body class="{{ $cartDrawerOpen ? 'cart-drawer-open' : '' }}">
    <a class="skip-link" href="#main-content">{{ $storefrontLayoutCopy['skip_to_content'] ?? 'Skip to content' }}</a>
    <div class="noise-overlay" aria-hidden="true"></div>

    <header class="site-header reveal in-view">
        <nav class="top-nav" aria-label="Primary">
            <a href="{{ route('storefront.home', ['lang' => $storefrontLocale]) }}" class="brand" aria-label="{{ $storefrontLayoutCopy['home_aria'] ?? 'Go to homepage' }}">NEBVSIN</a>
            <button
                type="button"
                class="hamburger"
                data-nav-toggle
                aria-label="{{ $storefrontLayoutCopy['menu_open'] ?? 'Open menu' }}"
                aria-expanded="false"
                aria-controls="site-navigation"
            >
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
            <div id="site-navigation" class="nav-groups" data-nav-groups>
                <ul class="nav-list nav-main">
                    <li><a href="{{ route('storefront.home', ['lang' => $storefrontLocale]) }}#drop" data-nav-section-link="drop" data-mobile-close>{{ $storefrontLayoutCopy['nav_drop'] ?? 'DROP' }}</a></li>
                    <li><a href="{{ route('storefront.home', ['lang' => $storefrontLocale]) }}#manifesto" data-nav-section-link="manifesto" data-mobile-close>{{ $storefrontLayoutCopy['nav_about'] ?? 'ABOUT' }}</a></li>
                    <li><a href="{{ route('storefront.process', ['lang' => $storefrontLocale]) }}" class="{{ $isProcessPage ? 'is-active' : '' }}" data-mobile-close>{{ $storefrontLayoutCopy['nav_process'] ?? 'PROCESS' }}</a></li>
                </ul>
                <ul class="nav-list nav-utility">
                    <li>
                        <div class="locale-switcher" role="group" aria-label="{{ $storefrontLayoutCopy['language_switcher'] ?? 'Language switcher' }}">
                            <a
                                class="locale-switch-btn {{ $storefrontLocale === 'en' ? 'is-active' : '' }}"
                                href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                            >EN</a>
                            <a
                                class="locale-switch-btn {{ $storefrontLocale === 'th' ? 'is-active' : '' }}"
                                href="{{ request()->fullUrlWithQuery(['lang' => 'th']) }}"
                            >TH</a>
                        </div>
                    </li>
                    @if ($storefrontAuthUser)
                        <li class="profile-menu-item">
                            <details class="profile-menu">
                                <summary class="profile-menu-trigger">
                                    {{ ($storefrontAuthUser['role'] ?? 'user') === 'admin' ? ($storefrontLayoutCopy['admin_menu'] ?? 'ADMIN') : ($storefrontLayoutCopy['profile'] ?? 'PROFILE') }}
                                </summary>
                                <div class="profile-menu-panel">
                                    @if (($storefrontAuthUser['role'] ?? 'user') === 'admin')
                                        <a href="{{ route('admin.procurement', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['admin_procurement'] ?? 'ADMIN PROCUREMENT' }}</a>
                                        <a href="{{ route('admin.products', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['admin_products'] ?? 'ADMIN PRODUCTS' }}</a>
                                        <a href="{{ route('admin.customers', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['admin_customers'] ?? 'ADMIN CUSTOMERS' }}</a>
                                        <a href="{{ route('admin.payments', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['admin_payments'] ?? 'ADMIN PAYMENTS' }}</a>
                                        <a href="{{ route('admin.shipping', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['admin_shipping'] ?? 'ADMIN SHIPPING' }}</a>
                                        <a href="{{ route('admin.orders', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['admin_orders'] ?? 'ADMIN ORDERS' }}</a>
                                        <div class="profile-menu-separator" aria-hidden="true"></div>
                                    @else
                                        <p class="profile-menu-meta">{{ $storefrontAuthUser['email'] ?? '' }}</p>
                                        <p class="profile-menu-meta">{{ str_replace(':role', (string) ($storefrontAuthUser['role'] ?? 'user'), $storefrontLayoutCopy['role_label'] ?? 'ROLE: user') }}</p>
                                        <div class="profile-menu-separator" aria-hidden="true"></div>
                                        <a href="{{ route('storefront.account.purchase-history', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['purchase_history'] ?? 'PURCHASE HISTORY' }}</a>
                                        <a href="{{ route('storefront.account.address', ['lang' => $storefrontLocale]) }}">{{ $storefrontLayoutCopy['my_address'] ?? 'MY ADDRESS' }}</a>
                                        <div class="profile-menu-separator" aria-hidden="true"></div>
                                    @endif
                                    <form method="post" action="{{ route('storefront.logout', ['lang' => $storefrontLocale]) }}" class="inline-form profile-menu-form">
                                        @csrf
                                        <button type="submit" class="nav-link-button profile-menu-button">{{ $storefrontLayoutCopy['logout'] ?? 'LOGOUT' }}</button>
                                    </form>
                                </div>
                            </details>
                        </li>
                    @else
                        <li><a href="{{ route('storefront.login', ['next' => request()->path() === '/' ? '/' : request()->getRequestUri(), 'lang' => $storefrontLocale]) }}" data-mobile-close>{{ $storefrontLayoutCopy['login'] ?? 'LOGIN' }}</a></li>
                        <li><a href="{{ route('storefront.register', ['next' => request()->path() === '/' ? '/' : request()->getRequestUri(), 'lang' => $storefrontLocale]) }}" data-mobile-close>{{ $storefrontLayoutCopy['register'] ?? 'REGISTER' }}</a></li>
                    @endif
                </ul>
            </div>
        </nav>
    </header>
    <button type="button" class="mobile-nav-backdrop" data-nav-backdrop aria-hidden="true" tabindex="-1"></button>

    @if ($showFloatingCart)
        <div data-cart-menu>
            <a href="{{ request()->fullUrlWithQuery(['cart' => 'open']) }}" aria-label="Open cart">
                <svg class="cart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M3.5 4h2.1l1.9 9.2a1 1 0 0 0 1 .8h8.4a1 1 0 0 0 1-.8L20 7H7.2"></path>
                    <circle cx="10" cy="18.5" r="1.6"></circle>
                    <circle cx="17" cy="18.5" r="1.6"></circle>
                </svg>
                <span class="cart-count-badge">{{ str_pad((string) ($storefrontCartCount ?? 0), 2, '0', STR_PAD_LEFT) }}</span>
            </a>
        </div>
    @endif

    <main id="main-content">
        @yield('content')
    </main>

    @include('storefront.cart._drawer')

    <footer class="site-footer reveal in-view">
        <div class="footer-links">
            <a href="https://instagram.com/nebvsin" target="_blank" rel="noreferrer noopener">IG / @NEBVSIN</a>
            <a href="mailto:nebvsinstudio@gmail.com">nebvsinstudio@gmail.com</a>
        </div>
        <p>&copy; 2026 NEBVSIN. {{ $storefrontLayoutCopy['footer_rights'] ?? 'All rights reserved.' }}</p>
    </footer>

    @if ($cartDrawerOpen)
        <script>
            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }

                window.location.href = @json($cartDrawerCloseUrl);
            });
        </script>
    @endif
    <script>
        (function () {
            var toggle = document.querySelector('[data-nav-toggle]');
            var groups = document.querySelector('[data-nav-groups]');
            var backdrop = document.querySelector('[data-nav-backdrop]');

            if (!toggle || !groups || !backdrop) {
                return;
            }

            var closeTargets = groups.querySelectorAll('[data-mobile-close]');
            var focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';
            var lastFocused = null;

            function setOpen(isOpen) {
                if (isOpen) {
                    lastFocused = document.activeElement;
                }

                groups.classList.toggle('is-open', isOpen);
                toggle.classList.toggle('is-open', isOpen);
                backdrop.classList.toggle('is-open', isOpen);
                backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                if (isOpen) {
                    var focusables = groups.querySelectorAll(focusableSelector);

                    if (focusables.length) {
                        focusables[0].focus();
                    }
                } else if (lastFocused && typeof lastFocused.focus === 'function') {
                    lastFocused.focus();
                }
            }

            toggle.addEventListener('click', function () {
                setOpen(!groups.classList.contains('is-open'));
            });

            closeTargets.forEach(function (node) {
                node.addEventListener('click', function () {
                    setOpen(false);
                });
            });

            backdrop.addEventListener('click', function () {
                setOpen(false);
            });

            document.addEventListener('click', function (event) {
                if (!groups.classList.contains('is-open')) {
                    return;
                }

                if (groups.contains(event.target) || toggle.contains(event.target)) {
                    return;
                }

                setOpen(false);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                    return;
                }

                if (event.key !== 'Tab' || !groups.classList.contains('is-open')) {
                    return;
                }

                var focusables = Array.prototype.slice.call(groups.querySelectorAll(focusableSelector))
                    .filter(function (node) {
                        return node.offsetParent !== null;
                    });

                if (!focusables.length) {
                    event.preventDefault();
                    toggle.focus();
                    return;
                }

                var first = focusables[0];
                var last = focusables[focusables.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            var sectionLinks = document.querySelectorAll('[data-nav-section-link]');
            var sectionMap = {};
            var sections = ['drop', 'manifesto']
                .map(function (id) {
                    return document.getElementById(id);
                })
                .filter(Boolean);

            sectionLinks.forEach(function (link) {
                sectionMap[link.getAttribute('data-nav-section-link')] = link;
            });

            function setActiveSection(sectionId) {
                sectionLinks.forEach(function (link) {
                    link.classList.toggle('is-active', sectionId !== '' && link.getAttribute('data-nav-section-link') === sectionId);
                });
            }

            function syncSectionActiveFromHash() {
                var hash = window.location.hash.replace('#', '');

                if (hash && sectionMap[hash]) {
                    setActiveSection(hash);
                    return;
                }

                if (@json($isProcessPage)) {
                    setActiveSection('');
                }
            }

            syncSectionActiveFromHash();
            window.addEventListener('hashchange', syncSectionActiveFromHash);

            function syncSectionActiveFromScroll() {
                if (!sections.length || @json($isProcessPage)) {
                    return;
                }

                var nav = document.querySelector('.top-nav');
                var isMobile = window.matchMedia('(max-width: 900px)').matches;
                var navHeight = nav ? Math.round(nav.getBoundingClientRect().height) : 72;
                var headerOffset = isMobile
                    ? Math.round(navHeight + 22)
                    : Math.round(navHeight + 44);
                var viewportMidpoint = window.scrollY + (window.innerHeight * (isMobile ? 0.32 : 0.36));
                var probeY = Math.max(window.scrollY + headerOffset, viewportMidpoint);
                var activeId = '';

                sections.forEach(function (section) {
                    var sectionTop = section.offsetTop;
                    var sectionMidpoint = sectionTop + (section.offsetHeight * 0.35);

                    if (sectionTop <= probeY || sectionMidpoint <= probeY) {
                        activeId = section.id;
                    }
                });

                setActiveSection(activeId);
            }

            syncSectionActiveFromScroll();

            var ticking = false;
            window.addEventListener('scroll', function () {
                if (ticking) {
                    return;
                }

                ticking = true;
                window.requestAnimationFrame(function () {
                    syncSectionActiveFromScroll();
                    ticking = false;
                });
            }, { passive: true });

            window.addEventListener('resize', syncSectionActiveFromScroll);
        }());
    </script>
</body>
</html>
