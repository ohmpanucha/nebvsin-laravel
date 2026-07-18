<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncLegacyAuthTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->string('id', 191)->primary();
                $table->string('email', 320)->unique();
                $table->string('password_plain', 255);
                $table->string('role', 32)->default('user');
                $table->string('display_name', 255);
                $table->string('phone', 32)->nullable();
                $table->string('account_status', 32)->default('active');
                $table->string('suspended_reason', 512)->nullable();
                $table->timestamp('account_status_updated_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'password_plain')) {
                    $table->string('password_plain', 255)->nullable()->after('email');
                }

                if (! Schema::hasColumn('users', 'role')) {
                    $table->string('role', 32)->default('user')->after('password_plain');
                }

                if (! Schema::hasColumn('users', 'display_name')) {
                    $table->string('display_name', 255)->nullable()->after('role');
                }

                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone', 32)->nullable()->after('display_name');
                }

                if (! Schema::hasColumn('users', 'account_status')) {
                    $table->string('account_status', 32)->default('active')->after('phone');
                }

                if (! Schema::hasColumn('users', 'suspended_reason')) {
                    $table->string('suspended_reason', 512)->nullable()->after('account_status');
                }

                if (! Schema::hasColumn('users', 'account_status_updated_at')) {
                    $table->timestamp('account_status_updated_at')->nullable()->after('suspended_reason');
                }
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('token', 191)->primary();
                $table->string('user_id', 191);
                $table->timestamp('expires_at');
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->index('user_id');
            });
        }

        if (Schema::hasTable('users') && ! $this->indexExists('users', 'idx_users_account_status')) {
            DB::statement('CREATE INDEX idx_users_account_status ON users(account_status)');
        }
    }

    public function down()
    {
        // Keep legacy-compatible auth columns/tables intact on rollback.
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
}
