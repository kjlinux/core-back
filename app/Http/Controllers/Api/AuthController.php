<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with('company')->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Identifiants incorrects', 401);
        }

        if (! $user->is_active) {
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
        $user = $request->user('sanctum');

        if ($user) {
            $user->currentAccessToken()->delete();
        }

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
        return $this->resourceResponse(new UserResource($request->user()->load('company', 'employee')));
    }

    /**
     * Permet au technicien de valider qu'une entreprise existe et retourne ses infos.
     * Le frontend stocke ensuite l'ID et l'envoie dans X-Active-Company-Id.
     */
    public function selectCompany(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTechnicien() && !$user->isSuperAdmin()) {
            return $this->errorResponse('Action reservee aux techniciens et super admins', 403);
        }

        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        $company = Company::findOrFail($request->input('company_id'));

        return $this->successResponse([
            'companyId'   => (string) $company->id,
            'companyName' => $company->name,
        ], 'Entreprise selectionnee');
    }
}
