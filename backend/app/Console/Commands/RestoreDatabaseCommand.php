<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'backup:restore {file}';
    protected $description = 'Restore database from a backup file';

    public function handle(): int
    {
        $file = $this->argument('file');
        $disk = Storage::disk('local');

        $path = str_starts_with($file, 'backups/') ? $file : 'backups/' . $file;

        if (!$disk->exists($path)) {
            $this->error("Backup file not found: {$path}");
            return self::FAILURE;
        }

        $fullPath = storage_path('app/' . $path);

        if (!$this->confirm("WARNING: This will overwrite the current database. Continue?")) {
            $this->info('Restore cancelled.');
            return self::SUCCESS;
        }

        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database', '');
        $dbUser = config('database.connections.mysql.username', '');
        $dbPass = config('database.connections.mysql.password', '');

        $command = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s --password=%s %s 2>&1',
            escapeshellarg($fullPath),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Restore failed: ' . implode("\n", $output));
            return self::FAILURE;
        }

        $this->info("Database restored from: {$path}");
        return self::SUCCESS;
    }
}
