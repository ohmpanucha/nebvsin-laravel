@extends('layouts.storefront')

@php
    $editingProductId = $moduleKey === 'products' ? (int) session('admin_edit_product', 0) : 0;
    $editingProduct = null;
    $editingProductHasOldInput = $moduleKey === 'products' && $editingProductId > 0 && count(session()->getOldInput()) > 0;
    $createProductHasOldInput = $moduleKey === 'products' && $editingProductId === 0 && count(session()->getOldInput()) > 0;

    if ($moduleKey === 'products' && $editingProductId > 0) {
        foreach ($rows as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $editingProductId) {
                $editingProduct = $candidate;
                break;
            }
        }
    }

    $inventoryLowStockItems = $moduleKey === 'inventory' ? ($moduleData['lowStockItems'] ?? []) : [];
    $inventoryRecentMovements = $moduleKey === 'inventory' ? ($moduleData['recentMovements'] ?? []) : [];
    $shippingDetails = $moduleKey === 'shipping' ? $rows : [];
    $orderDetails = $moduleKey === 'orders' ? $rows : [];
    $shippingStatusLabels = [
        'pending_fulfillment' => $adminCopy['shipping_status_pending_fulfillment'] ?? 'Ready to Pack',
        'processing' => $adminCopy['shipping_status_processing'] ?? 'Packing',
        'shipped' => $adminCopy['shipping_status_shipped'] ?? 'Shipped',
        'delivered' => $adminCopy['shipping_status_delivered'] ?? 'Delivered',
    ];
    $orderStatusLabels = [
        'pending' => $adminCopy['order_status_pending'] ?? 'Pending',
        'awaiting_review' => $adminCopy['order_status_awaiting_review'] ?? 'Awaiting Review',
        'paid' => $adminCopy['order_status_paid'] ?? 'Paid',
        'failed' => $adminCopy['order_status_failed'] ?? 'Failed',
        'canceled' => $adminCopy['order_status_canceled'] ?? 'Canceled',
    ];
@endphp

@section('content')
    <section class="admin-page">
        <article class="admin-card reveal in-view" aria-labelledby="admin-title">
            <p class="admin-kicker">{{ $adminCopy['kicker'] ?? 'ADMIN' }}</p>
            <h1 id="admin-title" class="admin-title">{{ $moduleConfig['title'] }}</h1>
            <p class="admin-intro">{{ $moduleConfig['description'] }}</p>

            <nav class="admin-nav" aria-label="{{ $adminCopy['nav_aria'] ?? 'Admin pages' }}">
                @foreach ($navItems as $item)
                    <a href="{{ $item['route'] }}" class="{{ $moduleKey === $item['key'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="admin-stats">
                @foreach ($stats as $stat)
                    <section class="admin-stat">
                        <p class="admin-stat-label">{{ $stat['label'] }}</p>
                        <p class="admin-stat-value">{{ $stat['value'] }}</p>
                    </section>
                @endforeach
            </div>

            <div class="admin-table-wrap">
                <div class="admin-head-row">
                    <p class="admin-status">{{ $adminCopy['recent_records'] ?? 'Recent records' }}</p>
                    {{-- <p class="admin-status">{{ $adminCopy['phase_notice'] ?? 'Laravel admin is live for active modules.' }}</p> --}}
                </div>

                @if ($adminStatus)
                    <p class="admin-flash">{{ $adminStatus }}</p>
                @endif

                @if ($moduleKey === 'products')
                    <section class="admin-form-card">
                        <h2 class="admin-section-title">Create Product</h2>
                        <form method="post" action="{{ route('admin.products.store', ['lang' => $storefrontLocale]) }}" class="admin-form-grid" data-admin-product-create-form data-admin-product-image-src="" enctype="multipart/form-data">
                            @csrf
                            <input name="name" type="text" placeholder="Name" value="{{ $createProductHasOldInput ? old('name') : '' }}" required>
                            <input name="price_thb" type="number" min="0" placeholder="Price THB" value="{{ $createProductHasOldInput ? old('price_thb') : '' }}" required>
                            <input name="alt" type="text" placeholder="Alt text" value="{{ $createProductHasOldInput ? old('alt') : '' }}">
                            <div class="admin-product-image-preview" data-admin-product-image-preview>
                                <img src="" alt="Product preview" data-admin-product-image-preview-img hidden>
                                <p class="admin-product-image-preview-empty" data-admin-product-image-preview-empty>Image preview</p>
                            </div>
                            <label class="admin-upload-field">
                                <span>Upload image</span>
                                <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp" {{ $createProductHasOldInput ? '' : 'required' }} data-admin-product-image-file-input>
                            </label>
                            <input name="sort_order" type="number" placeholder="Sort order" value="{{ $createProductHasOldInput ? old('sort_order', 0) : 0 }}">
                            <input name="limited_qty" type="number" min="0" placeholder="Limited qty" value="{{ $createProductHasOldInput ? old('limited_qty', 40) : 40 }}">
                            <div class="admin-check-group">
                                <label class="admin-check"><input type="checkbox" name="is_public" value="1" {{ ($createProductHasOldInput ? old('is_public', '1') : '1') ? 'checked' : '' }}> <span>Public</span></label>
                                <label class="admin-check"><input type="checkbox" name="coming_soon" value="1" {{ $createProductHasOldInput && old('coming_soon') ? 'checked' : '' }}> <span>Coming soon</span></label>
                            </div>
                            <textarea name="description" placeholder="Description">{{ $createProductHasOldInput ? old('description') : '' }}</textarea>
                            <button type="submit" class="admin-action-btn">Create Product</button>
                        </form>
                    </section>
                @endif

                @if ($moduleKey === 'procurement')
                    <div class="admin-procurement-grid">
                        <section class="admin-procurement-card">
                            <h2 class="admin-section-title">Create Supplier</h2>
                            <form method="post" action="{{ route('admin.procurement.suppliers.store', ['lang' => $storefrontLocale]) }}" class="admin-form-grid">
                                @csrf
                                <input name="name" type="text" placeholder="Supplier name" value="{{ old('name') }}" required>
                                <input name="contact_name" type="text" placeholder="Contact name" value="{{ old('contact_name') }}">
                                <input name="phone" type="text" placeholder="Phone" value="{{ old('phone') }}">
                                <input name="note" type="text" placeholder="Note" value="{{ old('note') }}">
                                <button type="submit" class="admin-action-btn">Add Supplier</button>
                            </form>
                        </section>
                        <section class="admin-procurement-card">
                            <h2 class="admin-section-title">Create Purchase Order</h2>
                            @php
                                $poItems = old('items', [['product_id' => '', 'ordered_qty' => '', 'unit_cost' => '']]);
                                $extraCosts = old('extra_costs', [['cost_name' => '', 'amount' => '']]);
                            @endphp
                            <form method="post" action="{{ route('admin.procurement.purchase-orders.store', ['lang' => $storefrontLocale]) }}" class="admin-form-grid" data-admin-procurement-form>
                                @csrf
                                <select name="supplier_id" required>
                                    <option value="">Select supplier</option>
                                    @foreach (($moduleData['suppliers'] ?? []) as $supplier)
                                        <option value="{{ $supplier['id'] }}" {{ old('supplier_id') === $supplier['id'] ? 'selected' : '' }}>{{ $supplier['name'] }}</option>
                                    @endforeach
                                </select>
                                <input name="note" type="text" placeholder="Note" value="{{ old('note') }}">

                                <div class="admin-procurement-lines">
                                    <div data-admin-procurement-items>
                                        @foreach ($poItems as $index => $item)
                                            <div class="admin-procurement-line" data-admin-procurement-item>
                                                <select name="items[{{ $index }}][product_id]" required>
                                                    <option value="">Product</option>
                                                    @foreach (($moduleData['productCosts'] ?? []) as $product)
                                                        <option value="{{ $product['product_id'] }}" {{ (string) ($item['product_id'] ?? '') === (string) $product['product_id'] ? 'selected' : '' }}>
                                                            #{{ $product['product_id'] }} {{ $product['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <input name="items[{{ $index }}][ordered_qty]" type="number" min="1" step="1" placeholder="Qty" value="{{ $item['ordered_qty'] ?? '' }}" required>
                                                <input name="items[{{ $index }}][unit_cost]" type="number" min="0" step="0.01" placeholder="Unit cost" value="{{ $item['unit_cost'] ?? '' }}" required>
                                            </div>
                                        @endforeach
                                    </div>
                                    <template data-admin-procurement-item-template>
                                        <div class="admin-procurement-line">
                                            <select name="items[__INDEX__][product_id]" required>
                                                <option value="">Product</option>
                                                @foreach (($moduleData['productCosts'] ?? []) as $product)
                                                    <option value="{{ $product['product_id'] }}">#{{ $product['product_id'] }} {{ $product['name'] }}</option>
                                                @endforeach
                                            </select>
                                            <input name="items[__INDEX__][ordered_qty]" type="number" min="1" step="1" placeholder="Qty" required>
                                            <input name="items[__INDEX__][unit_cost]" type="number" min="0" step="0.01" placeholder="Unit cost" required>
                                            <button type="button" class="admin-mini-btn" data-admin-procurement-remove-line>Remove</button>
                                        </div>
                                    </template>
                                </div>

                                <div class="admin-procurement-lines">
                                    <p>Other Costs</p>
                                    <div class="admin-procurement-extra-line">
                                        <button type="button" class="admin-mini-btn" data-admin-procurement-add-cost>Add Cost</button>
                                    </div>
                                    <div data-admin-procurement-extra-costs>
                                        @foreach ($extraCosts as $index => $cost)
                                            <div class="admin-procurement-extra-line" data-admin-procurement-extra-cost>
                                                <input name="extra_costs[{{ $index }}][cost_name]" type="text" placeholder="Cost name" value="{{ $cost['cost_name'] ?? '' }}">
                                                <input name="extra_costs[{{ $index }}][amount]" type="number" min="0" step="0.01" placeholder="Amount" value="{{ $cost['amount'] ?? '' }}">
                                                <button type="button" class="admin-mini-btn" data-admin-procurement-remove-cost {{ count($extraCosts) === 1 ? 'disabled' : '' }}>Remove</button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <template data-admin-procurement-extra-cost-template>
                                        <div class="admin-procurement-extra-line">
                                            <input name="extra_costs[__INDEX__][cost_name]" type="text" placeholder="Cost name">
                                            <input name="extra_costs[__INDEX__][amount]" type="number" min="0" step="0.01" placeholder="Amount">
                                            <button type="button" class="admin-mini-btn" data-admin-procurement-remove-cost>Remove</button>
                                        </div>
                                    </template>
                                </div>

                                <div class="admin-row-actions">
                                    <button type="submit" class="admin-action-btn">Create PO</button>
                                </div>
                            </form>
                        </section>
                    </div>

                    @if (!empty($moduleData['productCosts']))
                        <section class="admin-procurement-card">
                            <h2 class="admin-section-title">Product Cost Reference</h2>
                            <div class="admin-table-scroll">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>PRODUCT</th>
                                            <th>NAME</th>
                                            <th>ON HAND</th>
                                            <th>AVG COST</th>
                                            <th>LAST COST</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($moduleData['productCosts'] as $product)
                                            <tr>
                                                <td>{{ $product['product_id'] }}</td>
                                                <td>{{ $product['name'] }}</td>
                                                <td>{{ $product['qty_on_hand'] }}</td>
                                                <td>{{ $product['avg_unit_cost'] }}</td>
                                                <td>{{ $product['last_unit_cost'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif
                @endif

                @if ($moduleKey === 'inventory')
                    <div class="admin-forms-two">
                        <section class="admin-form-card">
                            <h2 class="admin-section-title">Receive Stock</h2>
                            <form method="post" action="{{ route('admin.inventory.receive', ['lang' => $storefrontLocale]) }}" class="admin-form-grid compact">
                                @csrf
                                <select name="product_id" required>
                                    <option value="">Select product</option>
                                    @foreach ($rows as $row)
                                        <option value="{{ $row['product_id'] }}" {{ (string) old('product_id') === (string) $row['product_id'] ? 'selected' : '' }}>
                                            #{{ $row['product_id'] }} {{ $row['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <input name="quantity" type="number" min="1" placeholder="Quantity" value="{{ old('quantity') }}" required>
                                <input name="note" type="text" placeholder="Note (optional)" value="{{ old('note') }}">
                                <button type="submit" class="admin-action-btn">Receive</button>
                            </form>
                        </section>
                        <section class="admin-form-card">
                            <h2 class="admin-section-title">Deduct Stock</h2>
                            <form method="post" action="{{ route('admin.inventory.deduct', ['lang' => $storefrontLocale]) }}" class="admin-form-grid compact">
                                @csrf
                                <select name="product_id" required>
                                    <option value="">Select product</option>
                                    @foreach ($rows as $row)
                                        <option value="{{ $row['product_id'] }}" {{ (string) old('product_id') === (string) $row['product_id'] ? 'selected' : '' }}>
                                            #{{ $row['product_id'] }} {{ $row['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <input name="quantity" type="number" min="1" placeholder="Quantity" value="{{ old('quantity') }}" required>
                                <input name="note" type="text" placeholder="Note (optional)" value="{{ old('note') }}">
                                <button type="submit" class="admin-action-btn danger">Deduct</button>
                            </form>
                        </section>
                    </div>

                    <section class="admin-form-card admin-low-stock-panel">
                        <h2 class="admin-section-title">Low Stock Alert ({{ count($inventoryLowStockItems) }})</h2>
                        @if ($inventoryLowStockItems)
                            <ul class="admin-low-stock-list">
                                @foreach ($inventoryLowStockItems as $item)
                                    <li>
                                        #{{ $item['product_id'] }} {{ $item['name'] }}:
                                        {{ $item['qty_on_hand'] }} left
                                        (threshold {{ $item['low_stock_threshold'] }})
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="admin-empty">All products are above threshold.</p>
                        @endif
                    </section>
                @endif

                @if ($rows)
                    @if ($moduleKey === 'procurement')
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>PO</th>
                                        <th>SUPPLIER</th>
                                        <th>STATUS</th>
                                        <th>ORDERED</th>
                                        <th>RECEIVED</th>
                                        <th>TOTAL</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row['id'] }}</td>
                                            <td>{{ $row['supplier_name'] }}</td>
                                            <td>
                                                <span class="admin-badge admin-badge-{{ str_replace('_', '-', (string) ($row['status'] ?? 'draft')) }}">
                                                    {{ strtoupper((string) $row['status']) }}
                                                </span>
                                            </td>
                                            <td>{{ $row['ordered_units'] }}</td>
                                            <td>{{ $row['received_units'] }}</td>
                                            <td>{{ $row['total_amount'] }}</td>
                                            <td>
                                                <div class="admin-row-actions">
                                                    @php
                                                        $procurementDetailPayload = [
                                                            'purchase_order' => [
                                                                'id' => $row['id'],
                                                                'supplier_name' => $row['supplier_name'],
                                                                'status' => $row['status'],
                                                                'note' => $row['note'],
                                                                'subtotal_amount' => $row['subtotal_amount'],
                                                                'extra_cost_amount' => $row['extra_cost_amount'],
                                                                'total_amount' => $row['total_amount'],
                                                                'created_at' => $row['created_at'],
                                                                'updated_at' => $row['updated_at'],
                                                            ],
                                                            'items' => $row['items'] ?? [],
                                                            'extra_costs' => $row['extra_costs'] ?? [],
                                                            'receipts' => $row['receipts'] ?? [],
                                                            'receive_action' => route('admin.procurement.purchase-orders.receive', ['poId' => $row['id'], 'lang' => $storefrontLocale]),
                                                            'manage_url' => route('admin.procurement', ['lang' => $storefrontLocale, 'purchase_order_id' => $row['id']]),
                                                            'old_note' => '',
                                                        ];
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        class="admin-mini-btn"
                                                        data-admin-procurement-detail-open
                                                        data-po-detail='@json($procurementDetailPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                                                    >
                                                        Detail
                                                    </button>
                                                    @if (($row['status'] ?? '') === 'draft')
                                                        <form method="post" action="{{ route('admin.procurement.purchase-orders.status', ['poId' => $row['id'], 'lang' => $storefrontLocale]) }}" class="admin-inline-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="admin-mini-btn">Approve</button>
                                                        </form>
                                                        <form method="post" action="{{ route('admin.procurement.purchase-orders.status', ['poId' => $row['id'], 'lang' => $storefrontLocale]) }}" class="admin-inline-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="canceled">
                                                            <button type="submit" class="admin-mini-btn danger">Cancel</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($moduleKey === 'orders')
                        @php
                            $orderCollection = collect($rows);
                            $orderBuckets = [
                                'all' => $orderCollection->count(),
                                'pending' => $orderCollection->where('status', 'pending')->count(),
                                'awaiting_review' => $orderCollection->where('status', 'awaiting_review')->count(),
                                'paid' => $orderCollection->where('status', 'paid')->count(),
                                'failed' => $orderCollection->where('status', 'failed')->count(),
                                'canceled' => $orderCollection->where('status', 'canceled')->count(),
                                'with_slip' => $orderCollection->where('has_slip', true)->count(),
                            ];
                        @endphp
                        <section class="admin-shipments-board">
                            <div class="admin-shipments-toolbar">
                                <div class="admin-shipments-search">
                                    <label for="admin-orders-search" class="admin-detail-label">SEARCH</label>
                                    <input id="admin-orders-search" type="search" placeholder="Order, email, customer, province" data-admin-orders-search>
                                </div>
                                <div class="admin-shipments-filters" data-admin-orders-filters>
                                    <button type="button" class="admin-filter-chip is-active" data-filter="all">{{ $adminCopy['order_filter_all'] ?? 'All' }} <span>{{ $orderBuckets['all'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="pending">{{ $adminCopy['order_filter_pending'] ?? 'Pending' }} <span>{{ $orderBuckets['pending'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="awaiting_review">{{ $adminCopy['order_filter_awaiting_review'] ?? 'Awaiting Review' }} <span>{{ $orderBuckets['awaiting_review'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="paid">{{ $adminCopy['order_filter_paid'] ?? 'Paid' }} <span>{{ $orderBuckets['paid'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="failed">{{ $adminCopy['order_filter_failed'] ?? 'Failed' }} <span>{{ $orderBuckets['failed'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="canceled">{{ $adminCopy['order_filter_canceled'] ?? 'Canceled' }} <span>{{ $orderBuckets['canceled'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="with_slip">{{ $adminCopy['order_filter_with_slip'] ?? 'With Slip' }} <span>{{ $orderBuckets['with_slip'] }}</span></button>
                                </div>
                            </div>

                            <div class="admin-table-scroll">
                                <table class="admin-table admin-orders-table">
                                    <thead>
                                        <tr>
                                            <th>ORDER</th>
                                            <th>CUSTOMER</th>
                                            <th>TOTAL</th>
                                            <th>PAYMENT</th>
                                            <th>SHIPPING</th>
                                            <th>ITEMS</th>
                                            <th>SLIP</th>
                                            <th>CREATED</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody data-admin-order-rows>
                                        @foreach ($rows as $row)
                                            @php
                                                $orderSearchBlob = implode(' ', array_filter([
                                                    $row['order_id'] ?? '',
                                                    $row['user_email'] ?? '',
                                                    $row['address']['full_name'] ?? '',
                                                    $row['address']['province'] ?? '',
                                                ]));
                                            @endphp
                                            <tr
                                                data-admin-order-row
                                                data-status="{{ $row['status'] }}"
                                                data-has-slip="{{ !empty($row['has_slip']) ? '1' : '0' }}"
                                                data-search="{{ strtolower($orderSearchBlob) }}"
                                            >
                                                <td>{{ $row['order_id'] }}</td>
                                                <td>
                                                    <strong>{{ $row['address']['full_name'] ?? '-' }}</strong><br>
                                                    {{ $row['user_email'] }}
                                                </td>
                                                <td>{{ $row['total_amount'] }} {{ $row['currency'] }}</td>
                                                <td><span class="admin-status-pill is-order-{{ str_replace('_', '-', $row['status']) }}">{{ $orderStatusLabels[$row['status']] ?? strtoupper((string) $row['status']) }}</span></td>
                                                <td>{{ $shippingStatusLabels[$row['shipping_status'] ?? ''] ?? (($row['shipping_status'] ?? '') !== '' ? strtoupper((string) $row['shipping_status']) : '-') }}</td>
                                                <td>{{ $row['item_count'] ?? 0 }}</td>
                                                <td>{{ !empty($row['has_slip']) ? 'YES' : 'NO' }}</td>
                                                <td>{{ $row['created_at'] ?: '-' }}</td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="admin-mini-btn"
                                                        data-admin-order-detail-open
                                                        data-order-id="{{ $row['order_id'] }}"
                                                    >
                                                        Manage
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="admin-empty" data-admin-orders-empty hidden>No orders match the current filters.</p>
                        </section>
                    @elseif ($moduleKey === 'products')
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>IMAGE</th>
                                        <th>NAME</th>
                                        <th>PRICE</th>
                                        <th>LIMITED</th>
                                        <th>PUBLIC</th>
                                        <th>SOON</th>
                                        <th>PAID SOLD</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row['id'] }}</td>
                                            <td>
                                                <div class="admin-product-table-preview">
                                                    @if (!empty($row['image']))
                                                        <img src="{{ $row['image'] }}" alt="{{ $row['alt'] ?: $row['name'] }}" loading="lazy">
                                                    @else
                                                        <span>NO IMAGE</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $row['name'] }}</td>
                                            <td>{{ $row['price_thb'] }}</td>
                                            <td>{{ $row['limited_qty'] }}</td>
                                            <td>{{ $row['is_public'] ? 'YES' : 'NO' }}</td>
                                            <td>{{ $row['coming_soon'] ? 'YES' : 'NO' }}</td>
                                            <td>{{ $row['paid_sold_qty'] }}</td>
                                            <td>
                                                <div class="admin-row-actions">
                                                    <button
                                                        type="button"
                                                        class="admin-mini-btn"
                                                        data-admin-product-edit-open
                                                        data-product-id="{{ $row['id'] }}"
                                                        data-product-name="{{ e($row['name']) }}"
                                                        data-product-price="{{ $row['price_thb'] }}"
                                                        data-product-image="{{ e($row['image']) }}"
                                                        data-product-alt="{{ e((string) $row['alt']) }}"
                                                        data-product-sort-order="{{ $row['sort_order'] }}"
                                                        data-product-limited-qty="{{ $row['limited_qty'] }}"
                                                        data-product-is-public="{{ $row['is_public'] ? '1' : '0' }}"
                                                        data-product-coming-soon="{{ $row['coming_soon'] ? '1' : '0' }}"
                                                        data-product-description="{{ e((string) $row['description']) }}"
                                                        data-update-action="{{ route('admin.products.update', ['productId' => $row['id'], 'lang' => $storefrontLocale]) }}"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form method="post" action="{{ route('admin.products.delete', ['productId' => $row['id'], 'lang' => $storefrontLocale]) }}" class="admin-inline-form danger-inline" data-admin-product-delete-form data-product-name="{{ e($row['name']) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="admin-mini-btn danger">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($moduleKey === 'inventory')
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>PRODUCT</th>
                                        <th>ON HAND</th>
                                        <th>THRESHOLD</th>
                                        <th>STATUS</th>
                                        <th>AVG COST</th>
                                        <th>LAST COST</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>
                                                <div class="admin-inventory-product-cell">
                                                    @if (!empty($row['image']))
                                                        <img src="{{ $row['image'] }}" alt="{{ $row['name'] }}">
                                                    @endif
                                                    <p>#{{ $row['product_id'] }} {{ $row['name'] }}</p>
                                                </div>
                                            </td>
                                            <td>{{ $row['qty_on_hand'] }}</td>
                                            <td>
                                                <form method="post" action="{{ route('admin.inventory.threshold', ['productId' => $row['product_id'], 'lang' => $storefrontLocale]) }}" class="admin-row-actions admin-threshold-form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input name="low_stock_threshold" type="number" min="0" value="{{ $row['low_stock_threshold'] }}" class="admin-cell-input">
                                                    <button type="submit" class="admin-mini-btn">Save</button>
                                                </form>
                                            </td>
                                            <td>
                                                <span class="admin-badge {{ !empty($row['is_low_stock']) ? 'admin-badge-low' : 'admin-badge-ok' }}">
                                                    {{ !empty($row['is_low_stock']) ? 'LOW STOCK' : 'OK' }}
                                                </span>
                                            </td>
                                            <td>{{ $row['avg_unit_cost'] }}</td>
                                            <td>{{ $row['last_unit_cost'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>TIME</th>
                                        <th>PRODUCT</th>
                                        <th>TYPE</th>
                                        <th>QUANTITY</th>
                                        <th>NOTE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($inventoryRecentMovements as $movement)
                                        <tr>
                                            <td>{{ $movement['created_at'] }}</td>
                                            <td>#{{ $movement['product_id'] }} {{ $movement['product_name'] }}</td>
                                            <td>{{ strtoupper(str_replace('_', ' ', (string) $movement['movement_type'])) }}</td>
                                            <td>{{ $movement['quantity'] }}</td>
                                            <td>{{ $movement['note'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">No inventory movement yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif ($moduleKey === 'customers')
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>EMAIL</th>
                                        <th>ROLE</th>
                                        <th>PHONE</th>
                                        <th>ORDERS</th>
                                        <th>SPEND</th>
                                        <th>STATUS</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row['id'] }}</td>
                                            <td>{{ $row['email'] }}</td>
                                            <td>{{ strtoupper((string) $row['role']) }}</td>
                                            <td>{{ $row['phone'] }}</td>
                                            <td>{{ $row['total_orders'] }}</td>
                                            <td>{{ $row['total_spend'] }}</td>
                                            <td>{{ strtoupper((string) $row['account_status']) }}</td>
                                            <td>
                                                <details class="admin-row-details">
                                                    <summary>Edit</summary>
                                                    <form method="post" action="{{ route('admin.customers.status', ['userId' => $row['id'], 'lang' => $storefrontLocale]) }}" class="admin-form-grid compact">
                                                        @csrf
                                                        @method('PATCH')
                                                        <select name="account_status">
                                                            @foreach (['active', 'suspended'] as $status)
                                                                <option value="{{ $status }}" {{ ($row['account_status'] ?? '') === $status ? 'selected' : '' }}>{{ strtoupper($status) }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input name="suspended_reason" type="text" value="{{ $row['suspended_reason'] }}" placeholder="Suspend reason (optional)">
                                                        <button type="submit" class="admin-mini-btn">Save</button>
                                                    </form>
                                                </details>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($moduleKey === 'payments')
                        @php
                            $paymentDetails = $rows;
                        @endphp
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ORDER</th>
                                        <th>EMAIL</th>
                                        <th>TOTAL</th>
                                        <th>STATUS</th>
                                        <th>SLIP</th>
                                        <th>UPLOADED</th>
                                        <th>DETAIL</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $index => $row)
                                        <tr>
                                            <td>{{ $row['order_id'] }}</td>
                                            <td>{{ $row['user_email'] }}</td>
                                            <td>{{ $row['total_amount'] }} {{ $row['currency'] }}</td>
                                            <td>{{ strtoupper((string) $row['status']) }}</td>
                                            <td>{{ !empty($row['payment_slip_data']) ? 'YES' : 'NO' }}</td>
                                            <td>{{ $row['payment_slip_uploaded_at'] }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="admin-mini-btn"
                                                    data-admin-payment-detail-open
                                                    data-order-id="{{ $row['order_id'] }}"
                                                >
                                                    Detail
                                                </button>
                                            </td>
                                            <td>
                                                {{-- <details class="admin-row-details"> --}}
                                                    {{-- <summary>Review</summary> --}}
                                                    {{-- @if (!empty($row['payment_slip_data']))
                                                        <div class="admin-payment-slip-preview">
                                                            <div class="admin-payment-slip-preview-head">
                                                                <p>PAYMENT SLIP</p>
                                                                <span>Tap to inspect</span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                class="admin-slip-btn"
                                                                data-admin-slip-open
                                                                data-slip-src="{{ $row['payment_slip_data'] }}"
                                                                data-slip-order="{{ $row['order_id'] }}"
                                                                aria-label="Preview payment slip {{ $row['order_id'] }}"
                                                            >
                                                                <img class="admin-slip-thumb" src="{{ $row['payment_slip_data'] }}" alt="Payment slip {{ $row['order_id'] }}">
                                                            </button>
                                                        </div>
                                                    @endif --}}
                                                {{-- </details> --}}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="8">
                                                <details class="admin-row-details">
                                                    <summary>Review</summary>
                                                    <div class="admin-payment-slip-preview">
                                                        <div class="admin-payment-slip-preview-head">
                                                            <p>PAYMENT SLIP</p>
                                                            <span>Tap to inspect</span>
                                                            <form method="post" action="{{ route('admin.payments.status', ['orderId' => $row['order_id'], 'lang' => $storefrontLocale]) }}" class="admin-form-grid compact">
                                                                @csrf
                                                                @method('PATCH')
                                                                <select name="status">
                                                                    @foreach (['pending', 'awaiting_review', 'paid', 'failed', 'canceled'] as $status)
                                                                        <option value="{{ $status }}" {{ ($row['status'] ?? '') === $status ? 'selected' : '' }}>{{ strtoupper($status) }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <input name="note" type="text" placeholder="Note (optional)">
                                                                <button type="submit" class="admin-mini-btn">Save</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="8"><hr></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($moduleKey === 'shipping')
                        @php
                            $shippingCollection = collect($rows);
                            $shippingBuckets = [
                                'all' => $shippingCollection->count(),
                                'pending_fulfillment' => $shippingCollection->where('shipping_status', 'pending_fulfillment')->count(),
                                'processing' => $shippingCollection->where('shipping_status', 'processing')->count(),
                                'shipped' => $shippingCollection->where('shipping_status', 'shipped')->count(),
                                'delivered' => $shippingCollection->where('shipping_status', 'delivered')->count(),
                                'overdue' => $shippingCollection->where('is_overdue', true)->count(),
                                'missing_tracking' => $shippingCollection->where('has_tracking', false)->count(),
                            ];
                        @endphp
                        <section class="admin-shipments-board">
                            <div class="admin-shipments-toolbar">
                                <div class="admin-shipments-search">
                                    <label for="admin-shipping-search" class="admin-detail-label">SEARCH</label>
                                    <input id="admin-shipping-search" type="search" placeholder="Order, email, carrier, tracking" data-admin-shipping-search>
                                </div>
                                <div class="admin-shipments-filters" data-admin-shipping-filters>
                                    <button type="button" class="admin-filter-chip is-active" data-filter="all">{{ $adminCopy['shipping_filter_all'] ?? 'All' }} <span>{{ $shippingBuckets['all'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="pending_fulfillment">{{ $adminCopy['shipping_filter_ready'] ?? 'Ready' }} <span>{{ $shippingBuckets['pending_fulfillment'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="processing">{{ $adminCopy['shipping_filter_processing'] ?? 'Packing' }} <span>{{ $shippingBuckets['processing'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="shipped">{{ $adminCopy['shipping_filter_shipped'] ?? 'Shipped' }} <span>{{ $shippingBuckets['shipped'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="delivered">{{ $adminCopy['shipping_filter_delivered'] ?? 'Delivered' }} <span>{{ $shippingBuckets['delivered'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="overdue">{{ $adminCopy['shipping_filter_overdue'] ?? 'Overdue' }} <span>{{ $shippingBuckets['overdue'] }}</span></button>
                                    <button type="button" class="admin-filter-chip" data-filter="missing_tracking">{{ $adminCopy['shipping_filter_missing_tracking'] ?? 'Missing Tracking' }} <span>{{ $shippingBuckets['missing_tracking'] }}</span></button>
                                </div>
                            </div>

                            <div class="admin-table-scroll">
                                <table class="admin-table admin-shipping-table">
                                    <thead>
                                        <tr>
                                            <th>ORDER</th>
                                            <th>CUSTOMER</th>
                                            <th>PAYMENT</th>
                                            <th>SHIPPING</th>
                                            <th>ITEMS</th>
                                            <th>TRACKING</th>
                                            <th>READY</th>
                                            <th>FLAGS</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody data-admin-shipping-rows>
                                        @foreach ($rows as $row)
                                            @php
                                                $searchBlob = implode(' ', array_filter([
                                                    $row['order_id'] ?? '',
                                                    $row['user_email'] ?? '',
                                                    $row['tracking_number'] ?? '',
                                                    $row['shipping_carrier'] ?? '',
                                                    $row['address']['full_name'] ?? '',
                                                ]));
                                                $flagText = collect([
                                                    !empty($row['is_overdue']) ? 'OVERDUE' : null,
                                                    empty($row['has_tracking']) ? 'TRACKING MISSING' : null,
                                                ])->filter()->implode(' / ');
                                            @endphp
                                            <tr
                                                data-admin-shipping-row
                                                data-status="{{ $row['shipping_status'] }}"
                                                data-overdue="{{ !empty($row['is_overdue']) ? '1' : '0' }}"
                                                data-has-tracking="{{ !empty($row['has_tracking']) ? '1' : '0' }}"
                                                data-search="{{ strtolower($searchBlob) }}"
                                            >
                                                <td>{{ $row['order_id'] }}</td>
                                                <td>
                                                    <strong>{{ $row['address']['full_name'] ?? '-' }}</strong><br>
                                                    {{ $row['user_email'] }}
                                                </td>
                                                <td>{{ strtoupper((string) $row['payment_status']) }}</td>
                                                <td><span class="admin-status-pill is-{{ str_replace('_', '-', $row['shipping_status']) }}">{{ $shippingStatusLabels[$row['shipping_status']] ?? strtoupper((string) $row['shipping_status']) }}</span></td>
                                                <td>{{ $row['item_count'] ?? 0 }}</td>
                                                <td>
                                                    <strong>{{ $row['tracking_number'] ?: '-' }}</strong><br>
                                                    {{ $row['shipping_carrier'] ?: '-' }}
                                                </td>
                                                <td>{{ $row['created_at'] ?: '-' }}</td>
                                                <td>{{ $flagText !== '' ? $flagText : '-' }}</td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="admin-mini-btn"
                                                        data-admin-shipping-detail-open
                                                        data-order-id="{{ $row['order_id'] }}"
                                                    >
                                                        Manage
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="admin-empty" data-admin-shipping-empty hidden>No shipments match the current filters.</p>
                        </section>
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        @foreach (array_keys($rows[0]) as $column)
                                            <th>{{ str_replace('_', ' ', strtoupper($column)) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            @foreach ($row as $value)
                                                <td>{{ is_scalar($value) || $value === null ? (string) $value : json_encode($value) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <p class="admin-empty">{{ $adminCopy['empty'] ?? 'No records available yet.' }}</p>
                @endif
            </div>
        </article>
    </section>

    @if ($moduleKey === 'payments')
        <div class="admin-slip-modal" data-admin-slip-modal hidden>
            <button type="button" class="admin-slip-modal-backdrop" data-admin-slip-close aria-label="Close slip preview"></button>
            <section class="admin-slip-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-slip-modal-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-slip-modal-title">PAYMENT SLIP</h2>
                    <button type="button" data-admin-slip-close aria-label="Close preview">X</button>
                </header>
                <img src="" alt="" data-admin-slip-image>
            </section>
        </div>
        <div class="admin-slip-modal" data-admin-payment-detail-modal hidden>
            <button type="button" class="admin-slip-modal-backdrop" data-admin-payment-detail-close aria-label="Close payment detail"></button>
            <section class="admin-slip-modal-card admin-payment-detail-card" role="dialog" aria-modal="true" aria-labelledby="admin-payment-detail-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-payment-detail-title">PAYMENT DETAIL</h2>
                    <button type="button" data-admin-payment-detail-close aria-label="Close payment detail">X</button>
                </header>
                <div class="admin-payment-detail-grid" data-admin-payment-detail-grid></div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">PAYMENT SLIP</p>
                    <button type="button" class="admin-payment-detail-slip-btn" data-admin-payment-detail-slip-open hidden>
                        <img src="" alt="" data-admin-payment-detail-slip hidden>
                    </button>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">LINE ITEMS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>SIZE</th>
                                    <th>QTY</th>
                                    <th>UNIT</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody data-admin-payment-detail-lines></tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">PAYMENT EVENTS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>AT</th>
                                    <th>FROM</th>
                                    <th>TO</th>
                                    <th>ACTOR</th>
                                    <th>ROLE</th>
                                    <th>NOTE</th>
                                </tr>
                            </thead>
                            <tbody data-admin-payment-detail-events></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <script type="application/json" id="admin-payment-detail-data">
            @json($paymentDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
        </script>
    @endif

    @if ($moduleKey === 'shipping')
        <div class="admin-slip-modal" data-admin-shipping-detail-modal hidden>
            <button type="button" class="admin-slip-modal-backdrop" data-admin-shipping-detail-close aria-label="Close shipment detail"></button>
            <section class="admin-slip-modal-card admin-payment-detail-card" role="dialog" aria-modal="true" aria-labelledby="admin-shipping-detail-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-shipping-detail-title">SHIPMENT DETAIL</h2>
                    <button type="button" data-admin-shipping-detail-close aria-label="Close shipment detail">X</button>
                </header>
                <div class="admin-payment-detail-grid" data-admin-shipping-detail-grid></div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">SHIPPING ADDRESS</p>
                    <div class="admin-shipping-address" data-admin-shipping-address></div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">LINE ITEMS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>SIZE</th>
                                    <th>QTY</th>
                                    <th>UNIT</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody data-admin-shipping-lines></tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">SHIPMENT ACTIONS</p>
                    <form method="post" action="" class="admin-form-grid admin-shipping-detail-form" data-admin-shipping-update-form data-action-template="{{ route('admin.shipping.update', ['orderId' => '__ORDER__', 'lang' => $storefrontLocale]) }}">
                        @csrf
                        @method('PATCH')
                        <select name="shipping_status" data-admin-shipping-status-input>
                            @foreach (['pending_fulfillment', 'processing', 'shipped', 'delivered'] as $status)
                                <option value="{{ $status }}">{{ $shippingStatusLabels[$status] ?? strtoupper($status) }}</option>
                            @endforeach
                        </select>
                        <input name="tracking_number" type="text" placeholder="Tracking number" data-admin-shipping-tracking-input>
                        <input name="shipping_carrier" type="text" placeholder="Carrier" data-admin-shipping-carrier-input>
                        <input name="note" type="text" placeholder="Note (optional)" data-admin-shipping-note-input>
                        <button type="submit" class="admin-action-btn">Save Shipment Update</button>
                    </form>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">SHIPPING EVENTS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>AT</th>
                                    <th>FROM</th>
                                    <th>TO</th>
                                    <th>TRACKING</th>
                                    <th>CARRIER</th>
                                    <th>ACTOR</th>
                                    <th>NOTE</th>
                                </tr>
                            </thead>
                            <tbody data-admin-shipping-events></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <script type="application/json" id="admin-shipping-detail-data">
            @json($shippingDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
        </script>
        <script type="application/json" id="admin-shipping-status-labels">
            @json($shippingStatusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
        </script>
    @endif

    @if ($moduleKey === 'orders')
        <div class="admin-slip-modal" data-admin-order-detail-modal hidden>
            <button type="button" class="admin-slip-modal-backdrop" data-admin-order-detail-close aria-label="Close order detail"></button>
            <section class="admin-slip-modal-card admin-procurement-detail-card" role="dialog" aria-modal="true" aria-labelledby="admin-order-detail-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-order-detail-title">ORDER DETAIL</h2>
                    <button type="button" data-admin-order-detail-close aria-label="Close order detail">X</button>
                </header>
                <div class="admin-payment-detail-grid" data-admin-order-detail-grid></div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">SHIPPING ADDRESS</p>
                    <div class="admin-shipping-address" data-admin-order-address></div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">LINE ITEMS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>SIZE</th>
                                    <th>QTY</th>
                                    <th>UNIT</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody data-admin-order-lines></tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">ORDER ACTIONS</p>
                    <form method="post" action="" class="admin-form-grid admin-order-detail-form" data-admin-order-update-form data-action-template="{{ route('admin.orders.status', ['orderId' => '__ORDER__', 'lang' => $storefrontLocale]) }}">
                        @csrf
                        @method('PATCH')
                        <select name="status" data-admin-order-status-input>
                            @foreach (['pending', 'awaiting_review', 'paid', 'failed', 'canceled'] as $status)
                                <option value="{{ $status }}">{{ $orderStatusLabels[$status] ?? strtoupper($status) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="admin-action-btn">Save Order Update</button>
                    </form>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">PAYMENT EVENTS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>AT</th>
                                    <th>FROM</th>
                                    <th>TO</th>
                                    <th>ACTOR</th>
                                    <th>ROLE</th>
                                    <th>NOTE</th>
                                </tr>
                            </thead>
                            <tbody data-admin-order-payment-events></tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">SHIPPING EVENTS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>AT</th>
                                    <th>FROM</th>
                                    <th>TO</th>
                                    <th>TRACKING</th>
                                    <th>CARRIER</th>
                                    <th>ACTOR</th>
                                    <th>NOTE</th>
                                </tr>
                            </thead>
                            <tbody data-admin-order-shipping-events></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <script type="application/json" id="admin-order-detail-data">
            @json($orderDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
        </script>
        <script type="application/json" id="admin-order-status-labels">
            @json($orderStatusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
        </script>
        <script type="application/json" id="admin-order-shipping-status-labels">
            @json($shippingStatusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
        </script>
    @endif

    @if ($moduleKey === 'procurement')
        <div
            class="admin-slip-modal"
            data-admin-procurement-detail-modal
            hidden
        >
            <button type="button" class="admin-slip-modal-backdrop" data-admin-procurement-detail-close aria-label="Close purchase order detail"></button>
            <section class="admin-slip-modal-card admin-procurement-detail-card" role="dialog" aria-modal="true" aria-labelledby="admin-procurement-detail-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-procurement-detail-title">PO DETAIL</h2>
                    <button type="button" data-admin-procurement-detail-close aria-label="Close purchase order detail">X</button>
                </header>
                <div class="admin-payment-detail-grid" data-admin-procurement-detail-grid></div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">OTHER COSTS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>NOTE</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody data-admin-procurement-extra-costs></tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">LINE ITEMS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>ORDERED</th>
                                    <th>RECEIVED</th>
                                    <th>REMAINING</th>
                                    <th>UNIT COST</th>
                                    <th>RECEIVE NOW</th>
                                </tr>
                            </thead>
                            <tbody data-admin-procurement-detail-items></tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">POST GOODS RECEIPT</p>
                    <form method="post" action="" class="admin-form-grid admin-procurement-detail-form" data-admin-procurement-receive-form>
                        @csrf
                        <input name="note" type="text" placeholder="Receipt note" data-admin-procurement-receive-note>
                        <div class="admin-procurement-lines" data-admin-procurement-receive-lines></div>
                        <div class="admin-row-actions">
                            {{-- <a href="{{ route('admin.procurement', ['lang' => $storefrontLocale]) }}" class="admin-mini-btn" data-admin-procurement-manage-link>Open Page</a> --}}
                            <button type="submit" class="admin-action-btn" data-admin-procurement-receive-submit>Receive Stock</button>
                        </div>
                    </form>
                </div>
                <div class="admin-payment-detail-section">
                    <p class="admin-detail-label">RECEIPTS</p>
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>RECEIPT</th>
                                    <th>UNITS</th>
                                    <th>NOTE</th>
                                    <th>CREATED</th>
                                </tr>
                            </thead>
                            <tbody data-admin-procurement-receipts></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    @endif

@if ($moduleKey === 'procurement')
    @push('meta')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.querySelector('[data-admin-procurement-form]');
                if (!form) {
                    return;
                }

                var itemContainer = form.querySelector('[data-admin-procurement-items]');
                var itemTemplate = form.querySelector('[data-admin-procurement-item-template]');
                var addLineButton = form.querySelector('[data-admin-procurement-add-line]');
                var extraCostContainer = form.querySelector('[data-admin-procurement-extra-costs]');
                var extraCostTemplate = form.querySelector('[data-admin-procurement-extra-cost-template]');
                var addCostButton = form.querySelector('[data-admin-procurement-add-cost]');

                var reindex = function (selector, prefix) {
                    var rows = form.querySelectorAll(selector);
                    rows.forEach(function (row, index) {
                        row.querySelectorAll('[name]').forEach(function (field) {
                            field.name = field.name.replace(new RegExp(prefix + '\\\\[(\\\\d+|__INDEX__)\\\\]'), prefix + '[' + index + ']');
                        });
                    });
                };

                var syncButtons = function () {
                    var lineButtons = form.querySelectorAll('[data-admin-procurement-remove-line]');
                    lineButtons.forEach(function (button) {
                        button.disabled = lineButtons.length <= 1;
                    });

                    var costButtons = form.querySelectorAll('[data-admin-procurement-remove-cost]');
                    costButtons.forEach(function (button) {
                        button.disabled = costButtons.length <= 1;
                    });
                };

                if (addLineButton && itemContainer && itemTemplate) {
                    addLineButton.addEventListener('click', function () {
                        var index = itemContainer.querySelectorAll('[data-admin-procurement-item]').length;
                        var wrapper = document.createElement('div');
                        wrapper.innerHTML = itemTemplate.innerHTML.replace(/__INDEX__/g, String(index)).trim();
                        var nextRow = wrapper.firstElementChild;
                        nextRow.setAttribute('data-admin-procurement-item', '');
                        itemContainer.appendChild(nextRow);
                        reindex('[data-admin-procurement-item]', 'items');
                        syncButtons();
                    });
                }

                if (addCostButton && extraCostContainer && extraCostTemplate) {
                    addCostButton.addEventListener('click', function () {
                        var index = extraCostContainer.querySelectorAll('[data-admin-procurement-extra-cost]').length;
                        var wrapper = document.createElement('div');
                        wrapper.innerHTML = extraCostTemplate.innerHTML.replace(/__INDEX__/g, String(index)).trim();
                        var nextRow = wrapper.firstElementChild;
                        nextRow.setAttribute('data-admin-procurement-extra-cost', '');
                        extraCostContainer.appendChild(nextRow);
                        reindex('[data-admin-procurement-extra-cost]', 'extra_costs');
                        syncButtons();
                    });
                }

                form.addEventListener('click', function (event) {
                    var removeLineButton = event.target.closest('[data-admin-procurement-remove-line]');
                    if (removeLineButton) {
                        event.preventDefault();
                        var row = removeLineButton.closest('[data-admin-procurement-item]');
                        if (row && itemContainer.querySelectorAll('[data-admin-procurement-item]').length > 1) {
                            row.remove();
                            reindex('[data-admin-procurement-item]', 'items');
                            syncButtons();
                        }
                    }

                    var removeCostButton = event.target.closest('[data-admin-procurement-remove-cost]');
                    if (removeCostButton) {
                        event.preventDefault();
                        var costRow = removeCostButton.closest('[data-admin-procurement-extra-cost]');
                        if (costRow && extraCostContainer.querySelectorAll('[data-admin-procurement-extra-cost]').length > 1) {
                            costRow.remove();
                            reindex('[data-admin-procurement-extra-cost]', 'extra_costs');
                            syncButtons();
                        }
                    }
                });

                syncButtons();

                var detailModal = document.querySelector('[data-admin-procurement-detail-modal]');
                var detailOpenButtons = document.querySelectorAll('[data-admin-procurement-detail-open]');
                var detailCloseButtons = document.querySelectorAll('[data-admin-procurement-detail-close]');
                var detailTitle = document.querySelector('#admin-procurement-detail-title');
                var detailGrid = document.querySelector('[data-admin-procurement-detail-grid]');
                var detailExtraCosts = document.querySelector('[data-admin-procurement-extra-costs]');
                var detailItems = document.querySelector('[data-admin-procurement-detail-items]');
                var detailReceipts = document.querySelector('[data-admin-procurement-receipts]');
                var receiveForm = document.querySelector('[data-admin-procurement-receive-form]');
                var receiveNote = document.querySelector('[data-admin-procurement-receive-note]');
                var receiveLines = document.querySelector('[data-admin-procurement-receive-lines]');
                var receiveSubmit = document.querySelector('[data-admin-procurement-receive-submit]');

                var escapeHtml = function (value) {
                    return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                };

                var renderTableRows = function (container, rows, mapFn, emptyCols, emptyMessage) {
                    if (!container) {
                        return;
                    }

                    if (!rows.length) {
                        container.innerHTML = '<tr><td colspan="' + emptyCols + '">' + escapeHtml(emptyMessage || '-') + '</td></tr>';
                        return;
                    }

                    container.innerHTML = rows.map(mapFn).join('');
                };

                var openDetailModal = function (detail) {
                    if (!detailModal || !detailTitle || !detailGrid || !detailExtraCosts || !detailItems || !detailReceipts || !receiveForm || !receiveNote || !receiveLines || !receiveSubmit) {
                        return;
                    }

                    var header = detail.purchase_order || {};
                    var items = Array.isArray(detail.items) ? detail.items : [];
                    var extraCosts = Array.isArray(detail.extra_costs) ? detail.extra_costs : [];
                    var receipts = Array.isArray(detail.receipts) ? detail.receipts : [];
                    var status = String(header.status || '').toLowerCase();

                    detailTitle.textContent = 'PO DETAIL / ' + (header.id || '');
                    detailGrid.innerHTML = [
                        ['PO', header.id || '-'],
                        ['SUPPLIER', header.supplier_name || '-'],
                        ['STATUS', String(header.status || '-').toUpperCase()],
                        ['SUBTOTAL', String(header.subtotal_amount || '0') + ' THB'],
                        ['OTHER COSTS', String(header.extra_cost_amount || '0') + ' THB'],
                        ['TOTAL', String(header.total_amount || '0') + ' THB'],
                        ['NOTE', header.note || '-'],
                        ['CREATED', header.created_at || '-'],
                        ['UPDATED', header.updated_at || '-']
                    ].map(function (item) {
                        return '<div class="admin-detail-cell"><p class="admin-detail-label">' + escapeHtml(item[0]) + '</p><p>' + escapeHtml(item[1]) + '</p></div>';
                    }).join('');

                    renderTableRows(detailExtraCosts, extraCosts, function (cost) {
                        return '<tr><td>' + escapeHtml(cost.cost_name || '-') + '</td><td>' + escapeHtml(String(cost.amount || '0')) + ' THB</td></tr>';
                    }, 2, 'No extra costs.');

                    renderTableRows(detailItems, items, function (item) {
                        var remaining = Math.max(0, Number(item.ordered_qty || 0) - Number(item.received_qty || 0));
                        var cost = item.effective_unit_cost || item.unit_cost || 0;
                        return '<tr><td>#' + escapeHtml(item.product_id || '-') + ' ' + escapeHtml(item.product_name || '-') + '</td><td>' + escapeHtml(item.ordered_qty || 0) + '</td><td>' + escapeHtml(item.received_qty || 0) + '</td><td>' + escapeHtml(remaining) + '</td><td>' + escapeHtml(String(cost)) + ' THB</td><td>' + (remaining > 0 ? 'Ready' : '-') + '</td></tr>';
                    }, 6, 'No line items.');

                    renderTableRows(detailReceipts, receipts, function (receipt) {
                        return '<tr><td>' + escapeHtml(receipt.id || '-') + '</td><td>' + escapeHtml(receipt.total_units || 0) + '</td><td>' + escapeHtml(receipt.note || '-') + '</td><td>' + escapeHtml(receipt.created_at || '-') + '</td></tr>';
                    }, 4, 'No receipts yet.');

                    receiveForm.action = detail.receive_action || '';
                    receiveNote.value = detail.old_note || '';
                    receiveLines.innerHTML = items.map(function (item, index) {
                        var remaining = Math.max(0, Number(item.ordered_qty || 0) - Number(item.received_qty || 0));
                        var value = item.old_received_qty != null ? item.old_received_qty : '';
                        return [
                            '<div class="admin-procurement-line">',
                            '<input type="text" value="#' + escapeHtml(item.product_id || '-') + ' ' + escapeHtml(item.product_name || '-') + '" readonly>',
                            '<input type="hidden" name="items[' + index + '][purchase_order_item_id]" value="' + escapeHtml(item.id || '') + '">',
                            '<input name="items[' + index + '][received_qty]" type="number" min="0" max="' + escapeHtml(remaining) + '" step="1" value="' + escapeHtml(value) + '"' + (remaining <= 0 ? ' disabled' : '') + '>',
                            '<input type="text" value="' + escapeHtml(remaining) + ' remaining" readonly>',
                            '</div>'
                        ].join('');
                    }).join('');

                    receiveSubmit.disabled = ['approved', 'partially_received'].indexOf(status) === -1;
                    detailModal.hidden = false;
                };

                var closeDetailModal = function () {
                    if (detailModal) {
                        detailModal.hidden = true;
                    }
                };

                detailOpenButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var detail = {};

                        try {
                            detail = JSON.parse(button.getAttribute('data-po-detail') || '{}');
                        } catch (error) {
                            detail = {};
                        }

                        openDetailModal(detail);
                    });
                });

                detailCloseButtons.forEach(function (button) {
                    button.addEventListener('click', closeDetailModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && detailModal && detailModal.hidden === false) {
                        closeDetailModal();
                    }
                });
            });
        </script>
    @endpush
@endif

    @if ($moduleKey === 'products')
        <div class="admin-slip-modal" data-admin-product-edit-modal {{ $editingProduct ? '' : 'hidden' }}>
            <button type="button" class="admin-slip-modal-backdrop" data-admin-product-edit-close aria-label="Close product editor"></button>
            <section class="admin-slip-modal-card admin-product-edit-card" role="dialog" aria-modal="true" aria-labelledby="admin-product-edit-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-product-edit-title">EDIT PRODUCT</h2>
                    <button type="button" data-admin-product-edit-close aria-label="Close product editor">X</button>
                </header>
                <p class="admin-status admin-product-edit-status" data-admin-product-edit-heading>
                    {{ $editingProduct ? 'Editing product #'.$editingProduct['id'] : 'Editing product' }}
                </p>
                <form
                    method="post"
                    action="{{ $editingProduct ? route('admin.products.update', ['productId' => $editingProduct['id'], 'lang' => $storefrontLocale]) : route('admin.products.update', ['productId' => 0, 'lang' => $storefrontLocale]) }}"
                    class="admin-form-grid admin-product-edit-form"
                    data-admin-product-edit-form
                    data-admin-product-image-src="{{ old('image', $editingProduct['image'] ?? '') }}"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @method('PATCH')
                    <input name="name" type="text" value="{{ old('name', $editingProduct['name'] ?? '') }}" placeholder="Name" required>
                    <input name="price_thb" type="number" min="0" value="{{ old('price_thb', $editingProduct['price_thb'] ?? '') }}" placeholder="Price THB" required>
                    <label class="admin-upload-field">
                        <span>Upload new image</span>
                        <input name="image_file" type="file" accept="image/png,image/jpeg,image/webp" data-admin-product-image-file-input>
                    </label>
                    <input name="alt" type="text" value="{{ old('alt', $editingProduct['alt'] ?? '') }}" placeholder="Alt text">
                    <div class="admin-product-image-preview admin-product-edit-preview" data-admin-product-image-preview>
                        <img src="" alt="Product preview" data-admin-product-image-preview-img hidden>
                        <p class="admin-product-image-preview-empty" data-admin-product-image-preview-empty>Image preview</p>
                    </div>
                    <input name="sort_order" type="number" value="{{ old('sort_order', $editingProduct['sort_order'] ?? 0) }}" placeholder="Sort order">
                    <input name="limited_qty" type="number" min="0" value="{{ old('limited_qty', $editingProduct['limited_qty'] ?? 40) }}" placeholder="Limited qty">
                    <div class="admin-check-group admin-product-edit-checks">
                        <label class="admin-check"><input type="checkbox" name="is_public" value="1" {{ ($editingProductHasOldInput ? old('is_public') : ($editingProduct['is_public'] ?? 0)) ? 'checked' : '' }}> <span>Public</span></label>
                        <label class="admin-check"><input type="checkbox" name="coming_soon" value="1" {{ ($editingProductHasOldInput ? old('coming_soon') : ($editingProduct['coming_soon'] ?? 0)) ? 'checked' : '' }}> <span>Coming soon</span></label>
                    </div>
                    <textarea name="description" placeholder="Description">{{ old('description', $editingProduct['description'] ?? '') }}</textarea>
                    <div class="admin-form-actions admin-product-edit-actions">
                        <button type="submit" class="admin-action-btn">Save Product</button>
                    </div>
                </form>
            </section>
        </div>
        <div class="admin-slip-modal" data-admin-product-delete-modal hidden>
            <button type="button" class="admin-slip-modal-backdrop" data-admin-product-delete-close aria-label="Close delete confirmation"></button>
            <section class="admin-slip-modal-card admin-product-delete-card" role="dialog" aria-modal="true" aria-labelledby="admin-product-delete-title">
                <header class="admin-slip-modal-head">
                    <h2 id="admin-product-delete-title">DELETE PRODUCT</h2>
                    <button type="button" data-admin-product-delete-close aria-label="Close delete confirmation">X</button>
                </header>
                <p class="admin-product-delete-copy" data-admin-product-delete-copy>Delete this product?</p>
                <div class="admin-row-actions admin-product-delete-actions">
                    <button type="button" class="admin-mini-btn" data-admin-product-delete-close>Cancel</button>
                    <button type="button" class="admin-mini-btn danger" data-admin-product-delete-confirm>Delete</button>
                </div>
            </section>
        </div>
    @endif
@endsection

@if ($moduleKey === 'payments')
    @push('meta')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.querySelector('[data-admin-slip-modal]');
                var image = document.querySelector('[data-admin-slip-image]');
                var title = document.querySelector('#admin-slip-modal-title');
                var openButtons = document.querySelectorAll('[data-admin-slip-open]');
                var closeButtons = document.querySelectorAll('[data-admin-slip-close]');
                var detailModal = document.querySelector('[data-admin-payment-detail-modal]');
                var detailOpenButtons = document.querySelectorAll('[data-admin-payment-detail-open]');
                var detailCloseButtons = document.querySelectorAll('[data-admin-payment-detail-close]');
                var detailDataNode = document.querySelector('#admin-payment-detail-data');
                var detailTitle = document.querySelector('#admin-payment-detail-title');
                var detailGrid = document.querySelector('[data-admin-payment-detail-grid]');
                var detailSlipButton = document.querySelector('[data-admin-payment-detail-slip-open]');
                var detailSlip = document.querySelector('[data-admin-payment-detail-slip]');
                var detailLines = document.querySelector('[data-admin-payment-detail-lines]');
                var detailEvents = document.querySelector('[data-admin-payment-detail-events]');
                var paymentDetailsByOrderId = {};

                if (detailDataNode) {
                    try {
                        paymentDetailsByOrderId = JSON.parse(detailDataNode.textContent || '[]').reduce(function (carry, item) {
                            if (item && item.order_id) {
                                carry[item.order_id] = item;
                            }

                            return carry;
                        }, {});
                    } catch (error) {
                        paymentDetailsByOrderId = {};
                    }
                }

                var closeModal = function () {
                    if (!modal || !image) {
                        return;
                    }

                    modal.hidden = true;
                    image.src = '';
                    image.alt = '';
                };

                var openSlipModal = function (src, orderId) {
                    if (!modal || !image || !title || !src) {
                        return;
                    }

                    image.src = src;
                    image.alt = 'Payment slip ' + orderId;
                    title.textContent = 'PAYMENT SLIP / ' + orderId;
                    modal.hidden = false;
                };

                openButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        openSlipModal(
                            button.getAttribute('data-slip-src') || '',
                            button.getAttribute('data-slip-order') || ''
                        );
                    });
                });

                var renderRows = function (container, rows, mapFn, emptyCols) {
                    if (!container) {
                        return;
                    }

                    if (!rows.length) {
                        container.innerHTML = '<tr><td colspan="' + emptyCols + '">-</td></tr>';
                        return;
                    }

                    container.innerHTML = rows.map(mapFn).join('');
                };

                var closeDetailModal = function () {
                    if (detailModal) {
                        detailModal.hidden = true;
                    }
                };

                detailOpenButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!detailModal || !detailTitle || !detailGrid || !detailSlipButton || !detailSlip || !detailLines || !detailEvents) {
                            return;
                        }

                        var orderId = button.getAttribute('data-order-id') || '';
                        var detail = paymentDetailsByOrderId[orderId] || {};

                        detailTitle.textContent = 'PAYMENT DETAIL / ' + (detail.order_id || orderId);
                        detailGrid.innerHTML = [
                            ['ORDER', detail.order_id || '-'],
                            ['EMAIL', detail.user_email || '-'],
                            ['STATUS', String(detail.status || '-').toUpperCase()],
                            ['TOTAL', String(detail.total_amount || '-') + ' ' + String(detail.currency || '')],
                            ['UPLOADED', detail.payment_slip_uploaded_at || '-'],
                            ['CREATED', detail.created_at || '-']
                        ].map(function (item) {
                            return '<div class="admin-detail-cell"><p class="admin-detail-label">' + item[0] + '</p><p>' + item[1] + '</p></div>';
                        }).join('');

                        if (detail.payment_slip_data) {
                            detailSlip.hidden = false;
                            detailSlipButton.hidden = false;
                            detailSlipButton.setAttribute('data-slip-src', detail.payment_slip_data);
                            detailSlipButton.setAttribute('data-slip-order', detail.order_id || '');
                            detailSlip.src = detail.payment_slip_data;
                            detailSlip.alt = 'Payment slip ' + (detail.order_id || '');
                        } else {
                            detailSlipButton.hidden = true;
                            detailSlipButton.removeAttribute('data-slip-src');
                            detailSlipButton.removeAttribute('data-slip-order');
                            detailSlip.hidden = true;
                            detailSlip.src = '';
                            detailSlip.alt = '';
                        }

                        renderRows(detailLines, detail.lines || [], function (line) {
                            return '<tr><td>' + (line.name || '-') + '</td><td>' + (line.size || '-') + '</td><td>' + (line.quantity || '-') + '</td><td>' + (line.unit_amount || '-') + '</td><td>' + (line.line_total || '-') + '</td></tr>';
                        }, 5);

                        renderRows(detailEvents, detail.events || [], function (event) {
                            return '<tr><td>' + (event.created_at || '-') + '</td><td>' + (event.from_status || '-') + '</td><td>' + (event.to_status || '-') + '</td><td>' + (event.actor_id || '-') + '</td><td>' + (event.actor_role || '-') + '</td><td>' + (event.note || '-') + '</td></tr>';
                        }, 6);

                        detailModal.hidden = false;
                    });
                });

                closeButtons.forEach(function (button) {
                    button.addEventListener('click', closeModal);
                });

                if (detailSlipButton) {
                    detailSlipButton.addEventListener('click', function () {
                        openSlipModal(
                            detailSlipButton.getAttribute('data-slip-src') || '',
                            detailSlipButton.getAttribute('data-slip-order') || ''
                        );
                    });
                }

                detailCloseButtons.forEach(function (button) {
                    button.addEventListener('click', closeDetailModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.hidden === false) {
                        closeModal();
                    }

                    if (event.key === 'Escape' && detailModal && detailModal.hidden === false) {
                        closeDetailModal();
                    }
                });
            });
        </script>
    @endpush
@endif

@if ($moduleKey === 'shipping')
    @push('meta')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var detailDataNode = document.querySelector('#admin-shipping-detail-data');
                var statusLabelsNode = document.querySelector('#admin-shipping-status-labels');
                var detailOpenButtons = document.querySelectorAll('[data-admin-shipping-detail-open]');
                var detailModal = document.querySelector('[data-admin-shipping-detail-modal]');
                var detailCloseButtons = document.querySelectorAll('[data-admin-shipping-detail-close]');
                var detailTitle = document.querySelector('#admin-shipping-detail-title');
                var detailGrid = document.querySelector('[data-admin-shipping-detail-grid]');
                var detailAddress = document.querySelector('[data-admin-shipping-address]');
                var detailLines = document.querySelector('[data-admin-shipping-lines]');
                var detailEvents = document.querySelector('[data-admin-shipping-events]');
                var updateForm = document.querySelector('[data-admin-shipping-update-form]');
                var statusInput = document.querySelector('[data-admin-shipping-status-input]');
                var trackingInput = document.querySelector('[data-admin-shipping-tracking-input]');
                var carrierInput = document.querySelector('[data-admin-shipping-carrier-input]');
                var noteInput = document.querySelector('[data-admin-shipping-note-input]');
                var searchInput = document.querySelector('[data-admin-shipping-search]');
                var filterButtons = document.querySelectorAll('[data-admin-shipping-filters] [data-filter]');
                var shippingRows = document.querySelectorAll('[data-admin-shipping-row]');
                var emptyState = document.querySelector('[data-admin-shipping-empty]');
                var shippingDetailsByOrderId = {};
                var shippingStatusLabels = {};
                var activeFilter = 'all';

                var escapeHtml = function (value) {
                    return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                };

                var renderTableRows = function (container, rows, mapFn, emptyCols, emptyText) {
                    if (!container) {
                        return;
                    }

                    if (!rows.length) {
                        container.innerHTML = '<tr><td colspan="' + emptyCols + '">' + escapeHtml(emptyText || '-') + '</td></tr>';
                        return;
                    }

                    container.innerHTML = rows.map(mapFn).join('');
                };

                if (detailDataNode) {
                    try {
                        shippingDetailsByOrderId = JSON.parse(detailDataNode.textContent || '[]').reduce(function (carry, item) {
                            if (item && item.order_id) {
                                carry[item.order_id] = item;
                            }

                            return carry;
                        }, {});
                    } catch (error) {
                        shippingDetailsByOrderId = {};
                    }
                }

                if (statusLabelsNode) {
                    try {
                        shippingStatusLabels = JSON.parse(statusLabelsNode.textContent || '{}');
                    } catch (error) {
                        shippingStatusLabels = {};
                    }
                }

                var shippingStatusLabel = function (status) {
                    return shippingStatusLabels[status] || String(status || '-').toUpperCase();
                };

                var applyFilters = function () {
                    var query = searchInput ? String(searchInput.value || '').toLowerCase().trim() : '';
                    var visibleCount = 0;

                    shippingRows.forEach(function (row) {
                        var rowStatus = row.getAttribute('data-status') || '';
                        var isOverdue = row.getAttribute('data-overdue') === '1';
                        var hasTracking = row.getAttribute('data-has-tracking') === '1';
                        var searchBlob = row.getAttribute('data-search') || '';

                        var matchesFilter = activeFilter === 'all'
                            || (activeFilter === 'overdue' && isOverdue)
                            || (activeFilter === 'missing_tracking' && !hasTracking)
                            || rowStatus === activeFilter;
                        var matchesSearch = query === '' || searchBlob.indexOf(query) !== -1;
                        var visible = matchesFilter && matchesSearch;

                        row.hidden = !visible;
                        if (visible) {
                            visibleCount += 1;
                        }
                    });

                    if (emptyState) {
                        emptyState.hidden = visibleCount !== 0;
                    }
                };

                filterButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        activeFilter = button.getAttribute('data-filter') || 'all';
                        filterButtons.forEach(function (node) {
                            node.classList.toggle('is-active', node === button);
                        });
                        applyFilters();
                    });
                });

                if (searchInput) {
                    searchInput.addEventListener('input', applyFilters);
                }

                var closeDetailModal = function () {
                    if (detailModal) {
                        detailModal.hidden = true;
                    }
                };

                detailOpenButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!detailModal || !detailTitle || !detailGrid || !detailAddress || !detailLines || !detailEvents || !updateForm || !statusInput || !trackingInput || !carrierInput || !noteInput) {
                            return;
                        }

                        var orderId = button.getAttribute('data-order-id') || '';
                        var detail = shippingDetailsByOrderId[orderId] || {};
                        var address = detail.address || {};

                        detailTitle.textContent = 'SHIPMENT DETAIL / ' + (detail.order_id || orderId);
                        detailGrid.innerHTML = [
                            ['ORDER', detail.order_id || '-'],
                            ['EMAIL', detail.user_email || '-'],
                            ['PAYMENT', String(detail.payment_status || '-').toUpperCase()],
                            ['SHIPPING', shippingStatusLabel(detail.shipping_status || '-')],
                            ['TOTAL', String(detail.total_amount || '-') + ' ' + String(detail.currency || '')],
                            ['READY', detail.created_at || '-']
                        ].map(function (item) {
                            return '<div class="admin-detail-cell"><p class="admin-detail-label">' + escapeHtml(item[0]) + '</p><p>' + escapeHtml(item[1]) + '</p></div>';
                        }).join('');

                        detailAddress.innerHTML = [
                            '<p><strong>' + escapeHtml(address.full_name || '-') + '</strong></p>',
                            '<p>' + escapeHtml(address.phone || '-') + '</p>',
                            '<p>' + escapeHtml(address.address_line1 || '-') + '</p>',
                            address.address_line2 ? '<p>' + escapeHtml(address.address_line2) + '</p>' : '',
                            '<p>' + escapeHtml([address.district, address.province, address.postal_code].filter(Boolean).join(' ' ) || '-') + '</p>'
                        ].join('');

                        renderTableRows(detailLines, detail.lines || [], function (line) {
                            return '<tr><td>' + escapeHtml(line.name || '-') + '</td><td>' + escapeHtml(line.size || '-') + '</td><td>' + escapeHtml(line.quantity || '-') + '</td><td>' + escapeHtml(line.unit_amount || '-') + '</td><td>' + escapeHtml(line.line_total || '-') + '</td></tr>';
                        }, 5, 'No items.');

                        renderTableRows(detailEvents, detail.events || [], function (event) {
                            var trackingMeta = event.tracking_number || '-';
                            var carrierMeta = event.shipping_carrier || '-';
                            return '<tr><td>' + escapeHtml(event.created_at || '-') + '</td><td>' + escapeHtml(shippingStatusLabel(event.from_status || '-')) + '</td><td>' + escapeHtml(shippingStatusLabel(event.to_status || '-')) + '</td><td>' + escapeHtml(trackingMeta) + '</td><td>' + escapeHtml(carrierMeta) + '</td><td>' + escapeHtml(event.actor_id || '-') + ' / ' + escapeHtml(event.actor_role || '-') + '</td><td>' + escapeHtml(event.note || '-') + '</td></tr>';
                        }, 7, 'No shipment events.');

                        updateForm.action = String(updateForm.getAttribute('data-action-template') || '').replace('__ORDER__', detail.order_id || orderId);
                        statusInput.value = detail.shipping_status || 'pending_fulfillment';
                        trackingInput.value = detail.tracking_number || '';
                        carrierInput.value = detail.shipping_carrier || '';
                        noteInput.value = '';

                        detailModal.hidden = false;
                    });
                });

                detailCloseButtons.forEach(function (button) {
                    button.addEventListener('click', closeDetailModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && detailModal && detailModal.hidden === false) {
                        closeDetailModal();
                    }
                });

                applyFilters();
            });
        </script>
    @endpush
@endif

@if ($moduleKey === 'orders')
    @push('meta')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var detailDataNode = document.querySelector('#admin-order-detail-data');
                var orderLabelsNode = document.querySelector('#admin-order-status-labels');
                var shippingLabelsNode = document.querySelector('#admin-order-shipping-status-labels');
                var detailOpenButtons = document.querySelectorAll('[data-admin-order-detail-open]');
                var detailModal = document.querySelector('[data-admin-order-detail-modal]');
                var detailCloseButtons = document.querySelectorAll('[data-admin-order-detail-close]');
                var detailTitle = document.querySelector('#admin-order-detail-title');
                var detailGrid = document.querySelector('[data-admin-order-detail-grid]');
                var detailAddress = document.querySelector('[data-admin-order-address]');
                var detailLines = document.querySelector('[data-admin-order-lines]');
                var paymentEvents = document.querySelector('[data-admin-order-payment-events]');
                var shippingEvents = document.querySelector('[data-admin-order-shipping-events]');
                var updateForm = document.querySelector('[data-admin-order-update-form]');
                var statusInput = document.querySelector('[data-admin-order-status-input]');
                var searchInput = document.querySelector('[data-admin-orders-search]');
                var filterButtons = document.querySelectorAll('[data-admin-orders-filters] [data-filter]');
                var orderRows = document.querySelectorAll('[data-admin-order-row]');
                var emptyState = document.querySelector('[data-admin-orders-empty]');
                var orderDetailsById = {};
                var orderStatusLabels = {};
                var shippingStatusLabels = {};
                var activeFilter = 'all';

                var escapeHtml = function (value) {
                    return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                };

                var renderTableRows = function (container, rows, mapFn, emptyCols, emptyText) {
                    if (!container) {
                        return;
                    }

                    if (!rows.length) {
                        container.innerHTML = '<tr><td colspan="' + emptyCols + '">' + escapeHtml(emptyText || '-') + '</td></tr>';
                        return;
                    }

                    container.innerHTML = rows.map(mapFn).join('');
                };

                if (detailDataNode) {
                    try {
                        orderDetailsById = JSON.parse(detailDataNode.textContent || '[]').reduce(function (carry, item) {
                            if (item && item.order_id) {
                                carry[item.order_id] = item;
                            }

                            return carry;
                        }, {});
                    } catch (error) {
                        orderDetailsById = {};
                    }
                }

                if (orderLabelsNode) {
                    try {
                        orderStatusLabels = JSON.parse(orderLabelsNode.textContent || '{}');
                    } catch (error) {
                        orderStatusLabels = {};
                    }
                }

                if (shippingLabelsNode) {
                    try {
                        shippingStatusLabels = JSON.parse(shippingLabelsNode.textContent || '{}');
                    } catch (error) {
                        shippingStatusLabels = {};
                    }
                }

                var orderStatusLabel = function (status) {
                    return orderStatusLabels[status] || String(status || '-').toUpperCase();
                };

                var shippingStatusLabel = function (status) {
                    return shippingStatusLabels[status] || String(status || '-').toUpperCase();
                };

                var applyFilters = function () {
                    var query = searchInput ? String(searchInput.value || '').toLowerCase().trim() : '';
                    var visibleCount = 0;

                    orderRows.forEach(function (row) {
                        var rowStatus = row.getAttribute('data-status') || '';
                        var hasSlip = row.getAttribute('data-has-slip') === '1';
                        var searchBlob = row.getAttribute('data-search') || '';
                        var matchesFilter = activeFilter === 'all'
                            || (activeFilter === 'with_slip' && hasSlip)
                            || rowStatus === activeFilter;
                        var matchesSearch = query === '' || searchBlob.indexOf(query) !== -1;
                        var visible = matchesFilter && matchesSearch;

                        row.hidden = !visible;
                        if (visible) {
                            visibleCount += 1;
                        }
                    });

                    if (emptyState) {
                        emptyState.hidden = visibleCount !== 0;
                    }
                };

                filterButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        activeFilter = button.getAttribute('data-filter') || 'all';
                        filterButtons.forEach(function (node) {
                            node.classList.toggle('is-active', node === button);
                        });
                        applyFilters();
                    });
                });

                if (searchInput) {
                    searchInput.addEventListener('input', applyFilters);
                }

                var closeDetailModal = function () {
                    if (detailModal) {
                        detailModal.hidden = true;
                    }
                };

                detailOpenButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!detailModal || !detailTitle || !detailGrid || !detailAddress || !detailLines || !paymentEvents || !shippingEvents || !updateForm || !statusInput) {
                            return;
                        }

                        var orderId = button.getAttribute('data-order-id') || '';
                        var detail = orderDetailsById[orderId] || {};
                        var address = detail.address || {};

                        detailTitle.textContent = 'ORDER DETAIL / ' + (detail.order_id || orderId);
                        detailGrid.innerHTML = [
                            ['ORDER', detail.order_id || '-'],
                            ['EMAIL', detail.user_email || '-'],
                            ['PAYMENT', orderStatusLabel(detail.status || '-')],
                            ['SHIPPING', shippingStatusLabel(detail.shipping_status || '-')],
                            ['TOTAL', String(detail.total_amount || '-') + ' ' + String(detail.currency || '')],
                            ['SLIP', detail.has_slip ? 'YES' : 'NO']
                        ].map(function (item) {
                            return '<div class="admin-detail-cell"><p class="admin-detail-label">' + escapeHtml(item[0]) + '</p><p>' + escapeHtml(item[1]) + '</p></div>';
                        }).join('');

                        detailAddress.innerHTML = [
                            '<p><strong>' + escapeHtml(address.full_name || '-') + '</strong></p>',
                            '<p>' + escapeHtml(address.phone || '-') + '</p>',
                            '<p>' + escapeHtml(address.address_line1 || '-') + '</p>',
                            address.address_line2 ? '<p>' + escapeHtml(address.address_line2) + '</p>' : '',
                            '<p>' + escapeHtml([address.district, address.province, address.postal_code].filter(Boolean).join(' ') || '-') + '</p>'
                        ].join('');

                        renderTableRows(detailLines, detail.lines || [], function (line) {
                            return '<tr><td>' + escapeHtml(line.name || '-') + '</td><td>' + escapeHtml(line.size || '-') + '</td><td>' + escapeHtml(line.quantity || '-') + '</td><td>' + escapeHtml(line.unit_amount || '-') + '</td><td>' + escapeHtml(line.line_total || '-') + '</td></tr>';
                        }, 5, 'No items.');

                        renderTableRows(paymentEvents, detail.payment_events || [], function (event) {
                            return '<tr><td>' + escapeHtml(event.created_at || '-') + '</td><td>' + escapeHtml(orderStatusLabel(event.from_status || '-')) + '</td><td>' + escapeHtml(orderStatusLabel(event.to_status || '-')) + '</td><td>' + escapeHtml(event.actor_id || '-') + '</td><td>' + escapeHtml(event.actor_role || '-') + '</td><td>' + escapeHtml(event.note || '-') + '</td></tr>';
                        }, 6, 'No payment events.');

                        renderTableRows(shippingEvents, detail.shipping_events || [], function (event) {
                            return '<tr><td>' + escapeHtml(event.created_at || '-') + '</td><td>' + escapeHtml(shippingStatusLabel(event.from_status || '-')) + '</td><td>' + escapeHtml(shippingStatusLabel(event.to_status || '-')) + '</td><td>' + escapeHtml(event.tracking_number || '-') + '</td><td>' + escapeHtml(event.shipping_carrier || '-') + '</td><td>' + escapeHtml(event.actor_id || '-') + ' / ' + escapeHtml(event.actor_role || '-') + '</td><td>' + escapeHtml(event.note || '-') + '</td></tr>';
                        }, 7, 'No shipping events.');

                        updateForm.action = String(updateForm.getAttribute('data-action-template') || '').replace('__ORDER__', detail.order_id || orderId);
                        statusInput.value = detail.status || 'pending';
                        detailModal.hidden = false;
                    });
                });

                detailCloseButtons.forEach(function (button) {
                    button.addEventListener('click', closeDetailModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && detailModal && detailModal.hidden === false) {
                        closeDetailModal();
                    }
                });

                applyFilters();
            });
        </script>
    @endpush
@endif

@if ($moduleKey === 'products')
    @push('meta')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var createForm = document.querySelector('[data-admin-product-create-form]');
                var modal = document.querySelector('[data-admin-product-edit-modal]');
                var deleteModal = document.querySelector('[data-admin-product-delete-modal]');
                var deleteModalCopy = document.querySelector('[data-admin-product-delete-copy]');
                var deleteConfirmButton = document.querySelector('[data-admin-product-delete-confirm]');
                var form = document.querySelector('[data-admin-product-edit-form]');
                var heading = document.querySelector('[data-admin-product-edit-heading]');
                var openButtons = document.querySelectorAll('[data-admin-product-edit-open]');
                var closeButtons = document.querySelectorAll('[data-admin-product-edit-close]');
                var deleteForms = document.querySelectorAll('[data-admin-product-delete-form]');
                var deleteCloseButtons = document.querySelectorAll('[data-admin-product-delete-close]');
                var pendingDeleteForm = null;

                if (!createForm && (!modal || !form || !heading)) {
                    return;
                }

                var bindImagePreview = function (scope) {
                    if (!scope) {
                        return null;
                    }

                    var input = scope.querySelector('[data-admin-product-image-input], [name="image"]');
                    var fileInput = scope.querySelector('[data-admin-product-image-file-input]');
                    var image = scope.querySelector('[data-admin-product-image-preview-img]');
                    var empty = scope.querySelector('[data-admin-product-image-preview-empty]');

                    if ((!input && !fileInput) || !image || !empty) {
                        return null;
                    }

                    var showEmpty = function (message) {
                        image.hidden = true;
                        image.removeAttribute('src');
                        empty.hidden = false;
                        empty.textContent = message;
                    };

                    var showSource = function (nextSrc) {
                        var resolvedSrc = String(nextSrc || '').trim();
                        if (resolvedSrc === '') {
                            showEmpty('Image preview');
                            return;
                        }

                        image.onerror = function () {
                            showEmpty('Image not found');
                        };
                        image.onload = function () {
                            empty.hidden = true;
                            image.hidden = false;
                        };
                        image.src = resolvedSrc;
                        image.hidden = false;
                        empty.hidden = true;
                    };

                    var sync = function (value) {
                        var src = String(value || '').trim();
                        showSource(src);
                    };

                    if (input) {
                        input.addEventListener('input', function () {
                            sync(input.value);
                        });
                    }

                    if (fileInput) {
                        fileInput.addEventListener('change', function () {
                            var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                            if (!file) {
                                sync(input ? input.value : '');
                                return;
                            }

                            if (!String(file.type || '').match(/^image\//)) {
                                showEmpty('Invalid image file');
                                return;
                            }

                            var reader = new FileReader();
                            reader.onload = function (event) {
                                showSource(event.target && event.target.result ? event.target.result : '');
                            };
                            reader.onerror = function () {
                                showEmpty('Image preview failed');
                            };
                            reader.readAsDataURL(file);
                        });
                    }

                    sync((input ? input.value : '') || scope.getAttribute('data-admin-product-image-src') || '');
                    return { input: input, fileInput: fileInput, sync: sync, showSource: showSource };
                };

                bindImagePreview(createForm);

                if (!modal || !form || !heading) {
                    return;
                }

                var fields = {
                    name: form.querySelector('[name="name"]'),
                    price: form.querySelector('[name="price_thb"]'),
                    image: form.querySelector('[name="image"]'),
                    alt: form.querySelector('[name="alt"]'),
                    sortOrder: form.querySelector('[name="sort_order"]'),
                    limitedQty: form.querySelector('[name="limited_qty"]'),
                    isPublic: form.querySelector('[name="is_public"]'),
                    comingSoon: form.querySelector('[name="coming_soon"]'),
                    description: form.querySelector('[name="description"]')
                };
                var editImagePreview = bindImagePreview(form);

                var closeModal = function () {
                    modal.hidden = true;
                };

                var openModal = function (button) {
                    var productId = button.getAttribute('data-product-id') || '';
                    form.action = button.getAttribute('data-update-action') || form.action;
                    heading.textContent = 'Editing product #' + productId;
                    if (fields.name) fields.name.value = button.getAttribute('data-product-name') || '';
                    if (fields.price) fields.price.value = button.getAttribute('data-product-price') || '';
                    if (fields.alt) fields.alt.value = button.getAttribute('data-product-alt') || '';
                    if (fields.sortOrder) fields.sortOrder.value = button.getAttribute('data-product-sort-order') || '0';
                    if (fields.limitedQty) fields.limitedQty.value = button.getAttribute('data-product-limited-qty') || '0';
                    if (fields.isPublic) fields.isPublic.checked = button.getAttribute('data-product-is-public') === '1';
                    if (fields.comingSoon) fields.comingSoon.checked = button.getAttribute('data-product-coming-soon') === '1';
                    if (fields.description) fields.description.value = button.getAttribute('data-product-description') || '';
                    form.setAttribute('data-admin-product-image-src', button.getAttribute('data-product-image') || '');
                    if (editImagePreview && editImagePreview.fileInput) editImagePreview.fileInput.value = '';
                    if (editImagePreview) editImagePreview.sync(button.getAttribute('data-product-image') || '');
                    modal.hidden = false;
                };

                openButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        openModal(button);
                    });
                });

                closeButtons.forEach(function (button) {
                    button.addEventListener('click', closeModal);
                });

                var closeDeleteModal = function () {
                    pendingDeleteForm = null;
                    if (deleteModal) {
                        deleteModal.hidden = true;
                    }
                };

                var openDeleteModal = function (deleteForm) {
                    pendingDeleteForm = deleteForm;
                    if (deleteModalCopy) {
                        var productName = deleteForm.getAttribute('data-product-name') || 'this product';
                        deleteModalCopy.textContent = 'Delete ' + productName + '? This action cannot be undone.';
                    }
                    if (deleteModal) {
                        deleteModal.hidden = false;
                    }
                };

                deleteForms.forEach(function (deleteForm) {
                    deleteForm.addEventListener('submit', function (event) {
                        event.preventDefault();
                        openDeleteModal(deleteForm);
                    });
                });

                deleteCloseButtons.forEach(function (button) {
                    button.addEventListener('click', closeDeleteModal);
                });

                if (deleteConfirmButton) {
                    deleteConfirmButton.addEventListener('click', function () {
                        if (!pendingDeleteForm) {
                            closeDeleteModal();
                            return;
                        }

                        var formToSubmit = pendingDeleteForm;
                        closeDeleteModal();
                        formToSubmit.submit();
                    });
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.hidden === false) {
                        closeModal();
                    }

                    if (event.key === 'Escape' && deleteModal && deleteModal.hidden === false) {
                        closeDeleteModal();
                    }
                });
            });
        </script>
    @endpush
@endif
