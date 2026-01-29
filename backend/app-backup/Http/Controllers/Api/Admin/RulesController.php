<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRuleRequest;
use App\Http\Requests\Admin\UpdateRuleRequest;
use App\Models\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RulesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Rule::query();

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        if ($scope = $request->query('scope')) {
            $query->where('scope', $scope);
        }

        if ($search = $request->query('q')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $rules = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        // Transform to DTO format
        $rules->getCollection()->transform(function ($rule) {
            return $this->toDTO($rule);
        });

        return response()->json($rules);
    }

    public function store(StoreRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $rule = Rule::create([
            'name' => $validated['name'],
            'enabled' => $validated['enabled'] ?? true,
            'scope' => $validated['scope'] ?? 'global',
            'conditions_json' => $validated['conditions_json'],
            'actions_json' => $validated['actions_json'],
            'created_by' => auth()->id(),
        ]);

        return response()->json($this->toDTO($rule), 201);
    }

    public function update(UpdateRuleRequest $request, int $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $validated = $request->validated();

        $rule->update($validated);

        return response()->json($this->toDTO($rule));
    }

    public function destroy(int $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Rule deleted']);
    }

    public function toggle(int $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $rule->update(['enabled' => !$rule->enabled]);

        return response()->json($this->toDTO($rule));
    }

    /**
     * Convert Rule model to DTO
     */
    private function toDTO(Rule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'enabled' => $rule->enabled,
            'conditions_json' => $rule->conditions_json ?? [],
            'actions_json' => $rule->actions_json ?? [],
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}

