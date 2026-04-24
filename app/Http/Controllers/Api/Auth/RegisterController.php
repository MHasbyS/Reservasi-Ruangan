<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\RegisterResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'karyawan',
        ]);

        $user->assignRole('karyawan');

        return response()->json([
            'message' => 'Registrasi Berhasil',
            // 'data' => $user,
            'data' => new RegisterResource(['user' => $user]),
        ]);
    }
}
