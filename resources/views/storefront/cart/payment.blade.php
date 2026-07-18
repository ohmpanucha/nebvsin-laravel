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
    $feedbackMessages = array_values(array_filter([
        session('cart_status'),
        session('account_status'),
        ...$errors->all(),
    ], function ($message) {
        return filled($message);
    }));
@endphp

@section('content')
    <section class="checkout-shell">
        <article class="checkout-card reveal in-view">
            <div class="checkout-intro">
                <p class="address-kicker">{{ $copy['kicker'] }}</p>
                <h1 class="address-title">{{ $copy['title'] }}</h1>
                <p class="address-caption">{{ $copy['caption'] }}</p>
            </div>

            <div class="checkout-layout">
                <section class="checkout-panel" aria-labelledby="payment-order-summary-title">
                    <div class="checkout-summary-head">
                        <h2 id="payment-order-summary-title">{{ $copy['summary_title'] }}</h2>
                        <p>{{ $order['order_id'] }}</p>
                    </div>

                    <div class="checkout-summary-table-wrap">
                        <table class="checkout-summary-table">
                            <thead>
                                <tr>
                                    <th scope="col">IMAGE</th>
                                    <th scope="col">ITEM</th>
                                    <th scope="col">SIZE</th>
                                    <th scope="col">QTY</th>
                                    <th scope="col">{{ $copy['total_label'] }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order['lines'] as $line)
                                    <tr>
                                        <td>
                                            @if (!empty($line['image']))
                                                <img class="checkout-summary-thumb" src="{{ $line['image'] }}" alt="{{ $line['name'] }}" loading="lazy">
                                            @endif
                                        </td>
                                        <td>{{ $line['name'] }}</td>
                                        <td>{{ $line['size'] ?: '-' }}</td>
                                        <td>{{ $line['quantity'] }}</td>
                                        <td>{{ $formatAmount($line['line_total']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="checkout-summary-total-row">
                        <p class="checkout-summary-total">{{ $copy['total_label'] }}</p>
                        <p class="checkout-summary-total">{{ $formatAmount($order['total_amount']) }}</p>
                    </div>

                    <div class="checkout-highlight-grid">
                        <div class="checkout-highlight-card">
                            <p class="detail-label">{{ $copy['status_label'] }}</p>
                            <p>{{ strtoupper((string) $order['status']) }}</p>
                        </div>
                        <div class="checkout-highlight-card">
                            <p class="detail-label">{{ $copy['created_label'] }}</p>
                            <p>{{ $formatDate($order['created_at']) }}</p>
                        </div>
                    </div>
                </section>

                <section class="checkout-panel" aria-labelledby="payment-instruction-title">
                    <div class="checkout-summary-head">
                        <h2 id="payment-instruction-title">{{ $copy['instruction_title'] }}</h2>
                        <p>{{ $copy['instruction_kicker'] }}</p>
                    </div>

                    <div class="payment-instruction-card">
                        <p class="detail-label">{{ $copy['bank_transfer'] }}</p>
                        <p>{{ $promptPayAccountName }}</p>
                        <p>{{ $promptPayBankName }} / {{ $promptPayData['mobile_number'] ?? config('storefront.promptpay.mobile_number') }}</p>
                        <p>{{ str_replace(':amount', $formatAmount($order['total_amount']), $copy['transfer_copy']) }}</p>
                    </div>

                    @if ($promptPayData)
                        <div class="payment-qr-card">
                            <img src="{{ $promptPayData['qr_image_url'] }}" alt="PromptPay QR code" loading="lazy" data-payment-qr-image>
                            <div class="payment-qr-meta">
                                <p>{{ $promptPayData['mobile_number'] }}</p>
                                <p>{{ $formatAmount($promptPayData['amount']) }}</p>
                                <p class="payment-qr-payload" data-payment-qr-payload>{{ $promptPayData['payload'] }}</p>
                                <div class="payment-qr-actions">
                                    <button type="button" class="detail-secondary-link payment-qr-action-btn" data-copy-payload>{{ $copy['copy_payload'] }}</button>
                                    <a
                                        href="{{ $promptPayData['qr_image_url'] }}"
                                        download="promptpay-{{ $order['order_id'] }}.svg"
                                        class="detail-secondary-link payment-qr-download-link"
                                    >{{ $copy['download_qr'] }}</a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="post" action="{{ route('storefront.account.purchase-history.slip', ['lang' => $storefrontLocale, 'orderId' => $order['order_id']]) }}" enctype="multipart/form-data" class="purchase-slip-upload">
                        @csrf
                        <label for="slip-upload" class="detail-label">{{ $copy['upload_label'] }}</label>
                        <div class="purchase-slip-upload-shell">
                            <input
                                id="slip-upload"
                                type="file"
                                name="slip"
                                accept="image/png,image/jpeg,image/webp"
                                required
                                data-payment-slip-input
                                data-idle-label="{{ $copy['slip_idle'] }}"
                                data-change-label="{{ $copy['change_slip'] }}"
                                class="purchase-slip-file-input"
                            >
                            <label for="slip-upload" class="purchase-slip-file-trigger">
                                <span>{{ $copy['select_slip'] }}</span>
                            </label>
                            <div class="purchase-slip-upload-meta">
                                <p class="purchase-slip-file-name" data-payment-slip-filename>{{ $copy['slip_idle'] }}</p>
                                <p>{{ $copy['upload_hint'] }}</p>
                            </div>
                        </div>
                        <div class="purchase-slip-selected-frame" data-payment-slip-frame>
                            <img
                                src=""
                                alt="Selected payment slip {{ $order['order_id'] }}"
                                class="purchase-slip-selected-preview"
                                data-payment-slip-preview
                            >
                        </div>

                        @if ($feedbackMessages)
                            @foreach ($feedbackMessages as $message)
                                <p class="address-status">{{ $message }}</p>
                            @endforeach
                        @endif
                        
                        <button type="submit" class="luxury-hover">{{ $copy['upload_cta'] }}</button>
                    </form>

                    @if (!empty($order['payment_slip_data']))
                        <div class="purchase-slip-preview">
                            <p>{{ $copy['last_upload'] }}</p>
                            <img src="{{ $order['payment_slip_data'] }}" alt="Payment slip {{ $order['order_id'] }}">
                            <p>UPLOADED: {{ $formatDate($order['payment_slip_uploaded_at']) }}</p>
                        </div>
                    @endif

                    <div class="checkout-actions">
                        <a href="{{ route('storefront.account.purchase-history', ['lang' => $storefrontLocale]) }}" class="detail-secondary-link">{{ $copy['view_history'] }}</a>
                    </div>
                </section>
            </div>
        </article>
    </section>
@endsection

@push('meta')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var copyButton = document.querySelector('[data-copy-payload]');
            var payloadNode = document.querySelector('[data-payment-qr-payload]');
            var statusNode = document.querySelector('.address-status');
            var slipInput = document.querySelector('[data-payment-slip-input]');

            if (slipInput) {
                var slipForm = slipInput.closest('form');
                var slipPreview = slipForm ? slipForm.querySelector('[data-payment-slip-preview]') : null;
                var slipFrame = slipForm ? slipForm.querySelector('[data-payment-slip-frame]') : null;
                var slipFileName = slipForm ? slipForm.querySelector('[data-payment-slip-filename]') : null;
                var slipTriggerLabel = slipForm ? slipForm.querySelector('.purchase-slip-file-trigger span') : null;
                var selectLabel = slipTriggerLabel ? slipTriggerLabel.textContent : '';
                var changeLabel = slipInput.getAttribute('data-change-label') || selectLabel;

                if (slipPreview && slipFileName) {
                    slipInput.addEventListener('change', function () {
                        var file = slipInput.files && slipInput.files[0] ? slipInput.files[0] : null;
                        if (!file) {
                            slipPreview.classList.remove('is-visible');
                            slipPreview.removeAttribute('src');
                            if (slipFrame) {
                                slipFrame.classList.remove('is-visible');
                            }
                            slipFileName.textContent = slipInput.getAttribute('data-idle-label') || '';
                            if (slipTriggerLabel) {
                                slipTriggerLabel.textContent = selectLabel;
                            }
                            return;
                        }

                        slipFileName.textContent = file.name;
                        if (slipTriggerLabel) {
                            slipTriggerLabel.textContent = changeLabel;
                        }

                        if (!String(file.type || '').match(/^image\//)) {
                            slipPreview.classList.remove('is-visible');
                            slipPreview.removeAttribute('src');
                            if (slipFrame) {
                                slipFrame.classList.remove('is-visible');
                            }
                            return;
                        }

                        var reader = new FileReader();
                        reader.onload = function (event) {
                            slipPreview.src = event.target && event.target.result ? event.target.result : '';
                            slipPreview.classList.add('is-visible');
                            if (slipFrame) {
                                slipFrame.classList.add('is-visible');
                            }
                        };
                        reader.onerror = function () {
                            slipPreview.classList.remove('is-visible');
                            slipPreview.removeAttribute('src');
                            if (slipFrame) {
                                slipFrame.classList.remove('is-visible');
                            }
                        };
                        reader.readAsDataURL(file);
                    });
                }
            }

            if (!copyButton || !payloadNode) {
                return;
            }

            copyButton.addEventListener('click', async function () {
                var text = payloadNode.textContent || '';

                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        var helper = document.createElement('textarea');
                        helper.value = text;
                        document.body.appendChild(helper);
                        helper.select();
                        document.execCommand('copy');
                        helper.remove();
                    }

                    if (statusNode) {
                        statusNode.textContent = @json($copy['payload_copied']);
                    }
                } catch (error) {
                    if (statusNode) {
                        statusNode.textContent = @json($copy['payload_copy_failed']);
                    }
                }
            });
        });
    </script>
@endpush
