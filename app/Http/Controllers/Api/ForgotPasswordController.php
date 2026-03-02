<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return success even if user not found to prevent email enumeration
            return $this->successResponse(null, 'Si cet email existe, un lien de reinitialisation a ete envoye.');
        }

        $status = Password::sendResetLink(['email' => $request->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return $this->successResponse(null, 'Si cet email existe, un lien de reinitialisation a ete envoye.');
        }

        return $this->errorResponse('Impossible d\'envoyer le lien de reinitialisation.', 500);
    }
}
