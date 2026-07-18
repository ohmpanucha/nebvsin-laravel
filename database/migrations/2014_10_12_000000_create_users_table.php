<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // The project uses the legacy auth schema from version-react.
        // Keep the default Laravel users migration as a no-op to avoid
        // creating a conflicting users table before the legacy sync migration runs.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No-op on rollback. Legacy auth tables are managed separately.
    }
}
