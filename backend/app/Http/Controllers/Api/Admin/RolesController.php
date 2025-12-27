<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RolesController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->orderBy('name')->get();

        return response()->json($roles);
    }
}









