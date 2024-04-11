<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // authenticate the user
    public function login(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // check if the user exists

        if (!$user = User::query()->where('email', $request->email)->first()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
                'data' => null
            ], 404);
        }

        $credentials = $request->only('email', 'password');

        // check if the credentials are correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect.',
                'data' => null
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur connecté avec succès.',
            'data' => [
                'token' => $user->createToken("token")->plainTextToken,
                'auth' => $user->userable,
            ]
        ]);
    }

    // register customer 

    public function register(Request $request, CustomerService $customerService)
    {
        $validator = validator()->make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'required|string',
            'country' => 'required|string',
            'city' => 'required|string',
            'street' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $customer = DB::transaction(function () use ($request, $customerService) {
            return $customerService->create($request->all());
        });

        $customer->refresh();


        return response()->json([
            'success' => true,
            'message' => 'Client enregistré avec succès.',
            'data' => $customer
        ], 201);
    }

    // Revoke the current token
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur déconnecté avec succès.',
            'data' => null,
        ]);
    }

    // logout from all device
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur déconnecté avec succès de tous les appareils.',
            'data' => null
        ]);
    }

    // change user password
    public function changePassword(Request $request)
    {

        $validator = validator()->make($request->all(), [
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => [
                    'errors' => ['errors' => $validator->errors()],
                ]
            ]);
        }

        $request->user()->update($request->only(['password']));

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès.',
            'data' => null
        ]);
    }

    // get the current user
    public function current(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Utilisateur connecté.',
            'data' => [
                'auth' => $request->user()->userable,
            ]
        ]);
    }

    // update customer profile

    public function updateProfile(Request $request, CustomerService $customerService)
    {
        $user = $request->user();

        $validator = validator()->make($request->all(), [
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'street' => 'sometimes|required|string',
            'profile' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $customer = DB::transaction(function () use ($user, $request, $customerService) {
            return $customerService->update($user, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'data' => $customer
        ]);
    }

    
}
