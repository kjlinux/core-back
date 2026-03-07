<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with('company')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Identifiants incorrects', 401);
        }

        if (!$user->is_active) {
            return $this->errorResponse('Compte desactive', 403);
        }

        $accessToken = $user->createToken('access_token')->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['refresh'])->plainTextToken;

        return $this->successResponse([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Deconnexion reussie');
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');
        $user->currentAccessToken()->delete();

        $accessToken = $user->createToken('access_token')->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['refresh'])->plainTextToken;

        return $this->successResponse([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->resourceResponse(new UserResource($request->user()->load('company')));
    }
}
