<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function notify(int $userId, string $type, string $title, string $message, ?int $cooperativeId = null, ?array $data = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'cooperative_id' => $cooperativeId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function notifyMultiple(array $userIds, string $type, string $title, string $message, ?int $cooperativeId = null, ?array $data = null): void
    {
        foreach ($userIds as $userId) {
            $this->notify($userId, $type, $title, $message, $cooperativeId, $data);
        }
    }

    public function notifyAdminDinas(string $type, string $title, string $message, ?int $cooperativeId = null, ?array $data = null): void
    {
        $adminDinasUsers = User::role('admin_dinas')->pluck('id')->toArray();
        $this->notifyMultiple($adminDinasUsers, $type, $title, $message, $cooperativeId, $data);
    }

    public function notifyCooperativeAdmins(int $cooperativeId, string $type, string $title, string $message, ?array $data = null): void
    {
        $cooperativeAdmins = User::role('admin_koperasi')
            ->where('cooperative_id', $cooperativeId)
            ->pluck('id')
            ->toArray();

        $this->notifyMultiple($cooperativeAdmins, $type, $title, $message, $cooperativeId, $data);
    }

    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getRecentNotifications(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => true]) > 0;
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function deleteNotification(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function cleanupOldNotifications(int $days = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->where('is_read', true)
            ->delete();
    }

    // Predefined notification types
    public function reportSubmitted(int $cooperativeId, string $reportType, int $reportingYear): void
    {
        $cooperative = \App\Models\Cooperative::find($cooperativeId);
        $reportTypeLabel = $this->getReportTypeLabel($reportType);

        $this->notifyAdminDinas(
            'report_submitted',
            'Laporan Baru Diajukan',
            "Laporan {$reportTypeLabel} tahun {$reportingYear} dari {$cooperative->name} telah diajukan dan menunggu persetujuan.",
            $cooperativeId,
            [
                'report_type' => $reportType,
                'reporting_year' => $reportingYear,
                'cooperative_name' => $cooperative->name,
            ]
        );
    }

    public function reportApproved(int $cooperativeId, string $reportType, int $reportingYear): void
    {
        $reportTypeLabel = $this->getReportTypeLabel($reportType);

        $this->notifyCooperativeAdmins(
            $cooperativeId,
            'report_approved',
            'Laporan Disetujui',
            "Laporan {$reportTypeLabel} tahun {$reportingYear} telah disetujui oleh Admin Dinas.",
            [
                'report_type' => $reportType,
                'reporting_year' => $reportingYear,
            ]
        );
    }

    public function reportRejected(int $cooperativeId, string $reportType, int $reportingYear, string $reason): void
    {
        $reportTypeLabel = $this->getReportTypeLabel($reportType);

        $this->notifyCooperativeAdmins(
            $cooperativeId,
            'report_rejected',
            'Laporan Ditolak',
            "Laporan {$reportTypeLabel} tahun {$reportingYear} ditolak. Alasan: {$reason}",
            [
                'report_type' => $reportType,
                'reporting_year' => $reportingYear,
                'rejection_reason' => $reason,
            ]
        );
    }

    private function getReportTypeLabel(string $reportType): string
    {
        return match ($reportType) {
            'balance_sheet' => 'Laporan Posisi Keuangan',
            'income_statement' => 'Laporan Perhitungan Hasil Usaha',
            'equity_changes' => 'Laporan Perubahan Ekuitas',
            'cash_flow' => 'Laporan Arus Kas',
            'member_savings' => 'Daftar Simpanan Anggota',
            'member_receivables' => 'Daftar Piutang Simpan Pinjam Anggota',
            'npl_receivables' => 'Daftar Piutang Tidak Lancar',
            'shu_distribution' => 'Daftar Rencana Pembagian SHU',
            'budget_plan' => 'Rencana Anggaran Pendapatan & Belanja',
            'notes_to_financial' => 'Catatan Atas Laporan Keuangan',
            default => $reportType,
        };
    }
}
