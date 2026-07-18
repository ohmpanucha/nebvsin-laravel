<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncInventoryAndProcurementTables extends Migration
{
    public function up()
    {
        $this->ensureReferenceKey('orders', 'id', 'uniq_orders_id');
        $this->ensureReferenceKey('users', 'id', 'uniq_users_id');
        $this->ensureReferenceKey('products', 'id', 'uniq_products_id');
        $this->syncOrdersInventoryColumns();
        $this->syncProductInventoryTable();
        $this->syncInventoryMovementsTable();
        $this->syncSuppliersTable();
        $this->ensureReferenceKey('suppliers', 'id', 'uniq_suppliers_id');
        $this->syncPurchaseOrdersTable();
        $this->ensureReferenceKey('purchase_orders', 'id', 'uniq_purchase_orders_id');
        $this->syncPurchaseOrderItemsTable();
        $this->ensureReferenceKey('purchase_order_items', 'id', 'uniq_purchase_order_items_id');
        $this->syncGoodsReceiptsTable();
        $this->ensureReferenceKey('goods_receipts', 'id', 'uniq_goods_receipts_id');
        $this->syncGoodsReceiptItemsTable();
        $this->syncPurchaseOrderExtraCostsTable();
        $this->syncIndexes();
    }

    public function down()
    {
        // Keep legacy inventory and procurement tables intact on rollback.
    }

    protected function syncOrdersInventoryColumns()
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'inventory_applied_at')) {
                $table->timestamp('inventory_applied_at')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('orders', 'inventory_reverted_at')) {
                $table->timestamp('inventory_reverted_at')->nullable()->after('inventory_applied_at');
            }
        });
    }

    protected function syncProductInventoryTable()
    {
        if (! Schema::hasTable('product_inventory')) {
            Schema::create('product_inventory', function (Blueprint $table) {
                $table->integer('product_id')->primary();
                $table->integer('qty_on_hand')->default(0);
                $table->integer('low_stock_threshold')->default(5);
                $table->decimal('avg_unit_cost', 12, 2)->default(0);
                $table->decimal('last_unit_cost', 12, 2)->default(0);
                $table->timestamp('updated_at')->nullable();

                $table->foreign('product_id', 'fk_product_inventory_product')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');
            });

            return;
        }

        Schema::table('product_inventory', function (Blueprint $table) {
            if (! Schema::hasColumn('product_inventory', 'avg_unit_cost')) {
                $table->decimal('avg_unit_cost', 12, 2)->default(0)->after('low_stock_threshold');
            }

            if (! Schema::hasColumn('product_inventory', 'last_unit_cost')) {
                $table->decimal('last_unit_cost', 12, 2)->default(0)->after('avg_unit_cost');
            }
        });
    }

    protected function syncInventoryMovementsTable()
    {
        $userIdDefinition = $this->getColumnDefinition('users', 'id', 'string', 191);

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) use ($userIdDefinition) {
                $table->string('id', 191)->primary();
                $table->integer('product_id');
                $table->enum('movement_type', ['receive', 'deduct', 'order_deduct', 'order_restock', 'procurement_receive']);
                $table->integer('quantity');
                $table->string('note', 512)->nullable();
                $this->addColumnByDefinition($table, 'created_by_user_id', array_merge($userIdDefinition, ['nullable' => true]));
                $table->string('reference_type', 32)->nullable();
                $table->string('reference_id', 191)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('product_id', 'fk_inventory_movements_product')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');
                $table->foreign('created_by_user_id', 'fk_inventory_movements_user')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            return;
        }

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'reference_type')) {
                $table->string('reference_type', 32)->nullable()->after('created_by_user_id');
            }

            if (! Schema::hasColumn('inventory_movements', 'reference_id')) {
                $table->string('reference_id', 191)->nullable()->after('reference_type');
            }
        });

        $this->expandInventoryMovementEnum();
    }

    protected function syncSuppliersTable()
    {
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('name', 255);
                $table->string('contact_name', 255)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('note', 512)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    protected function syncPurchaseOrdersTable()
    {
        $userIdDefinition = $this->getColumnDefinition('users', 'id', 'string', 191);

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) use ($userIdDefinition) {
                $table->string('id', 191)->primary();
                $table->string('supplier_id', 191);
                $table->enum('status', ['draft', 'approved', 'partially_received', 'received', 'canceled'])->default('draft');
                $table->string('note', 512)->nullable();
                $table->decimal('subtotal_amount', 12, 2)->default(0);
                $table->decimal('extra_cost_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $this->addColumnByDefinition($table, 'created_by_user_id', array_merge($userIdDefinition, ['nullable' => true]));
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('supplier_id', 'fk_purchase_orders_supplier')
                    ->references('id')
                    ->on('suppliers');
                $table->foreign('created_by_user_id', 'fk_purchase_orders_user')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'extra_cost_amount')) {
                $table->decimal('extra_cost_amount', 12, 2)->default(0)->after('subtotal_amount');
            }

            if (! Schema::hasColumn('purchase_orders', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('extra_cost_amount');
            }
        });
    }

    protected function syncPurchaseOrderItemsTable()
    {
        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('purchase_order_id', 191);
                $table->integer('product_id');
                $table->integer('ordered_qty');
                $table->integer('received_qty')->default(0);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->decimal('allocated_extra_cost', 12, 2)->default(0);
                $table->decimal('effective_unit_cost', 12, 4)->default(0);
                $table->decimal('effective_line_total', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('purchase_order_id', 'fk_po_items_order')
                    ->references('id')
                    ->on('purchase_orders')
                    ->onDelete('cascade');
                $table->foreign('product_id', 'fk_po_items_product')
                    ->references('id')
                    ->on('products');
            });

            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT chk_po_qty CHECK (ordered_qty > 0)');

            return;
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'allocated_extra_cost')) {
                $table->decimal('allocated_extra_cost', 12, 2)->default(0)->after('line_total');
            }

            if (! Schema::hasColumn('purchase_order_items', 'effective_unit_cost')) {
                $table->decimal('effective_unit_cost', 12, 4)->default(0)->after('allocated_extra_cost');
            }

            if (! Schema::hasColumn('purchase_order_items', 'effective_line_total')) {
                $table->decimal('effective_line_total', 12, 2)->default(0)->after('effective_unit_cost');
            }
        });
    }

    protected function syncGoodsReceiptsTable()
    {
        $userIdDefinition = $this->getColumnDefinition('users', 'id', 'string', 191);

        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) use ($userIdDefinition) {
                $table->string('id', 191)->primary();
                $table->string('purchase_order_id', 191);
                $table->string('note', 512)->nullable();
                $this->addColumnByDefinition($table, 'received_by_user_id', array_merge($userIdDefinition, ['nullable' => true]));
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('purchase_order_id', 'fk_goods_receipts_order')
                    ->references('id')
                    ->on('purchase_orders')
                    ->onDelete('cascade');
                $table->foreign('received_by_user_id', 'fk_goods_receipts_user')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    protected function syncGoodsReceiptItemsTable()
    {
        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('goods_receipt_id', 191);
                $table->string('purchase_order_item_id', 191);
                $table->integer('product_id');
                $table->integer('received_qty');
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('goods_receipt_id', 'fk_gr_items_receipt')
                    ->references('id')
                    ->on('goods_receipts')
                    ->onDelete('cascade');
                $table->foreign('purchase_order_item_id', 'fk_gr_items_po_item')
                    ->references('id')
                    ->on('purchase_order_items');
                $table->foreign('product_id', 'fk_gr_items_product')
                    ->references('id')
                    ->on('products');
            });

            DB::statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT chk_gr_qty CHECK (received_qty > 0)');
        }
    }

    protected function syncPurchaseOrderExtraCostsTable()
    {
        if (! Schema::hasTable('purchase_order_extra_costs')) {
            Schema::create('purchase_order_extra_costs', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('purchase_order_id', 191);
                $table->string('cost_name', 255);
                $table->decimal('amount', 12, 2)->default(0);
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('purchase_order_id', 'fk_po_extra_costs_order')
                    ->references('id')
                    ->on('purchase_orders')
                    ->onDelete('cascade');
            });
        }
    }

    protected function syncIndexes()
    {
        if (Schema::hasTable('inventory_movements')) {
            $this->createIndexIfMissing(
                'inventory_movements',
                'idx_inventory_movements_product_created_at',
                'CREATE INDEX idx_inventory_movements_product_created_at ON inventory_movements(product_id, created_at)'
            );
            $this->createIndexIfMissing(
                'inventory_movements',
                'idx_inventory_movements_reference_created',
                'CREATE INDEX idx_inventory_movements_reference_created ON inventory_movements(reference_type, reference_id, created_at)'
            );
            $this->createIndexIfMissing(
                'inventory_movements',
                'uniq_inventory_order_movement',
                'CREATE UNIQUE INDEX uniq_inventory_order_movement ON inventory_movements(reference_type, reference_id, movement_type, product_id)'
            );
        }

        if (Schema::hasTable('purchase_orders')) {
            $this->createIndexIfMissing(
                'purchase_orders',
                'idx_purchase_orders_status_created',
                'CREATE INDEX idx_purchase_orders_status_created ON purchase_orders(status, created_at)'
            );
        }

        if (Schema::hasTable('purchase_order_items')) {
            $this->createIndexIfMissing(
                'purchase_order_items',
                'idx_purchase_order_items_order',
                'CREATE INDEX idx_purchase_order_items_order ON purchase_order_items(purchase_order_id, product_id)'
            );
        }

        if (Schema::hasTable('goods_receipts')) {
            $this->createIndexIfMissing(
                'goods_receipts',
                'idx_goods_receipts_order_created',
                'CREATE INDEX idx_goods_receipts_order_created ON goods_receipts(purchase_order_id, created_at)'
            );
        }

        if (Schema::hasTable('purchase_order_extra_costs')) {
            $this->createIndexIfMissing(
                'purchase_order_extra_costs',
                'idx_po_extra_costs_order_sort',
                'CREATE INDEX idx_po_extra_costs_order_sort ON purchase_order_extra_costs(purchase_order_id, sort_order)'
            );
        }
    }

    protected function expandInventoryMovementEnum()
    {
        if (! Schema::hasTable('inventory_movements') || ! Schema::hasColumn('inventory_movements', 'movement_type')) {
            return;
        }

        DB::statement(
            "ALTER TABLE inventory_movements MODIFY COLUMN movement_type ENUM('receive', 'deduct', 'order_deduct', 'order_restock', 'procurement_receive') NOT NULL"
        );
    }

    protected function createIndexIfMissing($table, $indexName, $statement)
    {
        if (! $this->indexExists($table, $indexName)) {
            DB::statement($statement);
        }
    }

    protected function indexExists($table, $indexName)
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return ! empty($result);
    }

    protected function ensureReferenceKey($table, $column, $indexName)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->hasUniqueOrPrimaryKey($table, $column)) {
            return;
        }

        if ($this->columnHasDuplicates($table, $column)) {
            throw new RuntimeException(sprintf(
                'Cannot create foreign keys for %s.%s because duplicate values exist and the column is not unique.',
                $table,
                $column
            ));
        }

        DB::statement(sprintf('CREATE UNIQUE INDEX %s ON %s(%s)', $indexName, $table, $column));
    }

    protected function hasUniqueOrPrimaryKey($table, $column)
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND column_name = ? AND non_unique = 0 LIMIT 1',
            [$database, $table, $column]
        );

        return ! empty($result);
    }

    protected function columnHasDuplicates($table, $column)
    {
        $result = DB::selectOne(
            sprintf(
                'SELECT COUNT(*) AS total_rows, COUNT(DISTINCT %s) AS distinct_rows FROM %s',
                $column,
                $table
            )
        );

        if (! $result) {
            return false;
        }

        return (int) $result->total_rows !== (int) $result->distinct_rows;
    }

    protected function getColumnDefinition($table, $column, $fallbackType, $fallbackLength = null)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [
                'type' => $fallbackType,
                'length' => $fallbackLength,
                'unsigned' => false,
                'nullable' => false,
                'collation' => null,
            ];
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, CHARACTER_MAXIMUM_LENGTH AS max_length, IS_NULLABLE AS is_nullable, COLLATION_NAME AS collation_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $table, $column]
        );

        if (! $result) {
            return [
                'type' => $fallbackType,
                'length' => $fallbackLength,
                'unsigned' => false,
                'nullable' => false,
                'collation' => null,
            ];
        }

        return [
            'type' => strtolower($result->data_type),
            'length' => $result->max_length ? (int) $result->max_length : $fallbackLength,
            'unsigned' => strpos(strtolower((string) $result->column_type), 'unsigned') !== false,
            'nullable' => strtoupper((string) $result->is_nullable) === 'YES',
            'collation' => $result->collation_name ?: null,
        ];
    }

    protected function addColumnByDefinition(Blueprint $table, $name, array $definition)
    {
        $type = $definition['type'];

        if ($type === 'bigint') {
            $column = ! empty($definition['unsigned'])
                ? $table->unsignedBigInteger($name)
                : $table->bigInteger($name);
        } elseif ($type === 'int' || $type === 'integer') {
            $column = ! empty($definition['unsigned'])
                ? $table->unsignedInteger($name)
                : $table->integer($name);
        } else {
            $column = $table->string($name, $definition['length'] ?: 191);

            if (! empty($definition['collation'])) {
                $column->collation($definition['collation']);
            }
        }

        if (! empty($definition['nullable'])) {
            $column->nullable();
        }

        return $column;
    }
}
