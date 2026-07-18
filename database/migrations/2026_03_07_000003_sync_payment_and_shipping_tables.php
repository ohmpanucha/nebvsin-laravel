<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncPaymentAndShippingTables extends Migration
{
    public function up()
    {
        $this->ensureReferenceKey('orders', 'id', 'uniq_orders_id');
        $this->syncOrderPaymentColumns();
        $this->syncPaymentEventsTable();
        $this->syncShippingColumns();
        $this->syncShippingEventsTable();
        $this->syncIndexes();
    }

    public function down()
    {
        // Keep legacy payment and shipping tables intact on rollback.
    }

    protected function syncOrderPaymentColumns()
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_slip_data')) {
                $table->longText('payment_slip_data')->nullable()->after('updated_at');
            }

            if (! Schema::hasColumn('orders', 'payment_slip_mime')) {
                $table->string('payment_slip_mime', 64)->nullable()->after('payment_slip_data');
            }

            if (! Schema::hasColumn('orders', 'payment_slip_filename')) {
                $table->string('payment_slip_filename', 255)->nullable()->after('payment_slip_mime');
            }

            if (! Schema::hasColumn('orders', 'payment_slip_uploaded_at')) {
                $table->timestamp('payment_slip_uploaded_at')->nullable()->after('payment_slip_filename');
            }
        });
    }

    protected function syncPaymentEventsTable()
    {
        if (! Schema::hasTable('order_payment_events')) {
            Schema::create('order_payment_events', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('order_id', 191);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('actor_id', 191);
                $table->string('actor_role', 32);
                $table->string('note', 512)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('order_id', 'fk_payment_events_order')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');
            });
        }
    }

    protected function syncShippingColumns()
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shipping_status')) {
                $table->string('shipping_status', 32)->default('pending_fulfillment')->after('status');
            }

            if (! Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number', 191)->nullable()->after('shipping_status');
            }

            if (! Schema::hasColumn('orders', 'shipping_carrier')) {
                $table->string('shipping_carrier', 191)->nullable()->after('tracking_number');
            }

            if (! Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('shipping_carrier');
            }

            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }
        });
    }

    protected function syncShippingEventsTable()
    {
        if (! Schema::hasTable('order_shipping_events')) {
            Schema::create('order_shipping_events', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('order_id', 191);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('tracking_number', 191)->nullable();
                $table->string('shipping_carrier', 191)->nullable();
                $table->string('actor_id', 191);
                $table->string('actor_role', 32);
                $table->string('note', 512)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('order_id', 'fk_shipping_events_order')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');
            });
        }
    }

    protected function syncIndexes()
    {
        if (Schema::hasTable('orders')) {
            $this->createIndexIfMissing('orders', 'idx_orders_shipping_status', 'CREATE INDEX idx_orders_shipping_status ON orders(shipping_status)');
        }

        if (Schema::hasTable('order_payment_events')) {
            $this->createIndexIfMissing(
                'order_payment_events',
                'idx_payment_events_order_created',
                'CREATE INDEX idx_payment_events_order_created ON order_payment_events(order_id, created_at)'
            );
        }

        if (Schema::hasTable('order_shipping_events')) {
            $this->createIndexIfMissing(
                'order_shipping_events',
                'idx_shipping_events_order_created',
                'CREATE INDEX idx_shipping_events_order_created ON order_shipping_events(order_id, created_at)'
            );
        }
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
}
