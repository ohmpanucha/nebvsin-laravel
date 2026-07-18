<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomersAdminService
{
    public function summary(): array
    {
        return [
            ['label' => 'Users', 'value' => $this->countTable('users')],
            ['label' => 'Active', 'value' => $this->countWhere('users', 'account_status', 'active')],
            ['label' => 'Suspended', 'value' => $this->countWhere('users', 'account_status', 'suspended')],
        ];
    }

    public function listCustomers(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $query = DB::table('users as u')
            ->leftJoin('user_addresses as ua', 'ua.user_id', '=', 'u.id')
            ->leftJoin('orders as o', 'o.user_id', '=', 'u.id')
            ->select(
                'u.id',
                'u.email',
                'u.display_name',
                'u.role',
                'u.account_status',
                'u.suspended_reason',
                'u.account_status_updated_at',
                'u.created_at',
                DB::raw('COALESCE(ua.phone, u.phone) AS phone'),
                DB::raw('COUNT(o.id) AS total_orders'),
                DB::raw("COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS total_spend")
            )
            ->groupBy('u.id', 'u.email', 'u.display_name', 'u.role', 'u.account_status', 'u.suspended_reason', 'u.account_status_updated_at', 'u.created_at', 'ua.phone', 'u.phone')
            ->orderByDesc('u.created_at')
            ->limit(25);

        return $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'email' => $row->email,
                'display_name' => $row->display_name,
                'role' => $row->role,
                'account_status' => $row->account_status,
                'suspended_reason' => $row->suspended_reason,
                'account_status_updated_at' => $row->account_status_updated_at,
                'created_at' => $row->created_at,
                'phone' => $row->phone,
                'total_orders' => (int) $row->total_orders,
                'total_spend' => (int) $row->total_spend,
            ];
        })->all();
    }

    public function updateStatus($userId, string $nextStatus, ?string $suspendedReason, $actorId): void
    {
        $allowedStatuses = ['active', 'suspended'];
        if (! in_array($nextStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid account status.');
        }

        if ((string) $actorId === (string) $userId && $nextStatus === 'suspended') {
            throw new \InvalidArgumentException('You cannot suspend your own account.');
        }

        $payload = [
            'account_status' => $nextStatus,
        ];

        if (Schema::hasColumn('users', 'suspended_reason')) {
            $payload['suspended_reason'] = $nextStatus === 'suspended'
                ? $this->trimmed($suspendedReason, 512)
                : null;
        }

        if (Schema::hasColumn('users', 'account_status_updated_at')) {
            $payload['account_status_updated_at'] = now();
        }

        $updated = DB::table('users')
            ->where('id', $userId)
            ->update($payload);

        if (! $updated) {
            throw new \RuntimeException('Customer not found.');
        }
    }

    protected function trimmed($value, int $limit): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    protected function countTable($table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    protected function countWhere($table, $column, $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->where($column, $value)->count();
    }
}
