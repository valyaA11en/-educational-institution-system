<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GostDocumentService;
use App\Models\DocTemplate;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GostDocumentController extends Controller
{
    public function __construct(
        private GostDocumentService $gostDocumentService
    ) {}

    /**
     * GET /api/gost-documents/templates
     * Список шаблонов
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = DocTemplate::whereIn('type', ['order', 'decree', 'приказ', 'распоряжение'])
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'type' => $template->type,
                ];
            });

        return response()->json($templates);
    }

    /**
     * POST /api/gost-documents/orders
     * Генерация приказа
     */
    public function generateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'date' => 'required|string|regex:/^\d{2}\.\d{2}\.\d{4}$/',
            'title' => 'required|string|max:500',
            'content' => 'required|string',
            'signer' => 'nullable|string|max:200',
            'approver' => 'nullable|string|max:200',
        ]);

        try {
            $user = Auth::user();
            $document = $this->gostDocumentService->generateOrder(
                $validated,
                $user->tenant_id ?? 1,
                $user->id
            );

            $dataJson = $document->data_json ?? [];
            return response()->json([
                'message' => 'Order generated successfully',
                'document' => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'number' => $document->number,
                    'date' => $document->date?->format('d.m.Y'),
                    'docx_url' => isset($dataJson['docx_file_path']) 
                        ? Storage::disk('documents')->url($dataJson['docx_file_path']) 
                        : null,
                    'pdf_url' => isset($dataJson['pdf_file_path']) 
                        ? Storage::disk('documents')->url($dataJson['pdf_file_path']) 
                        : null,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate order', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to generate order',
            ], 500);
        }
    }

    /**
     * POST /api/gost-documents/decrees
     * Генерация распоряжения
     */
    public function generateDecree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'date' => 'required|string|regex:/^\d{2}\.\d{2}\.\d{4}$/',
            'title' => 'required|string|max:500',
            'content' => 'required|string',
            'signer' => 'nullable|string|max:200',
        ]);

        try {
            $user = Auth::user();
            $document = $this->gostDocumentService->generateDecree(
                $validated,
                $user->tenant_id ?? 1,
                $user->id
            );

            $dataJson = $document->data_json ?? [];
            return response()->json([
                'message' => 'Decree generated successfully',
                'document' => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'number' => $document->number,
                    'date' => $document->date?->format('d.m.Y'),
                    'docx_url' => isset($dataJson['docx_file_path']) 
                        ? Storage::disk('documents')->url($dataJson['docx_file_path']) 
                        : null,
                    'pdf_url' => isset($dataJson['pdf_file_path']) 
                        ? Storage::disk('documents')->url($dataJson['pdf_file_path']) 
                        : null,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate decree', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to generate decree',
            ], 500);
        }
    }

    /**
     * GET /api/gost-documents/{id}
     * Получить документ
     */
    public function show(int $id): JsonResponse
    {
        $document = Document::whereIn('type', ['order', 'decree', 'decision', 'приказ', 'распоряжение'])
            ->findOrFail($id);

        return response()->json([
            'id' => $document->id,
            'type' => $document->type,
            'number' => $document->number,
            'date' => $document->date?->format('d.m.Y'),
            'status' => $document->status,
            'data_json' => $document->data_json,
            'created_at' => $document->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /api/documents/{id}/gost/validate
     * Валидация документа ГОСТ
     */
    public function validateDocument(Request $request, int $id): JsonResponse
    {
        $document = Document::whereIn('type', ['order', 'decree', 'decision', 'приказ', 'распоряжение'])
            ->findOrFail($id);

        $errors = $this->gostDocumentService->validateDocument($document);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
}
