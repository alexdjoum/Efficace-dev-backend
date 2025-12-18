<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CustomerService;
use Illuminate\Support\Facades\DB;
use App\Models\OTP;

class UserController extends Controller
{
    /**
     * Créer un utilisateur avec un rôle spécifique (Admin uniquement)
     */
    public function createUser(Request $request, CustomerService $customerService)
    {
        $validator = validator()->make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'required|string|unique:customers,phone',
            'address' => 'nullable|string',
            'role' => 'required|in:customer,corrector,manager,validator,admin', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        try {
            $customer = DB::transaction(function () use ($request, $customerService) {
                $customer = $customerService->create($request->all());
                
                // ✅ L'admin peut choisir n'importe quel rôle
                $role = $request->input('role');
                $customer->user->assignRole($role);
                
                OTP::query()->create(["email" => $customer->user->email]);
                $customer->load('user.roles');
                
                return $customer;
            });

            $roleName = $customer->user->roles->first()?->name ?? 'Client';

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès par l\'administrateur.',
                'data' => [
                    'customer' => $customer,
                    'role' => $roleName,
                    'created_by' => auth()->user()->email
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de l\'utilisateur.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}