<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Resources\User\UserResource;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Response;
use App\Models\User;

class LoginController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error(
                'Invalid Credentials',
                Response::HTTP_UNAUTHORIZED
            );
        }
        $token = $user->createToken('auth_token')->accessToken;

        return ApiResponse::success(
           [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'Login Successfull'
        );
    }
}
