<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckBackups extends Command
{
    protected $signature = 'backup:check';
    protected $description = 'Check backup integrity and age';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        $files = glob("{$backupDir}/*.sql.gz");

        if (empty($files)) {
            $this->warn('No backups found');
            return 1;
        }

        $this->info('Found ' . count($files) . ' backup(s)');

        $oldest = null;
        $newest = null;

        foreach ($files as $file) {
            $age = now()->diffInDays(filemtime($file));
            $size = filesize($file);
            
            if (!$oldest || filemtime($file) < filemtime($oldest)) {
                $oldest = $file;
            }
            if (!$newest || filemtime($file) > filemtime($newest)) {
                $newest = $file;
            }

            $this->line("  " . basename($file) . " - {$age} days old, " . round($size / 1024 / 1024, 2) . " MB");
        }

        $newestAge = now()->diffInDays(filemtime($newest));
        if ($newestAge > 1) {
            $this->error("Newest backup is {$newestAge} days old!");
            return 1;
        }

        $this->info('Backups are up to date');
        return 0;
    }
}


