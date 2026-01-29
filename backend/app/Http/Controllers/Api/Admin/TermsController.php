<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Term::query()->orderBy('start_date');
        if ($request->filled('academic_year_id')) {
            $q->where('academic_year_id', $request->academic_year_id);
        }
        if ($request->boolean('is_current')) {
            $q->where('is_current', true);
        }
        $terms = $q->get()->map(fn ($t) => [
            'id' => $t->id,
            'academic_year_id' => $t->academic_year_id,
            'name' => $t->name,
            'start_date' => $t->start_date?->format('Y-m-d'),
            'end_date' => $t->end_date?->format('Y-m-d'),
            'is_current' => (bool) $t->is_current,
        ]);
        return response()->json($terms->isEmpty() ? [] : $terms->toArray());
    }
}
