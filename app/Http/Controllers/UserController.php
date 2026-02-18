<?php

namespace App\Http\Controllers;

use App\Models\OTP;
use App\Models\User;
use App\Models\JobWorker;
use Illuminate\Http\Request;
use App\Services\CustomerService;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{

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

    public function listWorkers()
    {
        $workers = User::whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'validator']);
            })
            ->with([
                'contact.localisationWorker',
                'accountType.lot',
                'jobWorkers',
            ])
            ->get()
            ->map(function ($worker) {

                $notes = $worker->jobWorkers
                    ->whereNotNull('note')
                    ->pluck('note')
                    ->map(fn($note) => is_numeric($note) ? (float) $note : null)
                    ->filter()
                    ->values();

                $average = $notes->isNotEmpty()
                    ? round($notes->avg(), 2)
                    : null;

                return [
                    'id' => $worker->id,
                    'firstName' => $worker->contact?->firstName,
                    'lastName' => $worker->contact?->lastName,
                    'email' => $worker->contact?->email,
                    'phone' => $worker->contact?->phoneNumber,
                    'localisation' => $worker->contact?->localisationWorker?->name,
                    'worker' => $worker->accountType?->worker,
                    'lot' => $worker->accountType?->lot?->name,
                    'years_of_experience' => $worker->accountType?->years_of_experience,
                    'total_jobs' => $worker->jobWorkers->count(),
                    'average_note' => $average,
                ];
            })
            ->sortBy([
                fn($a, $b) => match(true) {
                    $a['average_note'] !== null && $b['average_note'] !== null 
                        => $b['average_note'] <=> $a['average_note'], 
                    $a['average_note'] !== null 
                        => -1, 
                    $b['average_note'] !== null 
                        => 1,  
                    default 
                        => strcmp($a['firstName'], $b['firstName']), 
                }
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $workers
        ]);
    }
}