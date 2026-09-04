<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database {--path=}';
    protected $description = 'Create a database backup (mysqldump)';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $backupDir = 'backups';
        if (!$disk->exists($backupDir)) {
            $disk->makeDirectory($backupDir);
        }

        $filename = 'backup-' . now()->format('Y-m-d-His') . '.sql.gz';
        $path = $this->option('path') ?: $backupDir . '/' . $filename;
        $fullPath = storage_path('app/' . $path);

        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database', '');
        $dbUser = config('database.connections.mysql.username', '');
        $dbPass = config('database.connections.mysql.password', '');

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s 2>/dev/null | gzip > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($fullPath),
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($fullPath) || filesize($fullPath) === 0) {
            $this->error('Backup failed.');
            \Log::error('Database backup failed', ['return_code' => $returnCode]);
            return self::FAILURE;
        }

        $this->info("Backup created: {$path}");

        $this->cleanupOldBackups($disk, $backupDir);

        return self::SUCCESS;
    }

    private function cleanupOldBackups($disk, string $dir): void
    {
        $dailyKeep = 7;
        $weeklyKeep = 4;
        $monthlyKeep = 3;

        $files = $disk->files($dir);
        $backups = [];

        foreach ($files as $file) {
            if (!str_ends_with($file, '.sql.gz')) {
                continue;
            }
            $backups[] = [
                'path' => $file,
                'timestamp' => $disk->lastModified($file),
            ];
        }

        usort($backups, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        if (count($backups) > $dailyKeep + $weeklyKeep + $monthlyKeep) {
            $toDelete = array_slice($backups, $dailyKeep + $weeklyKeep + $monthlyKeep);
            foreach ($toDelete as $backup) {
                $disk->delete($backup['path']);
                $this->info("Deleted old backup: {$backup['path']}");
            }
        }
    }
}
