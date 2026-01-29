<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Login user (session-based for SPA)
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emailOrPhone' => 'required|string',
            'password' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Support both email and phone
        $emailOrPhone = $request->emailOrPhone;
        $user = User::where('email', $emailOrPhone)
            ->orWhere('phone', $emailOrPhone)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active'
            ], 403);
        }

        $twoFactor = app(TwoFactorService::class);
        if ($twoFactor->is2FAEnabled($user)) {
            $tempToken = $twoFactor->createTempToken($user);
            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'temp_token' => $tempToken,
                'expires_in' => 300,
            ]);
        }

        // Generate JWT token for API authentication
        $token = JWTAuth::fromUser($user);

        // Get user roles and permissions
        $roles = $user->roles()->get()->map(function ($role) {
            return ['id' => $role->id, 'name' => $role->name];
        });
        
        $permissions = $user->permissions()->get()->map(function ($permission) {
            return ['id' => $permission->id, 'code' => $permission->code, 'description' => $permission->description];
        });

        // Generate refresh token (using JWT with longer TTL)
        $refreshToken = JWTAuth::customClaims(['type' => 'refresh'])->fromUser($user);

        // Return format compatible with frontend LoginResponseDTO
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60, // Convert minutes to seconds
            'user' => [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'status' => $user->status,
                'tenant_id' => $user->tenant_id,
                'roles' => $roles->toArray(),
                'permissions' => $permissions->toArray(),
            ]
        ]);
    }

    /**
     * Get authenticated user (alias for me method)
     */
    public function user(Request $request): JsonResponse
    {
        return $this->me($request);
    }

    /**
     * Get authenticated user info (me endpoint)
     */
    public function me(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Get user roles and permissions
        $roles = $user->roles()->get()->map(function ($role) {
            return ['id' => $role->id, 'name' => $role->name];
        });
        
        $permissions = $user->permissions()->get()->map(function ($permission) {
            return ['id' => $permission->id, 'code' => $permission->code, 'description' => $permission->description];
        });

        // Return format compatible with frontend MeDTO
        return response()->json([
            'user' => [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'tenant_id' => $user->tenant_id,
            ],
            'roles' => $roles->toArray(),
            'permissions' => $permissions->toArray(),
        ]);
    }

    /**
     * Logout user (invalidate JWT token)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout'
            ], 500);
        }
    }

    /**
     * Get CSRF token for SPA
     */
    public function csrf(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'csrf_token' => csrf_token()
        ]);
    }

    /**
     * Simple web login for form-based authentication
     */
    public function webLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors())->withInput();
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['email' => 'Invalid credentials'])->withInput();
        }

        if ($user->status !== 'active') {
            return back()->withErrors(['email' => 'Account is not active'])->withInput();
        }

        // Login the user using Laravel's built-in auth
        auth()->login($user);

        return redirect()->intended('/dashboard');
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            // Get token from request (can be from Authorization header or request body)
            $token = $request->bearerToken() ?? $request->input('refreshToken');
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            // Refresh the token
            $newToken = JWTAuth::refresh($token);
            
            // Get user from the new token
            $user = JWTAuth::setToken($newToken)->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Generate new refresh token
            $refreshToken = JWTAuth::customClaims(['type' => 'refresh'])->fromUser($user);

            return response()->json([
                'access_token' => $newToken,
                'refresh_token' => $refreshToken,
                'expires_in' => (int) config('jwt.ttl') * 60,
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token: ' . $e->getMessage()
            ], 401);
        }
    }

    /**
     * Verify 2FA code (after login when requires_2fa). Uses temp_token from login response.
     */
    public function verify2FA(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:6',
            'temp_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $twoFactor = app(TwoFactorService::class);
        $user = $twoFactor->getUserFromTempToken($request->temp_token);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired temp token'
            ], 401);
        }

        if (!$twoFactor->verifyWithRecovery($user, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid code'
            ], 401);
        }

        $twoFactor->deleteTempToken($request->temp_token);

        $token = JWTAuth::fromUser($user);
        $refreshToken = JWTAuth::customClaims(['type' => 'refresh'])->fromUser($user);

        $roles = $user->roles()->get()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
        $permissions = $user->permissions()->get()->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'description' => $p->description]);

        return response()->json([
            'success' => true,
            'message' => '2FA verified successfully',
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'status' => $user->status,
                'tenant_id' => $user->tenant_id,
                'roles' => $roles->toArray(),
                'permissions' => $permissions->toArray(),
            ]
        ]);
    }
}