<?php

namespace App\Http\Middleware;

use App\Models\File;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FileAccessControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $fileId = $request->route('id') ?? $request->input('file_id');

        if ($fileId) {
            $file = File::find($fileId);
            
            if (!$file) {
                return response()->json(['message' => 'File not found'], 404);
            }

            // Check tenant isolation
            if (app()->bound('tenant_id') && $file->tenant_id !== app('tenant_id')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // TODO: Check object-level permissions
        }

        return $next($request);
    }
}


