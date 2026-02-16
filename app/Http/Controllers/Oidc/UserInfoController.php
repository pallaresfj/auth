<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('api');

        abort_unless($user, 401, 'Unauthorized');
        abort_if(! $user->is_active, 403, 'User is inactive');

        return response()->json([
            'sub' => (string) $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
        ]);
    }
}
