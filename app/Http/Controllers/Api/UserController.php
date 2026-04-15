<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Mail\UserCreatedMail;
use App\Mail\UserUpdatedMail;
use App\Mail\UserPasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends BaseApiController
{
    /**
     * Get paginated list of users.
     * super_admin sees all users; admin_enterprise sees only users from their company.
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $query = User::with('company');

        // admin_enterprise : uniquement les utilisateurs de sa propre entreprise
        if ($authUser->isAdminEnterprise()) {
            $query->where('company_id', $authUser->company_id);
        }

        // technicien : voit les users de l'entreprise active + les autres techniciens (sans company)
        // mais jamais les super_admin
        if ($authUser->isTechnicien()) {
            $activeCompanyId = $this->resolveActiveCompanyId();
            $query->where('role', '!=', 'super_admin')
                  ->where(function ($q) use ($activeCompanyId) {
                      // Users directement liés à l'entreprise active
                      $q->where('company_id', $activeCompanyId)
                        // OU techniciens sans entreprise fixe
                        ->orWhere(function ($q2) {
                            $q2->where('role', 'technicien')
                               ->whereNull('company_id');
                        });
                  });
        }

        // Filtre company_id additionnel (super_admin uniquement)
        if ($authUser->isSuperAdmin()) {
            $query->when($request->input('company_id'), function ($q, $companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $query->when($request->input('role'), function ($q, $role) {
            $q->where('role', $role);
        });

        $query->when($request->has('is_active'), function ($q) use ($request) {
            $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        });

        $perPage = $request->input('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse(UserResource::collection($users));
    }

    /**
     * Get a single user.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();
        $user = User::with('company')->findOrFail($id);

        // admin_enterprise can only see users from their company
        if ($authUser->isAdminEnterprise() && $user->company_id !== $authUser->company_id) {
            return $this->errorResponse('Acces non autorise', 403);
        }

        return $this->resourceResponse(new UserResource($user));
    }

    /**
     * Create a new user.
     * super_admin can create any role; admin_enterprise and technicien can only create managers in their company.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $data = $request->validated();

        // admin_enterprise and technicien can only create managers in their active company
        if ($authUser->isAdminEnterprise() || $authUser->isTechnicien()) {
            if ($data['role'] !== 'manager') {
                return $this->errorResponse('Vous ne pouvez creer que des managers', 403);
            }
            $data['company_id'] = $authUser->isTechnicien()
                ? $this->resolveActiveCompanyId()
                : $authUser->company_id;
        }

        $plainPassword = $data['password'];
        $data['name'] = $data['first_name'] . ' ' . $data['last_name'];

        $user = User::create($data);
        $user->load('company');

        try {
            Mail::to($user->email)->send(new UserCreatedMail($user, $plainPassword));
        } catch (\Exception $e) {
            \Log::error('UserCreatedMail failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        return $this->resourceResponse(new UserResource($user), 'Utilisateur cree avec succes', 201);
    }

    /**
     * Update an existing user.
     * admin_enterprise and technicien can only edit managers in their company and cannot promote them.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);
        $data = $request->validated();

        if ($authUser->isAdminEnterprise() || $authUser->isTechnicien()) {
            $scopedCompanyId = $authUser->isTechnicien()
                ? $this->resolveActiveCompanyId()
                : $authUser->company_id;
            // Cannot edit users outside their company
            if ($user->company_id !== $scopedCompanyId) {
                return $this->errorResponse('Acces non autorise', 403);
            }
            // Cannot edit non-manager users (e.g. another admin_enterprise)
            if ($user->role !== 'manager') {
                return $this->errorResponse('Vous ne pouvez modifier que les managers', 403);
            }
            // Cannot promote a manager to a higher role
            if (isset($data['role']) && $data['role'] !== 'manager') {
                return $this->errorResponse('Vous ne pouvez pas changer le role', 403);
            }
            $data['company_id'] = $scopedCompanyId;
        }

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $data['name'] = ($data['first_name'] ?? $user->first_name) . ' ' . ($data['last_name'] ?? $user->last_name);
        }

        $user->update($data);
        $user->load('company');

        Mail::to($user->email)->send(new UserUpdatedMail($user));

        return $this->resourceResponse(new UserResource($user), 'Utilisateur mis a jour');
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        if ($authUser->isAdminEnterprise() || $authUser->isTechnicien()) {
            $scopedCompanyId = $authUser->isTechnicien()
                ? $this->resolveActiveCompanyId()
                : $authUser->company_id;
            if ($user->company_id !== $scopedCompanyId || $user->role !== 'manager') {
                return $this->errorResponse('Acces non autorise', 403);
            }
        }

        // Cannot deactivate yourself
        if ($user->id === $authUser->id) {
            return $this->errorResponse('Vous ne pouvez pas desactiver votre propre compte', 400);
        }

        $user->update(['is_active' => !$user->is_active]);
        $user->load('company');

        return $this->resourceResponse(new UserResource($user), 'Statut de l\'utilisateur mis a jour');
    }

    /**
     * Send password reset email.
     */
    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        if ($authUser->isAdminEnterprise() || $authUser->isTechnicien()) {
            $scopedCompanyId = $authUser->isTechnicien()
                ? $this->resolveActiveCompanyId()
                : $authUser->company_id;
            if ($user->company_id !== $scopedCompanyId || $user->role !== 'manager') {
                return $this->errorResponse('Acces non autorise', 403);
            }
        }

        $tempPassword = Str::random(12);
        $user->update(['password' => Hash::make($tempPassword)]);

        Mail::to($user->email)->send(new UserPasswordResetMail($user, $tempPassword));

        return $this->successResponse(null, 'Mot de passe reinitialise avec succes');
    }

    /**
     * Delete a user (super_admin only).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Cannot delete yourself
        if ($user->id === $request->user()->id) {
            return $this->errorResponse('Vous ne pouvez pas supprimer votre propre compte', 400);
        }

        $user->delete();

        return $this->noContentResponse();
    }
}
