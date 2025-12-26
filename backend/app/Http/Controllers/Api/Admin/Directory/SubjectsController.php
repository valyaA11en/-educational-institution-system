<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Directory\StoreSubjectRequest;
use App\Http\Requests\Admin\Directory\UpdateSubjectRequest;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubjectsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subject::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $subjects = $query->orderBy('name')->paginate($request->integer('per_page', 50));

        return response()->json($subjects);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = Subject::create($request->validated());

        return response()->json($subject, Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        return response()->json($subject);
    }

    public function update(UpdateSubjectRequest $request, int $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);
        $subject->fill($request->validated());
        $subject->save();

        return response()->json($subject);
    }

    public function destroy(int $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


