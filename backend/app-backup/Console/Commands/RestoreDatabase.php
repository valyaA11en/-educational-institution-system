<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RestoreDatabase extends Command
{
    protected $signature = 'backup:restore {file}';
    protected $description = 'Restore PostgreSQL database from backup';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Backup file not found: {$file}");
            return 1;
        }

        $this->info('Restoring database...');
        $this->warn('This will overwrite existing data!');

        if (!$this->confirm('Continue?')) {
            return 0;
        }

        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbHost = config('database.connections.pgsql.host');
        $dbPassword = config('database.connections.pgsql.password');

        // Decompress if needed
        if (str_ends_with($file, '.gz')) {
            $decompressed = str_replace('.gz', '', $file);
            exec("gunzip -c {$file} > {$decompressed}");
            $file = $decompressed;
        }

        $command = sprintf(
            'PGPASSWORD=%s psql -h %s -U %s -d %s < %s',
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($file)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Restore failed');
            return 1;
        }

        $this->info('Database restored successfully');
        return 0;
    }
}


