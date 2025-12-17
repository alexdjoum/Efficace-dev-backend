<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OTP;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\CustomerService;
use App\Services\EmployeeService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

class AuthController extends Controller
{
    // authenticate the user
    public function login(Request $request)
{
    // Forcer le parsing du JSON si nécessaire
    $data = $request->all();
    if (empty($data) && $request->getContent()) {
        $data = json_decode($request->getContent(), true);
    }
    
    \Log::info('Parsed data: ' . json_encode($data));
    
    $validator = validator()->make($data, [
        'email' => 'required|email|exists:users',
        'password' => 'required',
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreurs de validation.',
            'data' => ['errors' => $validator->errors()]
        ], 422);
    }
    
    $user = User::where('email', $data['email'])->first();
    
    if (!$user || !Hash::check($data['password'], $user->password)) {
        if ($user) {
            event(new Failed("api", $user, $data));
        }
        return response()->json([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect.',
            'data' => null
        ], 401);
    }
    
    event(new Login("api", $user, false));
    
    return response()->json([
        'success' => true,
        'message' => 'Utilisateur connecté avec succès.',
        'data' => [
            'token' => $user->createToken("token")->plainTextToken,
            
        ]
        
        // 'data' => [
        //     'token' => $user->createToken("token")->plainTextToken,
        //     'auth' => $user->userable,
        // ]
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $customer = DB::transaction(function () use ($request, $customerService) {
            $customer =  $customerService->create($request->all());
            OTP::query()->create(["email" => $customer->user->email]);
            return $customer;
        });


        // send mail to activate account

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
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => [
                    'errors' => $validator->errors(),
                ]
            ],422);
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
                'auth' => $request->user()?->userable,
            ]
        ]);
    }

    // update customer profile

    public function updateProfile(Request $request, CustomerService $customerService, EmployeeService $employeeService)
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
            'profile' => 'image|mimes:jpeg,png,jpg,gif,svg|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $service = ($user->userable instanceof Employee) ? $employeeService : $customerService;

        $auth = DB::transaction(function () use ($user, $request, $service) {
            return $service->update($user->userable, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'data' => $auth
        ]);
    }

    public function logs(Request $request)
    {
        if ($request->name) {
            $logs = Activity::query()->where('log_name', $request->name)->get();
        } else {
            $logs = Activity::all();
        }
        return response()->json([
            'success' => true,
            'message' => 'Logs',
            'data' => $logs
        ]);
    }

    public function getResetCode(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'email' => 'required|email|exists:users'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        OTP::query()->whereEmail($request->email)->delete();

        $otp = OTP::query()->create($request->all());

        // send email for notify user

        return response()->json([
            'success' => true,
            'message' => 'Un code de vérification vous a été envoyé par mail',
            'data' => null
        ]);
    }

    public function verifyResetCode(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'email' => 'required|email|exists:users',
            'code' => 'required|numeric|exists:otps'
        ], [
            'code.exists' => 'Ce code est incorrect',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $otp = OTP::query()->whereCode($request->code)->whereEmail($request->email)->first();

        if ($otp->isExpire()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce code vérification a expiré',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ce code de vérification est valide',
            'data' => null,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'code' => 'required|numeric|exists:otps',
            'password' => 'required|min:6|confirmed'
        ], [
            'code.exists' => 'Ce code est incorrect',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $otp = OTP::query()->whereCode($request->code)->first();

        $user = User::query()->whereEmail($otp->email)->first();

        $user->update($request->only('password'));

        $otp->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès',
            'data' => null
        ]);
    }

    public function activateAccount(Request $request)
    {
        $validator = validator()->make($request->all(), [
            // 'email' => 'required|email|exists:users',
            'code' => 'required|numeric|exists:otps'
        ], [
            'code.exists' => 'Ce code est incorrect',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // dd($request);
        $otp = OTP::query()->whereCode($request->code)->whereEmail($request->user()->email)->first();

        if ($otp->isExpire()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce code vérification a expiré',
                'data' => null
            ]);
        }

        // $user = User::query()->whereEmail($request->email)->first();

        $request->user()->update([
            'email_verified_at' => now()
        ]);

        $otp->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur activé avec succès.',
            'data' => null,
        ]);
    }

    public function resendCode(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'email' => 'required|email|exists:users',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        OTP::query()->whereEmail($request->email)->delete();
        OTP::query()->create(['email' => $request->email]);

        return response()->json([
            'success' => true,
            'message' => 'Code de validation envoyé avec succès à ' . $request->email,
            'data' => null
        ]);
    }
}
