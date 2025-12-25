<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Document\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['template', 'creator']);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $documents = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($documents);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'template_id' => ['required', 'integer', 'exists:doc_templates,id'],
            'data_json' => ['required', 'array'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
        ]);

        // Get next number from registry
        $registry = \DB::table('document_registry')
            ->where('type', $validated['type'])
            ->where('year', now()->year)
            ->lockForUpdate()
            ->first();

        if (!$registry) {
            $registryId = \DB::table('document_registry')->insertGetId([
                'type' => $validated['type'],
                'year' => now()->year,
                'last_number' => 0,
                'updated_at' => now(),
            ]);
            $lastNumber = 0;
            $registry = (object) ['id' => $registryId];
        } else {
            $lastNumber = $registry->last_number;
        }

        $nextNumber = $lastNumber + 1;

        \DB::table('document_registry')
            ->where('id', $registry->id)
            ->update(['last_number' => $nextNumber]);

        $document = Document::create([
            'type' => $validated['type'],
            'number' => (string) $nextNumber,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'template_id' => $validated['template_id'],
            'data_json' => $validated['data_json'],
            'created_by' => auth()->id(),
            'verify_hash' => '', // Will be generated
        ]);

        // Generate document
        $document = $this->documentService->generateDocument($document);

        return response()->json($document->load(['template', 'creator']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $document = Document::with(['template', 'creator'])->findOrFail($id);
        $this->authorize('view', $document);

        return response()->json($document);
    }

    public function verify(string $hash): JsonResponse
    {
        $document = $this->documentService->verifyDocument($hash);

        if (!$document) {
            return response()->json([
                'valid' => false,
                'message' => 'Документ не найден или недействителен',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'document' => [
                'id' => $document->id,
                'type' => $document->type,
                'number' => $document->number,
                'date' => $document->date->format('Y-m-d'),
                'status' => $document->status,
            ],
        ]);
    }

    public function download(int $id, Request $request): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('export', $document);

        $format = $request->query('format', 'docx'); // docx or pdf

        // TODO: get file paths from document (file_path_docx, file_path_pdf)
        // For now, return placeholder
        return response()->json([
            'message' => 'Download not implemented yet',
            'document_id' => $document->id,
            'format' => $format,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('approve', $document);

        // TODO: implement approval logic
        return response()->json(['message' => 'Not implemented']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('approve', $document);

        // TODO: implement rejection logic
        return response()->json(['message' => 'Not implemented']);
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('sign', $document);

        // TODO: implement signing logic
        return response()->json(['message' => 'Not implemented']);
    }
}
