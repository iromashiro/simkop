<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;

class AuditLogService
{
    public function log(string $tableName, ?int $recordId, string $action, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        if (!auth()->check()) {
            throw new \Exception('User must be authenticated to create audit log');
        }

        $cooperativeId = null;
        if (auth()->user()->cooperative_id) {
            $cooperativeId = auth()->user()->cooperative_id;
        }

        return AuditLog::create([
            'user_id' => auth()->id(),
            'cooperative_id' => $cooperativeId,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function getRecentLogs(int $limit = 50): Collection
    {
        $query = AuditLog::with(['user', 'cooperative'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        // Filter by cooperative for non-admin users
        if (auth()->check() && !auth()->user()->isAdminDinas()) {
            $query->where('cooperative_id', auth()->user()->cooperative_id);
        }

        return $query->get();
    }

    public function getLogsByUser(int $userId, int $limit = 50): Collection
    {
        return AuditLog::with(['user', 'cooperative'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLogsByCooperative(int $cooperativeId, int $limit = 50): Collection
    {
        return AuditLog::with(['user', 'cooperative'])
            ->where('cooperative_id', $cooperativeId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLogsByTable(string $tableName, ?int $recordId = null, int $limit = 50): Collection
    {
        $query = AuditLog::with(['user', 'cooperative'])
            ->where('table_name', $tableName)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($recordId) {
            $query->where('record_id', $recordId);
        }

        // Filter by cooperative for non-admin users
        if (auth()->check() && !auth()->user()->isAdminDinas()) {
            $query->where('cooperative_id', auth()->user()->cooperative_id);
        }

        return $query->get();
    }

    public function cleanupOldLogs(int $days = 365): int
    {
        return AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }

    public function getActivitySummary(int $days = 30): array
    {
        $query = AuditLog::where('created_at', '>=', now()->subDays($days));

        // Filter by cooperative for non-admin users
        if (auth()->check() && !auth()->user()->isAdminDinas()) {
            $query->where('cooperative_id', auth()->user()->cooperative_id);
        }

        $logs = $query->get();

        return [
            'total_activities' => $logs->count(),
            'by_action' => $logs->groupBy('action')->map->count(),
            'by_table' => $logs->groupBy('table_name')->map->count(),
            'by_user' => $logs->groupBy('user_id')->map->count(),
            'by_date' => $logs->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            })->map->count(),
        ];
    }
}
