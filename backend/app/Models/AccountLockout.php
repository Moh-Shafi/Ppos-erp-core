<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLockout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'username', 'failed_attempts', 'locked_until',
        'last_attempt_at', 'ip_address',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function retryAfter(): int
    {
        if (!$this->isLocked()) {
            return 0;
        }
        return max(0, now()->diffInSeconds($this->locked_until));
    }
}
