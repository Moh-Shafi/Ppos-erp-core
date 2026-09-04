<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit:purge';
    protected $description = 'Delete audit logs older than retention period';

    public function handle(): int
    {
        $retentionDays = config('audit.retention_days', 90);
        $cutoff = now()->subDays($retentionDays);

        $count = AuditLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No audit logs to purge.');
            return self::SUCCESS;
        }

        $this->info("Purging {$count} audit logs older than {$retentionDays} days...");

        AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Purged {$count} audit logs.");
        return self::SUCCESS;
    }
}
