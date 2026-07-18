<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SecureUserPasswords extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password', 255)->nullable()->after('email');
            });
        }

        $hasPlainPassword = Schema::hasColumn('users', 'password_plain');

        DB::table('users')
            ->select(array_values(array_filter([
                'id',
                'password',
                $hasPlainPassword ? 'password_plain' : null,
            ])))
            ->orderBy('id')
            ->chunk(100, function ($users) use ($hasPlainPassword) {
                foreach ($users as $user) {
                    $currentHash = trim((string) ($user->password ?? ''));

                    if (
                        $currentHash !== ''
                        && (Hash::info($currentHash)['algoName'] ?? 'unknown') !== 'unknown'
                    ) {
                        continue;
                    }

                    $plainPassword = $hasPlainPassword
                        ? (string) ($user->password_plain ?? '')
                        : '';

                    // Accounts without a recoverable legacy password are locked with
                    // an unknown random password instead of retaining insecure data.
                    $passwordToHash = $plainPassword !== ''
                        ? $plainPassword
                        : Str::random(64);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['password' => Hash::make($passwordToHash)]);
                }
            });

        if ($hasPlainPassword) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_plain');
            });
        }
    }

    public function down()
    {
        // Plaintext passwords cannot and must not be reconstructed from hashes.
    }
}
