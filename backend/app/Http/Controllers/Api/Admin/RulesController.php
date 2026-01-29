<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\RuleEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class RulesController extends Controller
{
    protected RuleEngineService $ruleEngine;

    public function __construct(RuleEngineService $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * List all rules (admin view with all tenants)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Rule::with('creator');

        if ($request->has('scope')) {
            $query->forScope($request->scope);
        }
        if ($request->has('enabled')) {
            $query->where('enabled', $request->enabled);
        }
        if ($request->has('tenant_id')) {
            // Filter by tenant if needed
        }

        $perPage = $request->get('per_page', 15);
        $rules = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $rules->items(),
            'pagination' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ]
        ]);
    }

    /**
     * Create a new rule
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'scope' => 'required|in:global,org,term',
            'conditions_json' => 'required|array',
            'actions_json' => 'required|array',
            'enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate conditions and actions
        $conditionsValidation = $this->ruleEngine->validateRuleConditions($request->conditions_json);
        $actionsValidation = $this->ruleEngine->validateRuleActions($request->actions_json);

        if (!$conditionsValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid conditions',
                'errors' => ['conditions' => $conditionsValidation['errors']]
            ], 422);
        }

        if (!$actionsValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid actions',
                'errors' => ['actions' => $actionsValidation['errors']]
            ], 422);
        }

        try {
            $rule = Rule::create([
                'name' => $request->name,
                'scope' => $request->scope,
                'conditions_json' => $request->conditions_json,
                'actions_json' => $request->actions_json,
                'enabled' => $request->enabled ?? true,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rule created successfully',
                'data' => $rule->load('creator')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a rule
     */
    public function update(Request $request, $id): JsonResponse
    {
        $rule = Rule::find($id);

        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Rule not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'scope' => 'sometimes|in:global,org,term',
            'conditions_json' => 'sometimes|array',
            'actions_json' => 'sometimes|array',
            'enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate conditions and actions if provided
        if ($request->has('conditions_json')) {
            $conditionsValidation = $this->ruleEngine->validateRuleConditions($request->conditions_json);
            if (!$conditionsValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid conditions',
                    'errors' => ['conditions' => $conditionsValidation['errors']]
                ], 422);
            }
        }

        if ($request->has('actions_json')) {
            $actionsValidation = $this->ruleEngine->validateRuleActions($request->actions_json);
            if (!$actionsValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid actions',
                    'errors' => ['actions' => $actionsValidation['errors']]
                ], 422);
            }
        }

        try {
            $updateData = [];
            if ($request->has('name')) $updateData['name'] = $request->name;
            if ($request->has('scope')) $updateData['scope'] = $request->scope;
            if ($request->has('conditions_json')) $updateData['conditions_json'] = $request->conditions_json;
            if ($request->has('actions_json')) $updateData['actions_json'] = $request->actions_json;
            if ($request->has('enabled')) $updateData['enabled'] = $request->enabled;

            $rule->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Rule updated successfully',
                'data' => $rule->load('creator')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a rule
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $rule = Rule::find($id);

        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Rule not found'
            ], 404);
        }

        try {
            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rule deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle rule enabled status
     */
    public function toggle(Request $request, $id): JsonResponse
    {
        $rule = Rule::find($id);

        if (!$rule) {
            return response()->json([
                'success' => false,
                'message' => 'Rule not found'
            ], 404);
        }

        try {
            $rule->enabled = !$rule->enabled;
            $rule->save();

            return response()->json([
                'success' => true,
                'message' => 'Rule toggled successfully',
                'data' => $rule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle rule: ' . $e->getMessage()
            ], 500);
        }
    }
}
