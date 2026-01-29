<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PortfolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $studentId = $request->input('student_id', $user->id);

        // Проверка прав
        if ($studentId != $user->id && !$user->hasRole('admin') && !$user->hasRole('преподаватель')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = PortfolioItem::where('student_user_id', $studentId);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        $items = $query->orderBy('date', 'desc')->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Только студент может добавлять в своё портфолио
        $isStudent = $user->hasRole('student') || $user->hasRole('студент');
        if (!$isStudent) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:achievement,project,certificate,contest,other',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'file' => 'nullable|file|max:10240', // 10MB
            'is_public' => 'boolean',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = Storage::disk('s3')->put("portfolio/{$user->id}", $file);
        }

        $item = PortfolioItem::create([
            'student_user_id' => $user->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'],
            'file_path' => $filePath,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $item = PortfolioItem::findOrFail($id);
        $user = Auth::user();

        if ($item->student_user_id != $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'date' => 'sometimes|date',
            'is_public' => 'boolean',
        ]);

        $item->update($validated);

        return response()->json(['data' => $item]);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = PortfolioItem::findOrFail($id);
        $user = Auth::user();

        if ($item->student_user_id != $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($item->file_path) {
            Storage::disk('s3')->delete($item->file_path);
        }

        $item->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

