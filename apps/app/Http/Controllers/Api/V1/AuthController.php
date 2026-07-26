<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'pin'      => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'pin'      => Hash::make($request->pin),
        ]);

        $token = $user->createToken(
            'auth-token',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken(
            'auth-token',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    public function validateToken(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->forceFill([
                'expires_at' => now()->addDays(30),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid. Expiration extended.',
            'user'    => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully. Token revoked.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user'    => $request->user(),
        ]);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user->pin || !Hash::check($request->pin, $user->pin)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided PIN is incorrect.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'PIN verified successfully.',
        ]);
    }
}