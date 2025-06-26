<?php
// app/Domain/Auth/Services/SessionService.php - ENHANCED
namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\UserSession;
use App\Domain\Auth\Models\LoginAttempt;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SessionService
{
    public function createSession(int $userId, int $cooperativeId): UserSession
    {
        $session = UserSession::create([
            'cooperative_id' => $cooperativeId,
            'user_id' => $userId,
            'session_id' => session()->getId(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'device_type' => $this->getDeviceType(Request::userAgent()),
            'browser' => $this->getBrowser(Request::userAgent()),
            'platform' => $this->getPlatform(Request::userAgent()),
            'location' => $this->getLocation(Request::ip()),
            'is_active' => true,
            'last_activity' => now(),
            'login_at' => now(),
        ]);

        Log::info('User session created', [
            'user_id' => $userId,
            'session_id' => $session->session_id,
            'ip_address' => $session->ip_address,
            'cooperative_id' => $cooperativeId,
        ]);

        return $session;
    }

    public function updateSessionActivity(string $sessionId): void
    {
        UserSession::where('session_id', $sessionId)
            ->where('is_active', true)
            ->update(['last_activity' => now()]);
    }

    public function endSession(string $sessionId): void
    {
        $session = UserSession::where('session_id', $sessionId)->first();

        if ($session) {
            $session->markAsLoggedOut();

            Log::info('User session ended', [
                'user_id' => $session->user_id,
                'session_id' => $sessionId,
                'cooperative_id' => $session->cooperative_id,
            ]);
        }
    }

    public function endAllUserSessions(int $userId): void
    {
        UserSession::where('user_id', $userId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'logout_at' => now(),
            ]);

        Log::info('All user sessions ended', ['user_id' => $userId]);
    }

    /**
     * ✅ ENHANCED: Improved session cleanup with detailed logging
     */
    public function cleanupExpiredSessions(int $timeoutMinutes = 120): int
    {
        $cutoffTime = now()->subMinutes($timeoutMinutes);

        $expiredSessions = UserSession::where('is_active', true)
            ->where('last_activity', '<', $cutoffTime)
            ->get();

        $cleanedCount = 0;
        $cooperativeStats = [];

        foreach ($expiredSessions as $session) {
            $session->markAsLoggedOut();
            $cleanedCount++;

            // Track statistics by cooperative
            $cooperativeId = $session->cooperative_id;
            if (!isset($cooperativeStats[$cooperativeId])) {
                $cooperativeStats[$cooperativeId] = 0;
            }
            $cooperativeStats[$cooperativeId]++;
        }

        if ($cleanedCount > 0) {
            Log::info('Expired sessions cleaned up', [
                'total_cleaned' => $cleanedCount,
                'timeout_minutes' => $timeoutMinutes,
                'cutoff_time' => $cutoffTime->toDateTimeString(),
                'cooperative_breakdown' => $cooperativeStats,
            ]);
        }

        return $cleanedCount;
    }

    /**
     * ✅ NEW: Cleanup old login attempts
     */
    public function cleanupOldLoginAttempts(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $deletedCount = LoginAttempt::where('attempted_at', '<', $cutoffDate)->delete();

        if ($deletedCount > 0) {
            Log::info('Old login attempts cleaned up', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);
        }

        return $deletedCount;
    }

    /**
     * ✅ NEW: Get session statistics
     */
    public function getSessionStatistics(int $cooperativeId): array
    {
        return [
            'active_sessions' => UserSession::where('cooperative_id', $cooperativeId)
                ->where('is_active', true)
                ->count(),
            'total_sessions_today' => UserSession::where('cooperative_id', $cooperativeId)
                ->whereDate('login_at', today())
                ->count(),
            'unique_users_today' => UserSession::where('cooperative_id', $cooperativeId)
                ->whereDate('login_at', today())
                ->distinct('user_id')
                ->count(),
            'sessions_by_device' => UserSession::where('cooperative_id', $cooperativeId)
                ->whereDate('login_at', today())
                ->groupBy('device_type')
                ->selectRaw('device_type, count(*) as count')
                ->pluck('count', 'device_type')
                ->toArray(),
            'sessions_by_browser' => UserSession::where('cooperative_id', $cooperativeId)
                ->whereDate('login_at', today())
                ->groupBy('browser')
                ->selectRaw('browser, count(*) as count')
                ->pluck('count', 'browser')
                ->toArray(),
        ];
    }

    /**
     * ✅ NEW: Force cleanup sessions for specific user
     */
    public function forceCleanupUserSessions(int $userId, string $reason = 'Administrative action'): int
    {
        $activeSessions = UserSession::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        foreach ($activeSessions as $session) {
            $session->markAsLoggedOut();
        }

        Log::warning('User sessions force cleaned', [
            'user_id' => $userId,
            'sessions_cleaned' => $activeSessions->count(),
            'reason' => $reason,
        ]);

        return $activeSessions->count();
    }

    /**
     * ✅ NEW: Get suspicious session activity
     */
    public function getSuspiciousActivity(int $cooperativeId, int $hours = 24): array
    {
        $since = now()->subHours($hours);

        // Multiple logins from different IPs
        $multipleIPs = DB::table('user_sessions')
            ->where('cooperative_id', $cooperativeId)
            ->where('login_at', '>=', $since)
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT ip_address) > 3')
            ->selectRaw('user_id, COUNT(DISTINCT ip_address) as ip_count')
            ->get();

        // Rapid login attempts
        $rapidLogins = DB::table('login_attempts')
            ->where('cooperative_id', $cooperativeId)
            ->where('attempted_at', '>=', $since)
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) > 10')
            ->selectRaw('ip_address, COUNT(*) as attempt_count')
            ->get();

        return [
            'multiple_ip_users' => $multipleIPs->toArray(),
            'rapid_login_ips' => $rapidLogins->toArray(),
            'analysis_period' => $hours . ' hours',
            'analysis_since' => $since->toDateTimeString(),
        ];
    }

    public function recordLoginAttempt(
        int $cooperativeId,
        ?int $userId,
        string $email,
        string $status,
        ?string $failureReason = null
    ): LoginAttempt {
        return LoginAttempt::create([
            'cooperative_id' => $cooperativeId,
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'status' => $status,
            'failure_reason' => $failureReason,
            'attempted_at' => now(),
        ]);
    }

    public function getFailedLoginAttempts(string $email, int $minutes = 60): int
    {
        return LoginAttempt::byEmail($email)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    public function isAccountLocked(string $email, int $maxAttempts = 5, int $lockoutMinutes = 60): bool
    {
        $failedAttempts = $this->getFailedLoginAttempts($email, $lockoutMinutes);
        return $failedAttempts >= $maxAttempts;
    }

    public function getUserActiveSessions(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserSession::where('user_id', $userId)
            ->active()
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    private function getDeviceType(string $userAgent): string
    {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/Tablet/', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    private function getBrowser(string $userAgent): string
    {
        if (preg_match('/Chrome/', $userAgent)) return 'Chrome';
        if (preg_match('/Firefox/', $userAgent)) return 'Firefox';
        if (preg_match('/Safari/', $userAgent)) return 'Safari';
        if (preg_match('/Edge/', $userAgent)) return 'Edge';
        return 'Unknown';
    }

    private function getPlatform(string $userAgent): string
    {
        if (preg_match('/Windows/', $userAgent)) return 'Windows';
        if (preg_match('/Mac/', $userAgent)) return 'macOS';
        if (preg_match('/Linux/', $userAgent)) return 'Linux';
        if (preg_match('/Android/', $userAgent)) return 'Android';
        if (preg_match('/iOS/', $userAgent)) return 'iOS';
        return 'Unknown';
    }

    private function getLocation(string $ip): ?array
    {
        // Implementation for IP geolocation
        // This would integrate with a service like MaxMind GeoIP
        return null;
    }
}
