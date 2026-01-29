<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    private const FILES_DISK = 'local';
    private const FILES_PREFIX = 'files';

    /**
     * Get presigned upload URL: creates a pending file row and returns file_id + upload_url.
     * Client then POSTs the file to upload_url (POST /files/upload) with file_id.
     */
    public function getPresignedUploadUrl(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
            'mime' => 'required|string|max:128',
            'size' => 'required|integer|min:0|max:52428800',
            'assignment_id' => 'nullable|exists:assignments,id',
            'ticket_id' => 'nullable|exists:tickets,id',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $userId = (int) auth()->id();
        $tenantId = (int) auth()->user()->tenant_id;
        $uuid = Str::uuid()->toString();
        $storageKey = self::FILES_PREFIX . '/pending/' . $uuid;

        $id = DB::table('files')->insertGetId([
            'storage_key' => $storageKey,
            'original_name' => $request->filename,
            'size' => 0,
            'mime' => $request->mime,
            'uploaded_by' => $userId,
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $base = rtrim(config('app.url'), '/');
        $uploadUrl = $base . '/api/v1/files/upload?file_id=' . $id;

        return response()->json([
            'file_id' => $id,
            'upload_url' => $uploadUrl,
        ]);
    }

    /**
     * Direct upload: store file and update DB. Use when S3 presigned is not used.
     */
    public function upload(Request $request): JsonResponse
    {
        $fileId = $request->query('file_id') ?? $request->input('file_id');
        $v = Validator::make(['file_id' => $fileId, 'file' => $request->file('file')], [
            'file_id' => 'required|integer|exists:files,id',
            'file' => 'required|file|max:52428800',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $userId = (int) auth()->id();
        $f = DB::table('files')->where('id', $fileId)->where('uploaded_by', $userId)->first();
        if (!$f) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if (!str_starts_with($f->storage_key, self::FILES_PREFIX . '/pending/')) {
            return response()->json(['message' => 'File already uploaded'], 422);
        }

        $file = $request->file('file');
        $path = $file->store(self::FILES_PREFIX, self::FILES_DISK);
        if (!$path) {
            return response()->json(['message' => 'Storage failed'], 500);
        }

        DB::table('files')->where('id', $f->id)->update([
            'storage_key' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'updated_at' => now(),
        ]);

        $row = DB::table('files')->where('id', $f->id)->first();

        return response()->json([
            'file' => $this->fileToDto($row),
        ]);
    }

    /**
     * Confirm upload (e.g. after client upload to upload_url). Returns file record.
     */
    public function confirmUpload(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'filename' => 'sometimes|string|max:255',
            'size' => 'sometimes|integer|min:0',
            'content_type' => 'sometimes|string|max:128',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $userId = (int) auth()->id();
        $f = DB::table('files')->where('id', $id)->where('uploaded_by', $userId)->first();
        if (!$f) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $upd = [];
        if ($request->filled('filename')) {
            $upd['original_name'] = $request->filename;
        }
        if ($request->filled('size')) {
            $upd['size'] = (int) $request->size;
        }
        if ($request->filled('content_type')) {
            $upd['mime'] = $request->content_type;
        }
        if (!empty($upd)) {
            $upd['updated_at'] = now();
            DB::table('files')->where('id', $id)->update($upd);
            $f = DB::table('files')->where('id', $id)->first();
        }

        return response()->json(['file' => $this->fileToDto($f)]);
    }

    /**
     * Download file by id.
     */
    public function download(Request $request, $id): StreamedResponse|JsonResponse
    {
        $f = DB::table('files')->where('id', $id)->first();
        if (!$f) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if (str_starts_with($f->storage_key, self::FILES_PREFIX . '/pending/')) {
            return response()->json(['message' => 'File not yet uploaded'], 422);
        }

        if (!Storage::disk(self::FILES_DISK)->exists($f->storage_key)) {
            return response()->json(['message' => 'File missing in storage'], 404);
        }

        $name = $f->original_name ?: 'download';

        return response()->streamDownload(
            function () use ($f): void {
                echo Storage::disk(self::FILES_DISK)->get($f->storage_key);
            },
            $name,
            [
                'Content-Type' => $f->mime ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . addslashes($name) . '"',
            ],
            'attachment'
        );
    }

    /**
     * Delete file (own files only).
     */
    public function delete(Request $request, $id): JsonResponse
    {
        $userId = (int) auth()->id();
        $f = DB::table('files')->where('id', $id)->where('uploaded_by', $userId)->first();
        if (!$f) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if (Storage::disk(self::FILES_DISK)->exists($f->storage_key)) {
            Storage::disk(self::FILES_DISK)->delete($f->storage_key);
        }
        DB::table('files')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function fileToDto(object $row): object
    {
        return (object) [
            'id' => $row->id,
            'filename' => $row->original_name,
            'size' => (int) $row->size,
            'content_type' => $row->mime,
            'file_path' => $row->storage_key,
            'created_at' => $row->created_at,
        ];
    }
}
