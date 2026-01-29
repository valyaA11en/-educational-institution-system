<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRoute;
use App\Models\DocumentAck;
use App\Models\DocTemplate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DocumentController extends Controller
{
    /**
     * Get available document templates
     */
    public function templates(Request $request): JsonResponse
    {
        $query = DocTemplate::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by tenant
        $tenantId = auth()->user()->tenant_id;
        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        $templates = $query->get();

        return response()->json([
            'data' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'type' => $template->type,
                    'name' => $template->name,
                    'schema' => $template->schema_json,
                ];
            })
        ]);
    }

    /**
     * List documents
     */
    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['template', 'creator', 'signer', 'routes', 'acknowledgments']);

        // Filter by tenant
        $tenantId = auth()->user()->tenant_id;
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Search by number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                  ->orWhere('type', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $documents = $query->orderBy('date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

        return response()->json([
            'data' => $documents->items(),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    /**
     * Create a new document
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'template_id' => 'required|exists:doc_templates,id',
            'data' => 'required|array',
            'date' => 'nullable|date',
            'number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tenantId = auth()->user()->tenant_id;
            $date = $request->date ?? Carbon::today();

            // Generate number if not provided
            $number = $request->number;
            if (!$number) {
                $number = $this->generateDocumentNumber($request->type, $date->year);
            }

            // Generate verify hash
            $verifyHash = Document::generateVerifyHash(0, $request->type, $number, $date->format('Y-m-d'));

            $document = Document::create([
                'type' => $request->type,
                'number' => $number,
                'date' => $date,
                'status' => 'draft',
                'template_id' => $request->template_id,
                'data_json' => $request->data,
                'created_by' => auth()->id(),
                'verify_hash' => $verifyHash,
                'tenant_id' => $tenantId,
            ]);

            // Update verify hash with actual document ID
            $document->verify_hash = Document::generateVerifyHash($document->id, $document->type, $document->number, $document->date->format('Y-m-d'));
            $document->save();

            DB::commit();

            $document->load(['template', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Document created successfully',
                'data' => $document
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific document
     */
    public function show(Request $request, $id): JsonResponse
    {
        $document = Document::with([
            'template',
            'creator',
            'signer',
            'routes.approverRole',
            'routes.approverUser',
            'acknowledgments.user'
        ])->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // Check tenant access
        if ($document->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        return response()->json([
            'data' => $document
        ]);
    }

    /**
     * Update a document
     */
    public function update(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // Check tenant access
        if ($document->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        // Only allow updates in draft status
        if ($document->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Document cannot be updated in current status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'data' => 'sometimes|array',
            'date' => 'sometimes|date',
            'number' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('data')) {
                $document->data_json = $request->data;
            }
            if ($request->has('date')) {
                $document->date = $request->date;
            }
            if ($request->has('number')) {
                $document->number = $request->number;
                // Regenerate verify hash if number changed
                $document->verify_hash = Document::generateVerifyHash(
                    $document->id,
                    $document->type,
                    $document->number,
                    $document->date->format('Y-m-d')
                );
            }

            $document->save();

            $document->load(['template', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send document to approval
     */
    public function sendToApproval(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        if ($document->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Document must be in draft status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'routes' => 'required|array',
            'routes.*.step_no' => 'required|integer|min:1',
            'routes.*.approver_role_id' => 'nullable|exists:roles,id',
            'routes.*.approver_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate that either role_id or user_id is provided
        foreach ($request->routes as $route) {
            if (empty($route['approver_role_id']) && empty($route['approver_user_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Each route must have either approver_role_id or approver_user_id'
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            // Create approval routes
            foreach ($request->routes as $routeData) {
                DocumentRoute::create([
                    'document_id' => $document->id,
                    'step_no' => $routeData['step_no'],
                    'approver_role_id' => $routeData['approver_role_id'] ?? null,
                    'approver_user_id' => $routeData['approver_user_id'] ?? null,
                    'status' => 'pending',
                ]);
            }

            // Update document status
            $document->status = 'on_review';
            $document->save();

            DB::commit();

            $document->load(['routes']);

            return response()->json([
                'success' => true,
                'message' => 'Document sent to approval',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send document to approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve document at current step
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $document = Document::with('routes')->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        if ($document->status !== 'on_review') {
            return response()->json([
                'success' => false,
                'message' => 'Document is not in review status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'step_no' => 'required|integer',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $route = DocumentRoute::where('document_id', $document->id)
                ->where('step_no', $request->step_no)
                ->first();

            if (!$route) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval route not found'
                ], 404);
            }

            // Check if user can approve this step
            $user = auth()->user();
            $canApprove = false;

            if ($route->approver_user_id && $route->approver_user_id === $user->id) {
                $canApprove = true;
            } elseif ($route->approver_role_id) {
                $canApprove = $user->roles()->where('id', $route->approver_role_id)->exists();
            }

            if (!$canApprove) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to approve this step'
                ], 403);
            }

            // Approve current step
            $route->status = 'approved';
            $route->decided_at = now();
            $route->comment = $request->comment ?? null;
            $route->save();

            // Check if all steps are approved
            $pendingRoutes = DocumentRoute::where('document_id', $document->id)
                ->where('status', 'pending')
                ->count();

            if ($pendingRoutes === 0) {
                $document->status = 'approved';
                $document->save();
            }

            DB::commit();

            $document->load(['routes']);

            return response()->json([
                'success' => true,
                'message' => 'Document step approved',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject document
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $document = Document::with('routes')->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        if ($document->status !== 'on_review') {
            return response()->json([
                'success' => false,
                'message' => 'Document is not in review status'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'step_no' => 'required|integer',
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $route = DocumentRoute::where('document_id', $document->id)
                ->where('step_no', $request->step_no)
                ->first();

            if (!$route) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval route not found'
                ], 404);
            }

            // Check if user can reject this step
            $user = auth()->user();
            $canReject = false;

            if ($route->approver_user_id && $route->approver_user_id === $user->id) {
                $canReject = true;
            } elseif ($route->approver_role_id) {
                $canReject = $user->roles()->where('id', $route->approver_role_id)->exists();
            }

            if (!$canReject) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to reject this step'
                ], 403);
            }

            // Reject current step
            $route->status = 'rejected';
            $route->decided_at = now();
            $route->comment = $request->comment;
            $route->save();

            // Reject document
            $document->status = 'draft';
            $document->save();

            // Reset all pending routes
            DocumentRoute::where('document_id', $document->id)
                ->where('status', 'pending')
                ->update(['status' => 'pending']);

            DB::commit();

            $document->load(['routes']);

            return response()->json([
                'success' => true,
                'message' => 'Document rejected',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sign document
     */
    public function sign(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        if ($document->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Document must be approved before signing'
            ], 403);
        }

        try {
            $document->signed_by = auth()->id();
            $document->signed_at = now();
            $document->status = 'signed';
            $document->save();

            $document->load(['signer']);

            return response()->json([
                'success' => true,
                'message' => 'Document signed successfully',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sign document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set acknowledgment targets
     */
    public function setAckTargets(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Remove existing acknowledgments
            DocumentAck::where('document_id', $document->id)->delete();

            // Create new acknowledgments
            foreach ($request->user_ids as $userId) {
                DocumentAck::create([
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'status' => 'read',
                ]);
            }

            DB::commit();

            $document->load(['acknowledgments.user']);

            return response()->json([
                'success' => true,
                'message' => 'Acknowledgment targets set successfully',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set acknowledgment targets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm acknowledgment
     */
    public function confirmAck(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        $user = auth()->user();

        try {
            $ack = DocumentAck::where('document_id', $document->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$ack) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not in the acknowledgment list'
                ], 404);
            }

            $ack->status = 'confirmed';
            $ack->confirmed_at = now();
            $ack->save();

            return response()->json([
                'success' => true,
                'message' => 'Acknowledgment confirmed',
                'data' => $ack
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm acknowledgment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get acknowledgments for document
     */
    public function getAck(Request $request, $id): JsonResponse
    {
        $document = Document::with(['acknowledgments.user'])->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        return response()->json([
            'data' => $document->acknowledgments->map(function ($ack) {
                return [
                    'id' => $ack->id,
                    'user' => [
                        'id' => $ack->user->id,
                        'fio' => $ack->user->fio,
                        'email' => $ack->user->email,
                    ],
                    'status' => $ack->status,
                    'confirmed_at' => $ack->confirmed_at?->toIso8601String(),
                ];
            })
        ]);
    }

    /**
     * Register document number
     */
    public function registerNumber(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if number is unique for this type and year
            $exists = Document::where('type', $document->type)
                ->where('number', $request->number)
                ->where('id', '!=', $document->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document number already exists'
                ], 422);
            }

            $document->number = $request->number;
            $document->verify_hash = Document::generateVerifyHash(
                $document->id,
                $document->type,
                $document->number,
                $document->date->format('Y-m-d')
            );
            $document->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document number registered successfully',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to register document number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document
     */
    public function download(Request $request, $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // TODO: Implement actual file download
        // For now, return document data
        return response()->json([
            'success' => true,
            'message' => 'Download functionality not fully implemented',
            'data' => [
                'document_id' => $document->id,
                'type' => $document->type,
                'number' => $document->number,
                'date' => $document->date->format('Y-m-d'),
                'data' => $document->data_json,
            ]
        ]);
    }

    /**
     * Print document
     */
    public function print(Request $request, $id): JsonResponse
    {
        $document = Document::with(['template', 'creator', 'signer'])->find($id);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // TODO: Implement actual PDF generation
        // For now, return document data for printing
        return response()->json([
            'success' => true,
            'message' => 'Print functionality not fully implemented',
            'data' => [
                'document' => $document,
                'template' => $document->template,
            ]
        ]);
    }

    /**
     * Verify document by hash
     */
    public function verify(Request $request, $hash): JsonResponse
    {
        $document = Document::with(['template', 'creator', 'signer'])->where('verify_hash', $hash)->first();

        if (!$document) {
            return response()->json([
                'valid' => false,
                'message' => 'Document not found'
            ]);
        }

        // Verify hash
        $expectedHash = Document::generateVerifyHash(
            $document->id,
            $document->type,
            $document->number,
            $document->date->format('Y-m-d')
        );

        $isValid = $document->verify_hash === $expectedHash;

        return response()->json([
            'valid' => $isValid,
            'document' => $isValid ? [
                'id' => $document->id,
                'type' => $document->type,
                'number' => $document->number,
                'date' => $document->date->format('Y-m-d'),
                'status' => $document->status,
                'created_by' => $document->creator->fio ?? null,
                'signed_by' => $document->signer->fio ?? null,
                'signed_at' => $document->signed_at?->toIso8601String(),
            ] : null
        ]);
    }

    /**
     * Generate document number from registry
     */
    private function generateDocumentNumber(string $type, int $year): string
    {
        $registry = DB::table('document_registry')
            ->where('type', $type)
            ->where('year', $year)
            ->first();

        if ($registry) {
            $lastNumber = $registry->last_number + 1;
            DB::table('document_registry')
                ->where('type', $type)
                ->where('year', $year)
                ->update(['last_number' => $lastNumber, 'updated_at' => now()]);
        } else {
            $lastNumber = 1;
            DB::table('document_registry')->insert([
                'type' => $type,
                'year' => $year,
                'last_number' => $lastNumber,
                'updated_at' => now(),
            ]);
        }

        return sprintf('%s-%d/%d', $type, $lastNumber, $year);
    }
}
