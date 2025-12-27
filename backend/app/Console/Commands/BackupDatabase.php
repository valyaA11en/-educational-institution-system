<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';
    protected $description = 'Backup PostgreSQL database';

    public function handle(): int
    {
        $this->info('Starting database backup...');

        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbHost = config('database.connections.pgsql.host');
        $dbPassword = config('database.connections.pgsql.password');

        $timestamp = now()->format('Ymd_His');
        $filename = "backup_{$timestamp}.sql";
        $path = storage_path("app/backups/{$filename}");

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -U %s -d %s > %s',
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Backup failed');
            return 1;
        }

        // Compress
        exec("gzip {$path}");

        $this->info("Backup created: {$filename}.gz");

        // Cleanup old backups (keep last 30 days)
        $this->cleanupOldBackups();

        return 0;
    }

    protected function cleanupOldBackups(): void
    {
        $backupDir = storage_path('app/backups');
        $files = glob("{$backupDir}/backup_*.sql.gz");

        foreach ($files as $file) {
            if (filemtime($file) < now()->subDays(30)->timestamp) {
                unlink($file);
            }
        }
    }
}


