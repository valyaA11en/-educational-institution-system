<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\File;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function getPresignedUploadUrl(Request $request): JsonResponse
    {
        $this->authorize('presign', File::class);

        $validated = $request->validate([
            'assignment_id' => ['sometimes', 'nullable', 'integer', 'exists:assignments,id'],
            'ticket_id' => ['sometimes', 'nullable', 'integer', 'exists:tickets,id'],
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['required', 'string'],
        ]);

        // At least one of assignment_id or ticket_id must be provided
        if (empty($validated['assignment_id']) && empty($validated['ticket_id'])) {
            return response()->json([
                'message' => 'Either assignment_id or ticket_id must be provided',
            ], 422);
        }

        $maxFileSize = 10485760; // Default 10MB
        $allowedTypes = null;

        // If assignment_id is provided, validate against assignment constraints
        if (!empty($validated['assignment_id'])) {
            $assignment = Assignment::findOrFail($validated['assignment_id']);

            // Validate file size
            if ($assignment->max_file_size && $validated['size'] > $assignment->max_file_size) {
                return response()->json([
                    'message' => "Размер файла превышает максимально допустимый: " . $this->formatBytes($assignment->max_file_size),
                ], 422);
            }

            // Validate file type
            if ($assignment->allowed_types && !empty($assignment->allowed_types)) {
                $extension = strtolower(pathinfo($validated['filename'], PATHINFO_EXTENSION));
                $mimeType = $validated['mime'];

                $allowed = false;
                foreach ($assignment->allowed_types as $allowedType) {
                    if ($extension === strtolower($allowedType) || $mimeType === $allowedType) {
                        $allowed = true;
                        break;
                    }
                }

                if (!$allowed) {
                    return response()->json([
                        'message' => "Тип файла не разрешен. Разрешенные типы: " . implode(', ', $assignment->allowed_types),
                    ], 422);
                }
            }

            // Generate unique file path for assignment
            $path = 'assignments/' . $assignment->id . '/' . Str::uuid() . '/' . $validated['filename'];
        } else {
            // For tickets, use default constraints
            if ($validated['size'] > $maxFileSize) {
                return response()->json([
                    'message' => "Размер файла превышает максимально допустимый: " . $this->formatBytes($maxFileSize),
                ], 422);
            }

            // Generate unique file path for ticket
            $path = 'tickets/' . $validated['ticket_id'] . '/' . Str::uuid() . '/' . $validated['filename'];
        }

        // Generate presigned URL for upload (valid for 1 hour)
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region'),
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $command = $s3Client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
            'ContentType' => $validated['mime'],
            'ContentLength' => $validated['size'],
        ]);

        $presignedUrl = (string) $s3Client->createPresignedRequest($command, '+1 hour')->getUri();

        // Create file record
        $file = File::create([
            'storage_key' => $path,
            'original_name' => $validated['filename'],
            'size' => $validated['size'],
            'mime' => $validated['mime'],
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json([
            'file_id' => $file->id,
            'upload_url' => $presignedUrl,
            'expires_at' => now()->addHour()->toIso8601String(),
        ]);
    }

    public function confirmUpload(Request $request, int $fileId): JsonResponse
    {
        $file = File::findOrFail($fileId);

        // Verify file exists in storage
        if (!Storage::disk('s3')->exists($file->storage_key)) {
            return response()->json([
                'message' => 'Файл не найден в хранилище',
            ], 404);
        }

        // Update file status if needed
        // TODO: add status field to files table if needed

        return response()->json([
            'id' => $file->id,
            'filename' => $file->original_name,
            'size' => $file->size,
            'content_type' => $file->mime,
            'file_path' => $file->storage_key,
            'created_at' => $file->created_at?->toIso8601String(),
            'download_url' => Storage::disk('s3')->temporaryUrl($file->storage_key, now()->addHours(24)),
        ]);
    }

    public function download(Request $request, int $fileId): JsonResponse
    {
        $file = File::findOrFail($fileId);

        // TODO: check permissions (user can download if has access to assignment/submission)

        if (!Storage::disk('s3')->exists($file->storage_key)) {
            return response()->json([
                'message' => 'Файл не найден',
            ], 404);
        }

        $downloadUrl = Storage::disk('s3')->temporaryUrl($file->storage_key, now()->addHours(1));

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => now()->addHour()->toIso8601String(),
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

