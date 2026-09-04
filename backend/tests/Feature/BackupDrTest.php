<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDrTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_creates_file(): void
    {
        $this->artisan('backup:database')
            ->assertSuccessful();

        $backupDir = storage_path('app/backups');
        $files = glob($backupDir . '/*.sql.gz');

        $this->assertNotEmpty($files);
    }

    public function test_backup_directory_is_created_if_not_exists(): void
    {
        $backupDir = storage_path('app/backups');
        if (is_dir($backupDir)) {
            array_map('unlink', glob($backupDir . '/*'));
        }

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertDirectoryExists($backupDir);
        $this->assertNotEmpty(glob($backupDir . '/*.sql.gz'));
    }

    public function test_audit_purge_command_runs_successfully(): void
    {
        $this->artisan('audit:purge')->assertSuccessful();
    }

    public function test_backup_and_purge_are_scheduled(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $events = $schedule->events();

        $backupScheduled = false;
        $purgeScheduled = false;

        foreach ($events as $event) {
            if (str_contains($event->command, 'backup:database')) {
                $backupScheduled = true;
            }
            if (str_contains($event->command, 'audit:purge')) {
                $purgeScheduled = true;
            }
        }

        $this->assertTrue($backupScheduled, 'backup:database is not scheduled');
        $this->assertTrue($purgeScheduled, 'audit:purge is not scheduled');
    }
}
