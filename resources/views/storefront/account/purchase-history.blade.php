@extends('layouts.storefront')

@php
    $formatAmount = function ($amount) {
        return number_format((float) $amount, 2).' THB';
    };
    $formatDate = function ($value) {
        if (!$value) return '-';
        try {
            return \Carbon\Carbon::parse($value)->setTimezone('Asia/Bangkok')->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return '-';
        }
    };
    $statusClass = function ($status) {
        $value = strtolower((string) $status);
        if ($value === 'paid') return 'status-tag is-paid';
        if ($value === 'awaiting_review') return 'status-tag is-awaiting-review';
        if ($value === 'pending') return 'status-tag is-pending';
        if ($value === 'failed') return 'status-tag is-failed';
        if ($value === 'canceled') return 'status-tag is-canceled';
        if ($value === 'pending_fulfillment') return 'status-tag is-pending-fulfillment';
        if ($value === 'processing') return 'status-tag is-processing';
        if ($value === 'shipped') return 'status-tag is-shipped';
        if ($value === 'delivered') return 'status-tag is-delivered';
        return 'status-tag';
    };
    $statusLabel = function ($status, $type = 'order') use ($copy) {
        $value = strtolower((string) $status);
        if ($value === '') {
            return $copy['status_unknown'] ?? '-';
        }

        $key = $type.'_status_'.$value;
        return $copy[$key] ?? strtoupper((string) $status);
    };
@endphp

@section('content')
    <section class="address-page">
        <article class="address-card reveal in-view" aria-labelledby="purchase-history-title">
            <p class="address-kicker">{{ $copy['kicker'] }}</p>
            <h1 id="purchase-history-title" class="address-title">{{ $copy['title'] }}</h1>
            <p class="address-caption">{{ $copy['caption'] }}</p>
            @if (!empty($statusMessage))
                <p class="address-status">{{ $statusMessage }}</p>
            @endif

            @if (!$orders)
                <p class="address-status">{{ $copy['empty'] }}</p>
            @else
                <div class="purchase-history-list">
                    @foreach ($orders as $order)
                        @php
                            $qrData = $promptPayQrByOrder[$order['order_id']] ?? null;
                            $orderStatus = strtolower((string) ($order['status'] ?? ''));
                            $shouldShowQr = $orderStatus === 'pending' && !empty($qrData);
                            $canUploadSlip = in_array($orderStatus, ['pending', 'awaiting_review'], true);
                            $isExpanded = false;
                        @endphp
                        <section class="purchase-order-card">
                            <button
                                type="button"
                                class="purchase-order-toggle"
                                data-purchase-accordion-toggle
                                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                                aria-controls="purchase-order-body-{{ $order['order_id'] }}"
                                data-expand-label="{{ $copy['expand_order'] }}"
                                data-collapse-label="{{ $copy['collapse_order'] }}"
                            >
                                <span class="purchase-order-toggle-main">
                                    <span class="purchase-order-toggle-id">{{ str_replace(':id', $order['order_id'], $copy['order']) }}</span>
                                    <span class="purchase-order-toggle-summary">{{ str_replace(':count', (string) count($order['lines']), $copy['items']) }}</span>
                                </span>
                                <span class="purchase-order-toggle-meta">
                                    <span>{{ str_replace(':value', $formatAmount($order['total_amount']), $copy['total']) }}</span>
                                    <span>{{ str_replace(':value', $formatDate($order['created_at']), $copy['date']) }}</span>
                                </span>
                                <span class="purchase-order-toggle-tags">
                                    <span class="{{ $statusClass($order['status']) }}">{{ $statusLabel($order['status'], 'order') }}</span>
                                    <span class="{{ $statusClass($order['shipping_status']) }}">{{ $statusLabel($order['shipping_status'], 'shipping') }}</span>
                                </span>
                                <span class="purchase-order-toggle-state-row">
                                    <span class="purchase-order-toggle-divider" aria-hidden="true"></span>
                                </span>
                                <span class="purchase-order-toggle-state">
                                    <span data-purchase-accordion-label>{{ $isExpanded ? $copy['collapse_order'] : $copy['expand_order'] }}</span>
                                </span>
                            </button>

                            <div
                                id="purchase-order-body-{{ $order['order_id'] }}"
                                class="purchase-order-body{{ $isExpanded ? ' is-open' : '' }}"
                                data-purchase-accordion-panel
                                @if (!$isExpanded) hidden @endif
                            >
                                <div class="purchase-order-head">
                                    <p>{{ $copy['status'] }} <span class="{{ $statusClass($order['status']) }}">{{ $statusLabel($order['status'], 'order') }}</span></p>
                                    <p>{{ $copy['shipping'] }} <span class="{{ $statusClass($order['shipping_status']) }}">{{ $statusLabel($order['shipping_status'], 'shipping') }}</span></p>
                                </div>

                                @if ($order['lines'])
                                    <div class="purchase-order-lines">
                                        @foreach ($order['lines'] as $line)
                                            <article class="purchase-order-line">
                                                @if (!empty($line['image']))
                                                    <img src="{{ $line['image'] }}" alt="{{ $line['name'] }}">
                                                @endif
                                                <div class="purchase-order-line-meta">
                                                    <p>{{ $line['name'] }}</p>
                                                    <p>{{ str_replace(':value', strtoupper((string) ($line['size'] ?: '-')), $copy['size']) }}</p>
                                                    <p>{{ str_replace(':value', (string) $line['quantity'], $copy['qty']) }}</p>
                                                    <p>{{ str_replace(':value', $formatAmount($line['unit_amount']), $copy['unit']) }}</p>
                                                    <p>{{ str_replace(':value', $formatAmount($line['line_total']), $copy['line_total']) }}</p>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($shouldShowQr)
                                    <section class="purchase-order-qr" aria-label="{{ str_replace(':id', $order['order_id'], $copy['payment_qr_aria']) }}">
                                        <div id="purchase-qr-{{ $order['order_id'] }}" class="purchase-order-qr-panel">
                                            <img
                                                src="{{ $qrData['qr_image_url'] }}"
                                                alt="{{ str_replace(':id', $order['order_id'], $copy['payment_qr_alt']) }}"
                                                loading="lazy"
                                            >
                                            <p>{{ str_replace(':value', number_format((float) ($qrData['amount'] ?? 0), 0), $copy['amount_simple']) }}</p>
                                        </div>
                                    </section>
                                @endif

                                @if (!empty($order['payment_slip_data']))
                                    <div class="purchase-slip-preview">
                                        <p>{{ $copy['slip'] }}</p>
                                        <img src="{{ $order['payment_slip_data'] }}" alt="Payment slip {{ $order['order_id'] }}">
                                        <p>{{ str_replace(':value', $formatDate($order['payment_slip_uploaded_at']), $copy['uploaded']) }}</p>
                                    </div>
                                @elseif ($canUploadSlip)
                                    <form method="post" action="{{ route('storefront.account.purchase-history.slip', ['lang' => $storefrontLocale, 'orderId' => $order['order_id']]) }}" enctype="multipart/form-data" class="purchase-slip-upload">
                                        @csrf
                                        <label for="slip-{{ $order['order_id'] }}" class="detail-label">{{ $copy['slip'] }}</label>
                                        <div class="purchase-slip-upload-shell">
                                            <input id="slip-{{ $order['order_id'] }}" type="file" name="slip" accept="image/png,image/jpeg,image/webp" required data-purchase-slip-input data-idle-label="{{ $copy['slip_idle'] }}" data-change-label="{{ $copy['change_slip'] }}" class="purchase-slip-file-input">
                                            <label for="slip-{{ $order['order_id'] }}" class="purchase-slip-file-trigger">
                                                <span>{{ $copy['select_slip'] }}</span>
                                            </label>
                                            <div class="purchase-slip-upload-meta">
                                                <p class="purchase-slip-file-name" data-purchase-slip-filename>{{ $copy['slip_idle'] }}</p>
                                                <p>{{ $copy['slip_hint'] }}</p>
                                            </div>
                                        </div>
                                        <div class="purchase-slip-selected-frame" data-purchase-slip-frame>
                                            <img
                                                src=""
                                                alt="Selected payment slip {{ $order['order_id'] }}"
                                                class="purchase-slip-selected-preview"
                                                data-purchase-slip-preview
                                            >
                                        </div>
                                        <button type="submit">{{ $copy['upload_cta'] }}</button>
                                    </form>
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
@endsection

@push('meta')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggles = document.querySelectorAll('[data-purchase-qr-toggle]');
            var slipInputs = document.querySelectorAll('[data-purchase-slip-input]');
            var accordionToggles = document.querySelectorAll('[data-purchase-accordion-toggle]');

            toggles.forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    var panelId = toggle.getAttribute('aria-controls');
                    var panel = panelId ? document.getElementById(panelId) : null;
                    if (!panel) {
                        return;
                    }

                    var isOpen = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                    toggle.textContent = isOpen ? (toggle.getAttribute('data-open-label') || '') : (toggle.getAttribute('data-close-label') || '');
                    panel.hidden = isOpen;
                });
            });

            accordionToggles.forEach(function (toggle) {
                var panelId = toggle.getAttribute('aria-controls');
                var panel = panelId ? document.getElementById(panelId) : null;
                var label = toggle.querySelector('[data-purchase-accordion-label]');
                var expandLabel = toggle.getAttribute('data-expand-label') || '';
                var collapseLabel = toggle.getAttribute('data-collapse-label') || '';

                if (!panel) {
                    return;
                }

                var setPanelHeight = function () {
                    if (panel.hidden) {
                        panel.style.maxHeight = '0px';
                        return;
                    }

                    panel.style.maxHeight = panel.scrollHeight + 'px';
                };

                if (!panel.hidden) {
                    setPanelHeight();
                }

                toggle.addEventListener('click', function () {
                    var isOpen = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');

                    if (label) {
                        label.textContent = isOpen ? expandLabel : collapseLabel;
                    }

                    if (isOpen) {
                        panel.style.maxHeight = panel.scrollHeight + 'px';
                        requestAnimationFrame(function () {
                            panel.style.maxHeight = '0px';
                        });
                        panel.classList.remove('is-open');
                        window.setTimeout(function () {
                            panel.hidden = true;
                        }, 320);
                        return;
                    }

                    panel.hidden = false;
                    panel.classList.add('is-open');
                    panel.style.maxHeight = '0px';
                    requestAnimationFrame(function () {
                        setPanelHeight();
                    });
                });
            });

            slipInputs.forEach(function (input) {
                var form = input.closest('form');
                var preview = form ? form.querySelector('[data-purchase-slip-preview]') : null;
                var frame = form ? form.querySelector('[data-purchase-slip-frame]') : null;
                var fileName = form ? form.querySelector('[data-purchase-slip-filename]') : null;
                var triggerLabel = form ? form.querySelector('.purchase-slip-file-trigger span') : null;
                var selectLabel = triggerLabel ? triggerLabel.textContent : '';
                var changeLabel = input.getAttribute('data-change-label') || selectLabel;

                if (!preview || !fileName) {
                    return;
                }

                input.addEventListener('change', function () {
                    var file = input.files && input.files[0] ? input.files[0] : null;
                    if (!file) {
                        preview.classList.remove('is-visible');
                        preview.removeAttribute('src');
                        if (frame) {
                            frame.classList.remove('is-visible');
                        }
                        fileName.textContent = input.getAttribute('data-idle-label') || '';
                        if (triggerLabel) {
                            triggerLabel.textContent = selectLabel;
                        }
                        return;
                    }

                    if (!String(file.type || '').match(/^image\//)) {
                        preview.classList.remove('is-visible');
                        preview.removeAttribute('src');
                        if (frame) {
                            frame.classList.remove('is-visible');
                        }
                        fileName.textContent = input.getAttribute('data-idle-label') || '';
                        if (triggerLabel) {
                            triggerLabel.textContent = selectLabel;
                        }
                        return;
                    }

                    fileName.textContent = file.name;
                    if (triggerLabel) {
                        triggerLabel.textContent = changeLabel;
                    }

                    var reader = new FileReader();
                    reader.onload = function (event) {
                        preview.src = event.target && event.target.result ? event.target.result : '';
                        preview.classList.toggle('is-visible', !!preview.src);
                        if (frame) {
                            frame.classList.toggle('is-visible', !!preview.src);
                        }
                    };
                    reader.onerror = function () {
                        preview.classList.remove('is-visible');
                        preview.removeAttribute('src');
                        if (frame) {
                            frame.classList.remove('is-visible');
                        }
                    };
                    reader.readAsDataURL(file);
                });
            });
        });
    </script>
@endpush
