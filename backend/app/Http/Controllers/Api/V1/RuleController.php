<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rules\StoreRuleRequest;
use App\Http\Requests\Rules\UpdateRuleRequest;
use App\Models\Rule;
use App\Services\Rule\RuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function __construct(
        private RuleEngine $ruleEngine
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Rule::with('creator');

        if ($scope = $request->query('scope')) {
            $query->where('scope', $scope);
        }

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        $rules = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($rules);
    }

    public function store(StoreRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $rule = Rule::create([
            'name' => $validated['name'],
            'enabled' => $validated['enabled'] ?? true,
            'scope' => $validated['scope'],
            'conditions_json' => $validated['conditions_json'],
            'actions_json' => $validated['actions_json'],
            'created_by' => auth()->id(),
        ]);

        return response()->json($rule->load('creator'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $rule = Rule::with('creator')->findOrFail($id);
        return response()->json($rule);
    }

    public function update(UpdateRuleRequest $request, int $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $validated = $request->validated();

        $rule->update($validated);

        return response()->json($rule->load('creator'));
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

        return response()->json($rule);
    }

    /**
     * Validate rule conditions and actions
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'conditions_json' => ['required', 'array'],
            'actions_json' => ['required', 'array'],
        ]);

        $errors = $this->ruleEngine->validateRule(
            $request->input('conditions_json'),
            $request->input('actions_json')
        );

        if (empty($errors)) {
            return response()->json(['valid' => true, 'message' => 'Rule is valid']);
        }

        return response()->json([
            'valid' => false,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Test rule execution with sample context
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $context = $request->input('context', []);

        // Create a mock event for testing
        $mockEvent = new \App\Models\OutboxEvent([
            'event_type' => $request->input('event_type', 'test.event'),
            'actor_user_id' => auth()->id(),
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
            'payload_json' => $context['payload'] ?? [],
        ]);

        $matches = $this->ruleEngine->matchesConditions($rule, $mockEvent);

        return response()->json([
            'rule_id' => $rule->id,
            'matches' => $matches,
            'conditions' => $rule->conditions_json,
            'actions' => $rule->actions_json,
        ]);
    }
}
