<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocTemplate;
use App\Services\Document\DocumentService;
use App\Services\Document\DocumentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private DocumentWorkflowService $workflowService
    ) {}

    public function templates(Request $request): JsonResponse
    {
        $templates = DocTemplate::query();

        if ($type = $request->query('type')) {
            $templates->where('type', $type);
        }

        return response()->json($templates->get());
    }

    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['template', 'creator', 'signer']);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->query('dateFrom')) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('dateTo')) {
            $query->where('date', '<=', $dateTo);
        }

        if ($search = $request->query('search')) {
            $query->where('number', 'like', "%{$search}%");
        }

        $documents = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $documents->items(),
            'current_page' => $documents->currentPage(),
            'per_page' => $documents->perPage(),
            'total' => $documents->total(),
            'last_page' => $documents->lastPage(),
        ]);
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

        $document = Document::create([
            'type' => $validated['type'],
            'number' => '', // Will be assigned on registration
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
        $document = Document::with([
            'template',
            'creator',
            'signer',
            'routes.approverRole',
            'routes.approverUser',
            'acks.user'
        ])->findOrFail($id);
        $this->authorize('view', $document);

        return response()->json($document);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('update', $document);

        if ($document->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft documents can be updated',
            ], 422);
        }

        $validated = $request->validate([
            'data_json' => ['required', 'array'],
        ]);

        $document->update([
            'data_json' => $validated['data_json'],
        ]);

        return response()->json($document->load(['template', 'creator']));
    }

    public function sendToApproval(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('approve', $document);

        $validated = $request->validate([
            'route' => ['required', 'array', 'min:1'],
            'route.*.stepNo' => ['required', 'integer', 'min:1'],
            'route.*.approverRoleId' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'route.*.approverUserId' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $this->workflowService->sendToApproval($document, $validated['route']);

        return response()->json($document->load(['routes', 'template', 'creator']));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('approve', $document);

        $validated = $request->validate([
            'comment' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->workflowService->approveStep($document, $validated['comment'] ?? null);

        return response()->json($document->load(['routes', 'template', 'creator', 'signer']));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('approve', $document);

        $validated = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $this->workflowService->rejectStep($document, $validated['comment']);

        return response()->json($document->load(['routes', 'template', 'creator']));
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('sign', $document);

        $validated = $request->validate([
            'comment' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->workflowService->sign($document, $validated['comment'] ?? null);

        return response()->json($document->load(['routes', 'template', 'creator', 'signer']));
    }

    public function setAckTargets(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('view', $document);

        $validated = $request->validate([
            'userIds' => ['required', 'array', 'min:1'],
            'userIds.*' => ['integer', 'exists:users,id'],
        ]);

        $this->workflowService->setAckTargets($document, $validated['userIds']);

        return response()->json(['message' => 'Acknowledgment targets set']);
    }

    public function confirmAck(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('view', $document);

        $this->workflowService->confirmAck($document, auth()->id());

        return response()->json(['message' => 'Acknowledged']);
    }

    public function getAck(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('view', $document);

        $acks = $document->acks()->with('user')->get();

        return response()->json(['data' => $acks]);
    }

    public function registerNumber(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $this->authorize('viewAny', Document::class); // Only admin/registry

        $this->workflowService->registerNumber($document);

        return response()->json($document->load(['template', 'creator']));
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

    public function download(int $id, Request $request)
    {
        $document = Document::findOrFail($id);
        $this->authorize('export', $document);

        $format = $request->query('format', 'docx'); // docx or pdf

        // TODO: get file paths from document (file_path_docx, file_path_pdf)
        // For now, generate on-the-fly or return 501 if PDF not configured
        if ($format === 'pdf') {
            // Check if PDF conversion is available
            $pdfPath = null; // TODO: get from document or generate
            
            if (!$pdfPath) {
                return response()->json([
                    'message' => 'PDF conversion not configured',
                ], 501);
            }
            
            // TODO: return PDF file
            return response()->json([
                'message' => 'PDF download not implemented yet',
            ], 501);
        }

        // DOCX format
        $docxPath = null; // TODO: get from document or generate
        
        if (!$docxPath) {
            return response()->json([
                'message' => 'DOCX file not found',
            ], 404);
        }

        // TODO: return DOCX file
        return response()->json([
            'message' => 'DOCX download not implemented yet',
        ], 501);
    }

    /**
     * Print document as PDF
     * GET /api/documents/{id}/print?format=pdf
     */
    public function print(int $id, Request $request)
    {
        $document = Document::with(['signer', 'acks.user'])->findOrFail($id);
        $this->authorize('view', $document);

        $format = $request->query('format', 'pdf');

        if ($format !== 'pdf') {
            return response()->json([
                'message' => 'Only PDF format is supported for printing',
            ], 422);
        }

        $tenant = app('tenant') ?? \App\Models\Tenant::first();
        $data = $document->data_json ?? [];

        // Get acknowledgment recipients
        $acks = $document->acks()->with('user')->get();

        $viewData = [
            'document' => $document,
            'tenant' => $tenant,
            'data' => $data,
            'acks' => $acks,
        ];

        // Select template based on document type
        $template = 'print.document_general'; // fallback

        if ($document->type === 'order') {
            $template = 'print.order_gost';
        } elseif ($document->type === 'decision') {
            $template = 'print.decision_gost';
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, $viewData);
            
            $filename = sprintf(
                '%s_%s_%s.pdf',
                $document->type,
                $document->number ?? $document->id,
                $document->date ? \Carbon\Carbon::parse($document->date)->format('Y-m-d') : now()->format('Y-m-d')
            );

            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF for document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
