<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ControlPanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ControlPanelController extends Controller
{
    public function __construct(
        private ControlPanelService $panelService
    ) {}

    /**
     * GET /api/panels/curator
     */
    public function curator(): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав: куратор
        if (!$user->hasRole('куратор') && !$user->hasRole('curator')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->panelService->getCuratorPanel($user->id);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/panels/methodist
     */
    public function methodist(): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав: методист, admin
        if (!$user->hasRole('методист') && !$user->hasRole('methodist') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->panelService->getMethodistPanel($user->id);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/panels/principal
     */
    public function principal(): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав: руководство, admin
        if (!$user->hasRole('руководство') && !$user->hasRole('management') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->panelService->getPrincipalPanel($user->id);

        return response()->json(['data' => $data]);
    }
}
