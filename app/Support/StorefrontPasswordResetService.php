<?php

namespace App\Support;

use App\Mail\StorefrontPasswordResetMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StorefrontPasswordResetService
{
    public function sendResetLink(string $email, string $locale): void
    {
        if (! $this->hasSchema()) {
            return;
        }

        $user = DB::table('users')
            ->select('email', 'display_name')
            ->where('email', strtolower(trim($email)))
            ->first();

        if (! $user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $resetUrl = route('storefront.password.reset', [
            'token' => $token,
            'email' => $user->email,
            'lang' => $locale,
        ]);

        DB::table('password_resets')->where('email', $user->email)->delete();
        DB::table('password_resets')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new StorefrontPasswordResetMail(
                (string) ($user->display_name ?? ''),
                $resetUrl,
                $locale,
                $this->expireMinutes()
            ));
        } catch (Throwable $exception) {
            Log::error('Unable to send password reset email.', [
                'email' => $user->email,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function reset(string $email, string $token, string $password): bool
    {
        if (! $this->hasSchema() || trim($email) === '' || trim($token) === '') {
            return false;
        }

        $email = strtolower(trim($email));
        $reset = DB::table('password_resets')->where('email', $email)->first();

        if (! $reset || ! hash_equals((string) $reset->token, hash('sha256', $token))) {
            return false;
        }

        $createdAt = $reset->created_at ? CarbonImmutable::parse($reset->created_at) : null;
        if (! $createdAt || $createdAt->addMinutes($this->expireMinutes())->isPast()) {
            DB::table('password_resets')->where('email', $email)->delete();

            return false;
        }

        $user = DB::table('users')->select('id')->where('email', $email)->first();
        if (! $user) {
            DB::table('password_resets')->where('email', $email)->delete();

            return false;
        }

        DB::transaction(function () use ($email, $password, $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['password' => Hash::make($password)]);

            DB::table('password_resets')->where('email', $email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return true;
    }

    protected function hasSchema(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumns('users', ['id', 'email', 'password'])
            && Schema::hasTable('password_resets')
            && Schema::hasColumns('password_resets', ['email', 'token', 'created_at'])
            && Schema::hasTable('sessions')
            && Schema::hasColumns('sessions', ['user_id']);
    }

    protected function expireMinutes(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }
}
