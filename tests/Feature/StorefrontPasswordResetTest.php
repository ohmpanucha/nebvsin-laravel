<?php

namespace Tests\Feature;

use App\Mail\StorefrontPasswordResetMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontPasswordResetTest extends TestCase
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
            $table->string('account_status')->default('active');
        });

        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('token')->primary();
            $table->integer('user_id');
            $table->timestamp('expires_at');
        });

        DB::table('users')->insert([
            'id' => 1,
            'email' => 'user@nebvsin.local',
            'password' => Hash::make('old-password'),
            'role' => 'user',
            'display_name' => 'Reset User',
            'account_status' => 'active',
        ]);
    }

    public function test_forgot_password_sends_a_link_and_stores_only_a_token_hash(): void
    {
        Mail::fake();
        $resetToken = null;

        $response = $this->post('/forgot-password', [
            'email' => 'user@nebvsin.local',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('auth_status');

        Mail::assertSent(StorefrontPasswordResetMail::class, function ($mail) use (&$resetToken) {
            parse_str((string) parse_url($mail->resetUrl, PHP_URL_QUERY), $query);
            preg_match('#/reset-password/([^?]+)#', $mail->resetUrl, $matches);
            $resetToken = $matches[1] ?? null;

            return ($query['email'] ?? null) === 'user@nebvsin.local';
        });

        $storedToken = (string) DB::table('password_resets')
            ->where('email', 'user@nebvsin.local')
            ->value('token');

        $this->assertNotNull($resetToken);
        $this->assertNotSame($resetToken, $storedToken);
        $this->assertSame(hash('sha256', $resetToken), $storedToken);
    }

    public function test_password_can_be_reset_and_existing_sessions_are_revoked(): void
    {
        $token = 'valid-reset-token';

        DB::table('password_resets')->insert([
            'email' => 'user@nebvsin.local',
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'token' => 'existing-session',
            'user_id' => 1,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->post('/reset-password', [
            'email' => 'user@nebvsin.local',
            'token' => $token,
            'password' => 'new-secure-password',
            'confirm_password' => 'new-secure-password',
        ]);

        $response->assertRedirect('/login?lang=en');
        $this->assertTrue(Hash::check(
            'new-secure-password',
            (string) DB::table('users')->where('id', 1)->value('password')
        ));
        $this->assertSame(0, DB::table('password_resets')->count());
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        DB::table('password_resets')->insert([
            'email' => 'user@nebvsin.local',
            'token' => hash('sha256', 'expired-token'),
            'created_at' => now()->subMinutes(61),
        ]);

        $oldHash = (string) DB::table('users')->where('id', 1)->value('password');

        $response = $this->from('/reset-password/expired-token')->post('/reset-password', [
            'email' => 'user@nebvsin.local',
            'token' => 'expired-token',
            'password' => 'new-secure-password',
            'confirm_password' => 'new-secure-password',
        ]);

        $response->assertRedirect('/reset-password/expired-token');
        $response->assertSessionHas('auth_status');
        $this->assertSame($oldHash, DB::table('users')->where('id', 1)->value('password'));
    }

    public function test_unknown_email_gets_the_same_generic_response(): void
    {
        Mail::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'missing@nebvsin.local',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('auth_status');
        Mail::assertNothingSent();
    }
}
