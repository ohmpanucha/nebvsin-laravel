<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncCommerceCoreTables extends Migration
{
    public function up()
    {
        $this->syncOrdersTable();
        $this->ensureReferenceKey('users', 'id', 'uniq_users_id');
        $this->ensureReferenceKey('orders', 'id', 'uniq_orders_id');
        $this->syncOrderItemsTable();
        $this->syncOrderAddressesTable();
        $this->syncUserAddressesTable();
        $this->syncIndexes();
    }

    public function down()
    {
        // Keep legacy commerce tables intact on rollback.
    }

    protected function syncOrdersTable()
    {
        $userIdDefinition = $this->getColumnDefinition('users', 'id', 'string', 191);

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) use ($userIdDefinition) {
                $table->string('id', 191)->primary();
                $this->addColumnByDefinition($table, 'user_id', $userIdDefinition);
                $table->string('user_email', 320);
                $table->integer('total_amount')->default(0);
                $table->string('currency', 8)->default('THB');
                $table->string('status', 32)->default('pending');
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('user_id', 'fk_orders_user')
                    ->references('id')
                    ->on('users');
            });

            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'user_email')) {
                $table->string('user_email', 320)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('orders', 'total_amount')) {
                $table->integer('total_amount')->default(0)->after('user_email');
            }

            if (! Schema::hasColumn('orders', 'currency')) {
                $table->string('currency', 8)->default('THB')->after('total_amount');
            }

            if (! Schema::hasColumn('orders', 'status')) {
                $table->string('status', 32)->default('pending')->after('currency');
            }

            if (! Schema::hasColumn('orders', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    protected function syncOrderItemsTable()
    {
        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('order_id', 191);
                $table->string('product_id', 191);
                $table->string('name', 255);
                $table->string('image', 512)->nullable();
                $table->string('size', 16)->nullable();
                $table->integer('quantity');
                $table->integer('unit_amount');
                $table->integer('line_total');

                $table->foreign('order_id', 'fk_order_items_order')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');
            });
        }
    }

    protected function syncOrderAddressesTable()
    {
        $userIdDefinition = $this->getColumnDefinition('users', 'id', 'string', 191);

        if (! Schema::hasTable('order_addresses')) {
            Schema::create('order_addresses', function (Blueprint $table) use ($userIdDefinition) {
                $table->string('id', 191)->primary();
                $table->string('order_id', 191)->unique();
                $this->addColumnByDefinition($table, 'user_id', $userIdDefinition);
                $table->string('full_name', 255);
                $table->string('phone', 32);
                $table->string('address_line1', 512);
                $table->string('address_line2', 512)->nullable();
                $table->string('district', 191);
                $table->string('province', 191);
                $table->string('postal_code', 32);
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('order_id', 'fk_order_addresses_order')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_order_addresses_user')
                    ->references('id')
                    ->on('users');
            });
        }
    }

    protected function syncUserAddressesTable()
    {
        $userIdDefinition = $this->getColumnDefinition('users', 'id', 'string', 191);

        if (! Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table) use ($userIdDefinition) {
                $column = $this->addColumnByDefinition($table, 'user_id', $userIdDefinition);
                $column->primary();
                $table->string('full_name', 255);
                $table->string('phone', 32);
                $table->string('address_line1', 512);
                $table->string('address_line2', 512)->nullable();
                $table->string('district', 191);
                $table->string('province', 191);
                $table->string('postal_code', 32);
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('user_id', 'fk_user_addresses_user')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    }

    protected function syncIndexes()
    {
        if (Schema::hasTable('orders')) {
            $this->createIndexIfMissing('orders', 'idx_orders_user_id', 'CREATE INDEX idx_orders_user_id ON orders(user_id)');
            $this->createIndexIfMissing('orders', 'idx_orders_status', 'CREATE INDEX idx_orders_status ON orders(status)');
            $this->createIndexIfMissing('orders', 'idx_orders_created_at', 'CREATE INDEX idx_orders_created_at ON orders(created_at)');
        }

        if (Schema::hasTable('sessions')) {
            $this->createIndexIfMissing('sessions', 'idx_sessions_expires_at', 'CREATE INDEX idx_sessions_expires_at ON sessions(expires_at)');
        }

        if (Schema::hasTable('order_addresses')) {
            $this->createIndexIfMissing('order_addresses', 'idx_order_addresses_user_id', 'CREATE INDEX idx_order_addresses_user_id ON order_addresses(user_id)');
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
