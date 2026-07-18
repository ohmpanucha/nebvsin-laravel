<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

class LegacyAuthService
{
    public function currentUserFromToken(?string $token): ?array
    {
        if (! $this->hasLegacyAuthSchema()) {
            return null;
        }

        $value = trim((string) $token);

        if ($value === '') {
            return null;
        }

        $session = DB::table('sessions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select('s.token', 's.expires_at', 'u.id', 'u.email', 'u.role', 'u.display_name', 'u.account_status')
            ->where('s.token', $value)
            ->first();

        if (! $session) {
            return null;
        }

        $expiresAt = CarbonImmutable::parse($session->expires_at);
        if ($expiresAt->isPast()) {
            DB::table('sessions')->where('token', $value)->delete();

            return null;
        }

        if (($session->account_status ?? 'active') === 'suspended') {
            DB::table('sessions')->where('token', $value)->delete();

            return null;
        }

        return [
            'token' => $session->token,
            'user' => [
                'id' => $session->id,
                'email' => $session->email,
                'role' => $session->role,
                'display_name' => $session->display_name,
            ],
        ];
    }

    public function attemptLogin(string $email, string $password, int $ttlHours): array
    {
        if (! $this->hasLegacyAuthSchema()) {
            return ['error' => 'schema_mismatch'];
        }

        $user = DB::table('users')
            ->select('id', 'email', 'password', 'role', 'display_name', 'account_status')
            ->where('email', strtolower(trim($email)))
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            return ['error' => 'invalid_credentials'];
        }

        if (Hash::needsRehash((string) $user->password)) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['password' => Hash::make($password)]);
        }

        if (($user->account_status ?? 'active') === 'suspended') {
            return ['error' => 'suspended'];
        }

        return $this->issueSessionForUser((array) $user, $ttlHours);
    }

    public function register(array $input, int $ttlHours): array
    {
        if (! $this->hasLegacyAuthSchema()) {
            return ['error' => 'schema_mismatch'];
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $displayName = trim((string) ($input['display_name'] ?? $input['displayName'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));

        if ($email === '' || $password === '' || $displayName === '') {
            return ['error' => 'required'];
        }

        $exists = DB::table('users')->where('email', $email)->exists();
        if ($exists) {
            return ['error' => 'duplicate_email'];
        }

        $userId = $this->nextUserId();
        $payload = [
            'id' => $userId,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'user',
            'display_name' => $displayName,
            'phone' => $phone !== '' ? $phone : null,
        ];

        if (Schema::hasColumn('users', 'name')) {
            $payload['name'] = $displayName;
        }

        DB::table('users')->insert($payload);

        $user = [
            'id' => $userId,
            'email' => $email,
            'role' => 'user',
            'display_name' => $displayName,
            'account_status' => 'active',
        ];

        return $this->issueSessionForUser($user, $ttlHours);
    }

    public function logout(?string $token): void
    {
        $value = trim((string) $token);
        if ($value === '') {
            return;
        }

        DB::table('sessions')->where('token', $value)->delete();
    }

    public function safeCurrentUserFromToken(?string $token): ?array
    {
        try {
            if (! $this->hasLegacyAuthSchema()) {
                return null;
            }
            return $this->currentUserFromToken($token);
        } catch (Throwable $exception) {
            return null;
        }
    }

    protected function hasLegacyAuthSchema(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumns('users', ['email', 'password', 'role', 'display_name'])
            && Schema::hasTable('sessions')
            && Schema::hasColumns('sessions', ['token', 'user_id', 'expires_at']);
    }

    protected function issueSessionForUser(array $user, int $ttlHours): array
    {
        $token = bin2hex(random_bytes(24));
        $expiresAt = CarbonImmutable::now()->addHours($ttlHours);

        DB::table('sessions')->insert([
            'token' => $token,
            'user_id' => $user['id'],
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'display_name' => $user['display_name'],
            ],
        ];
    }

    protected function nextUserId()
    {
        $type = $this->getUserIdType();

        if ($type === 'bigint' || $type === 'int' || $type === 'integer') {
            $maxId = DB::table('users')->max('id');

            if ($maxId === null) {
                return 1;
            }

            return (int) $maxId + 1;
        }

        return 'usr_'.str_replace('-', '', (string) Str::uuid());
    }

    protected function getUserIdType()
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'id')) {
            return 'string';
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA table_info('users')") as $column) {
                if (($column->name ?? null) === 'id') {
                    return strtolower((string) ($column->type ?? 'string'));
                }
            }

            return 'string';
        }

        $column = $this->getColumnMetadata('users', 'id');

        if (! $column || empty($column->data_type)) {
            return 'string';
        }

        return strtolower((string) $column->data_type);
    }

    protected function getColumnMetadata($table, $column): ?stdClass
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT DATA_TYPE AS data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $table, $column]
        );

        if (! $result) {
            return null;
        }

        return $result;
    }
}
