<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SmokeTest extends Command
{
    protected $signature = 'smoke:test';
    protected $description = 'Run smoke tests after deployment';

    public function handle(): int
    {
        $baseUrl = config('app.url');
        $this->info("Running smoke tests against: {$baseUrl}");

        $tests = [
            ['GET', '/api/health', 200],
            ['GET', '/api/metrics', 200],
        ];

        $passed = 0;
        $failed = 0;

        foreach ($tests as [$method, $path, $expectedStatus]) {
            try {
                $response = Http::timeout(5)->send($method, $baseUrl . $path);
                
                if ($response->status() === $expectedStatus) {
                    $this->info("✓ {$method} {$path} - OK");
                    $passed++;
                } else {
                    $this->error("✗ {$method} {$path} - Expected {$expectedStatus}, got {$response->status()}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("✗ {$method} {$path} - Error: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("\nResults: {$passed} passed, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }
}


