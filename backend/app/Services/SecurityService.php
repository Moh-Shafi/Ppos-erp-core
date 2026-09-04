<?php

namespace App\Services;

use App\Models\AccountLockout;
use Illuminate\Support\Facades\Request;

class SecurityService
{
    public function recordFailedAttempt(string $username): AccountLockout
    {
        $lockout = AccountLockout::firstOrCreate(
            ['username' => $username],
            ['failed_attempts' => 0, 'ip_address' => Request::ip()],
        );

        $lockout->increment('failed_attempts');
        $lockout->last_attempt_at = now();
        $lockout->ip_address = Request::ip();

        $thresholds = config('security.lockout.thresholds', [5, 10, 15]);
        $durations = config('security.lockout.durations', [900, 3600, 86400]);

        foreach ($thresholds as $i => $threshold) {
            if ($lockout->failed_attempts >= $threshold) {
                $lockout->locked_until = now()->addSeconds($durations[$i]);
            }
        }

        $lockout->save();

        return $lockout;
    }

    public function resetFailedAttempts(string $username): void
    {
        AccountLockout::where('username', $username)->delete();
    }

    public function unlockUser(int $userId): bool
    {
        $lockout = AccountLockout::where('user_id', $userId)->first();

        if ($lockout) {
            $lockout->delete();
            return true;
        }

        return false;
    }

    public function isLocked(string $username): bool
    {
        $lockout = AccountLockout::where('username', $username)->first();

        return $lockout && $lockout->isLocked();
    }
}
