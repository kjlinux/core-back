<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', PasswordRule::min(8), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->update(['password' => Hash::make($password)]);
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(null, 'Mot de passe reinitialise avec succes. Vous pouvez maintenant vous connecter.');
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'Lien de reinitialisation invalide ou expire.',
            Password::INVALID_USER  => 'Aucun compte trouve avec cet email.',
            default                 => 'Impossible de reinitialiser le mot de passe.',
        };

        return $this->errorResponse($message, 422);
    }
}
