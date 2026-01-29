<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Services\RuleEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class RuleController extends Controller
{
    protected RuleEngineService $ruleEngine;

    public function __construct(RuleEngineService $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Rule::with('creator');

        if ($request->has('scope')) {
            $query->forScope($request->scope);
        }
        if ($request->has('enabled')) {
            $query->where('enabled', $request->enabled);
        }

        $rules = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $rules]);
    }

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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate conditions and actions
        $conditionsValidation = $this->ruleEngine->validateRuleConditions($request->conditions_json);
        $actionsValidation = $this->ruleEngine->validateRuleActions($request->actions_json);

        if (!$conditionsValidation['valid']) {
            return response()->json(['errors' => ['conditions' => $conditionsValidation['errors']]], 422);
        }

        if (!$actionsValidation['valid']) {
            return response()->json(['errors' => ['actions' => $actionsValidation['errors']]], 422);
        }

        $rule = Rule::create([
            'name' => $request->name,
            'scope' => $request->scope,
            'conditions_json' => $request->conditions_json,
            'actions_json' => $request->actions_json,
            'enabled' => $request->enabled ?? true,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['data' => $rule->load('creator')], 201);
    }

    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conditions' => 'required|array',
            'actions' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conditionsValidation = $this->ruleEngine->validateRuleConditions($request->conditions);
        $actionsValidation = $this->ruleEngine->validateRuleActions($request->actions);

        return response()->json([
            'valid' => $conditionsValidation['valid'] && $actionsValidation['valid'],
            'conditions' => $conditionsValidation,
            'actions' => $actionsValidation,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $rule = Rule::with('creator')->findOrFail($id);
        return response()->json(['data' => $rule]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'scope' => 'sometimes|in:global,org,term',
            'conditions_json' => 'sometimes|array',
            'actions_json' => 'sometimes|array',
            'enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate conditions and actions if provided
        if ($request->has('conditions_json')) {
            $conditionsValidation = $this->ruleEngine->validateRuleConditions($request->conditions_json);
            if (!$conditionsValidation['valid']) {
                return response()->json(['errors' => ['conditions' => $conditionsValidation['errors']]], 422);
            }
        }

        if ($request->has('actions_json')) {
            $actionsValidation = $this->ruleEngine->validateRuleActions($request->actions_json);
            if (!$actionsValidation['valid']) {
                return response()->json(['errors' => ['actions' => $actionsValidation['errors']]], 422);
            }
        }

        $rule->update($request->only(['name', 'scope', 'conditions_json', 'actions_json', 'enabled']));
        return response()->json(['data' => $rule->load('creator')]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $rule->delete();
        return response()->json(['message' => 'Rule deleted successfully']);
    }

    public function toggle(Request $request, $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        $rule->enabled = !$rule->enabled;
        $rule->save();
        return response()->json(['data' => $rule]);
    }

    public function test(Request $request, $id): JsonResponse
    {
        $rule = Rule::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'context' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $context = $request->context;
        $conditionsMet = $this->ruleEngine->validateConditions($rule->conditions_json ?? [], $context);
        
        return response()->json([
            'rule_id' => $rule->id,
            'conditions_met' => $conditionsMet,
            'would_execute' => $conditionsMet,
            'actions' => $conditionsMet ? ($rule->actions_json ?? []) : [],
        ]);
    }
}