<?php

namespace Tests\Feature;

use App\Support\LegacyAuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyAuthPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        Schema::create('users', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('user');
            $table->string('display_name');
            $table->string('phone')->nullable();
            $table->string('account_status')->default('active');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('token')->primary();
            $table->integer('user_id');
            $table->timestamp('expires_at');
        });
    }

    public function test_register_stores_only_a_password_hash(): void
    {
        $result = app(LegacyAuthService::class)->register([
            'email' => 'new@nebvsin.local',
            'password' => 'secure-password',
            'display_name' => 'New User',
        ], 24);

        $storedPassword = (string) DB::table('users')
            ->where('email', 'new@nebvsin.local')
            ->value('password');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertNotSame('secure-password', $storedPassword);
        $this->assertTrue(Hash::check('secure-password', $storedPassword));
        $this->assertFalse(Schema::hasColumn('users', 'password_plain'));
    }

    public function test_login_checks_the_password_hash(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'email' => 'user@nebvsin.local',
            'password' => Hash::make('correct-password'),
            'role' => 'user',
            'display_name' => 'Hash User',
            'account_status' => 'active',
        ]);

        $auth = app(LegacyAuthService::class);
        $invalidResult = $auth->attemptLogin('user@nebvsin.local', 'wrong-password', 24);
        $validResult = $auth->attemptLogin('user@nebvsin.local', 'correct-password', 24);

        $this->assertSame('invalid_credentials', $invalidResult['error']);
        $this->assertArrayNotHasKey('error', $validResult);
        $this->assertSame('user@nebvsin.local', $validResult['user']['email']);
    }
}
