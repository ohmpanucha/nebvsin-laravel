<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnableProductsAutoIncrement extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropProductForeignKeys();
        $this->normalizeProductIdsForAutoIncrement();
        DB::statement('ALTER TABLE products MODIFY id INT NOT NULL AUTO_INCREMENT');
        $this->syncAutoIncrementCounter();
        $this->restoreProductForeignKeys();
    }

    public function down()
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropProductForeignKeys();
        DB::statement('ALTER TABLE products MODIFY id INT NOT NULL');
        $this->restoreProductForeignKeys();
    }

    protected function dropProductForeignKeys()
    {
        $constraints = [
            'product_inventory' => 'fk_product_inventory_product',
            'inventory_movements' => 'fk_inventory_movements_product',
            'purchase_order_items' => 'fk_po_items_product',
            'goods_receipt_items' => 'fk_gr_items_product',
        ];

        foreach ($constraints as $table => $constraint) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! $this->foreignKeyExists($table, $constraint)) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $constraint));
        }
    }

    protected function restoreProductForeignKeys()
    {
        $definitions = [
            'product_inventory' => [
                'constraint' => 'fk_product_inventory_product',
                'column' => 'product_id',
                'on_delete' => 'CASCADE',
            ],
            'inventory_movements' => [
                'constraint' => 'fk_inventory_movements_product',
                'column' => 'product_id',
                'on_delete' => 'CASCADE',
            ],
            'purchase_order_items' => [
                'constraint' => 'fk_po_items_product',
                'column' => 'product_id',
                'on_delete' => null,
            ],
            'goods_receipt_items' => [
                'constraint' => 'fk_gr_items_product',
                'column' => 'product_id',
                'on_delete' => null,
            ],
        ];

        foreach ($definitions as $table => $definition) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $definition['column'])) {
                continue;
            }

            if ($this->foreignKeyExists($table, $definition['constraint'])) {
                continue;
            }

            $statement = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES products(id)',
                $table,
                $definition['constraint'],
                $definition['column']
            );

            if ($definition['on_delete']) {
                $statement .= ' ON DELETE '.$definition['on_delete'];
            }

            DB::statement($statement);
        }
    }

    protected function normalizeProductIdsForAutoIncrement()
    {
        $rows = DB::table('products')
            ->select('id')
            ->where('id', '<=', 0)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $nextId = (int) DB::table('products')->where('id', '>', 0)->max('id');

        foreach ($rows as $row) {
            $oldId = (int) $row->id;
            $nextId++;
            $this->remapProductId($oldId, $nextId);
        }
    }

    protected function remapProductId($oldId, $newId)
    {
        $references = [
            'product_inventory' => 'product_id',
            'inventory_movements' => 'product_id',
            'purchase_order_items' => 'product_id',
            'goods_receipt_items' => 'product_id',
        ];

        foreach ($references as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->where($column, $oldId)
                ->update([$column => $newId]);
        }

        DB::table('products')
            ->where('id', $oldId)
            ->update(['id' => $newId]);
    }

    protected function syncAutoIncrementCounter()
    {
        $nextId = ((int) DB::table('products')->max('id')) + 1;

        if ($nextId < 1) {
            $nextId = 1;
        }

        DB::statement('ALTER TABLE products AUTO_INCREMENT = '.$nextId);
    }

    protected function foreignKeyExists($table, $constraint)
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
}
