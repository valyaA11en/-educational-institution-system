<?php

namespace App\Services\Secrets;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class SecretsManager
{
    public function store(string $key, string $value, ?int $tenantId = null): void
    {
        $encrypted = Crypt::encryptString($value);
        $path = $this->getPath($key, $tenantId);
        
        Storage::put($path, $encrypted);
    }

    public function get(string $key, ?int $tenantId = null): ?string
    {
        $path = $this->getPath($key, $tenantId);
        
        if (!Storage::exists($path)) {
            return null;
        }

        $encrypted = Storage::get($path);
        return Crypt::decryptString($encrypted);
    }

    public function delete(string $key, ?int $tenantId = null): void
    {
        $path = $this->getPath($key, $tenantId);
        Storage::delete($path);
    }

    protected function getPath(string $key, ?int $tenantId = null): string
    {
        $prefix = $tenantId ? "tenants/{$tenantId}/" : 'global/';
        return "secrets/{$prefix}{$key}.enc";
    }
}


