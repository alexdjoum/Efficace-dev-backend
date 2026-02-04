<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OTP;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Login;
use App\Services\CustomerService;
use App\Services\EmployeeService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use Spatie\Activitylog\Models\Activity;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except([
            'login', 
            'register', 
            'resendCode', 
            'loginWorker',
            'getResetCode', 
            'verifyResetCode', 
            'resetPassword',
            'registerWorker'
        ]);
    }
    public function register(Request $request, CustomerService $customerService)
    {
        $validator = validator()->make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|unique:customers,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $customer = DB::transaction(function () use ($request, $customerService) {
            $customer = $customerService->create($request->all());
            $customer->user->email_verified_at = now();
            $clientRole = \App\Models\Role::where('name', 'Client')
                ->orWhere('slug', 'client')
                ->first();
            
            if (!$clientRole) {
                throw new \Exception("Le rôle 'Client' n'existe pas dans la base de données.");
            }
            
            DB::table('user_role')->insert([
                'user_id' => $customer->user->id,
                'role_id' => $clientRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            OTP::query()->create(["email" => $customer->user->email]);
            
            $customer->load('user.roles');
            
            return $customer;
        });

        return response()->json([
            'success' => true,
            'message' => 'Client enregistré avec succès.',
            'data' => $customer
        ], 201);
    }

    // authenticate the user
    public function login(Request $request)
    {
        $data = $request->all();
        if (empty($data) && $request->getContent()) {
            $data = json_decode($request->getContent(), true);
        }
        
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
                try {
                    event(new Failed("api", $user, $data));
                } catch (\Exception $e) {
                    \Log::warning('Failed to send login failure notification: ' . $e->getMessage());
                }
            }
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_credentials'),
                'data' => null
            ], 401);
        }
        
        try {
            event(new Login("api", $user, false));
        } catch (\Exception $e) {
            \Log::warning('Failed to send login notification: ' . $e->getMessage());
        }
        
        $user->load('roles', 'userable');
        
        return response()->json([
            'success' => true,
            'message' => __('messages.login_success'),
            'data' => [
                'email_verified_at' => $user->email_verified_at,
                'token' => $user->createToken("token")->plainTextToken,
                'user' => new UserResource($user),
            ]
        ]);
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

    public function registerWorker(Request $request)
    {
        $rules = [
            'email' => 'required|string|email|max:255|unique:contacts,email',
            'password' => 'required|string|min:8|confirmed',
            'privacy_policy' => 'required|boolean|accepted',
            
            'phoneNumber' => 'required|string|max:20',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'worker' => 'required|in:architect,technical_director,site_supervisor,site_manager,engineer',
            'years_of_experience' => 'required|integer|min:0',
            'presentation' => 'nullable|string|max:5000',
            
            'is_enterprise' => 'required|boolean',
        ];

        if ($request->input('is_enterprise') == false || $request->input('is_enterprise') == '0') {
            $rules['lot_id'] = 'required|exists:lots,id';
        } else {
            $rules['lot_id'] = 'nullable|exists:lots,id';
        }

        if ($request->input('is_enterprise') == true || $request->input('is_enterprise') == '1') {
            $rules['commercial_register'] = 'required|file|mimes:pdf|max:10240';
            $rules['immigration_certificate'] = 'required|file|mimes:pdf|max:10240';
            $rules['certificate_of_compliance'] = 'required|file|mimes:pdf|max:10240';
            $rules['approval'] = 'nullable|file|mimes:pdf|max:10240';
            $rules['patent'] = 'nullable|file|mimes:pdf|max:10240';
        }

        $validated = $request->validate($rules);

        $user = User::create([
            'password' => Hash::make($validated['password']),
            'privacy_policy' => $validated['privacy_policy'],
        ]);

        $user->assignRole('user');

        $user->contact()->create([
            'phoneNumber' => $validated['phoneNumber'],
            'firstName' => $validated['firstName'],
            'lastName' => $validated['lastName'],
            'email' => $validated['email'],
        ]);

        $user->accountType()->create([
            'lot_id' => $validated['lot_id'] ?? null, 
            'worker' => $validated['worker'],
            'is_enterprise' => $validated['is_enterprise'],
            'years_of_experience' => $validated['years_of_experience'],
            'presentation' => $validated['presentation'] ?? null,
        ]);

        if ($validated['is_enterprise']) {
            $documents = [];

            if ($request->hasFile('commercial_register')) {
                $documents['commercial_register'] = $request->file('commercial_register')
                    ->store('enterprise_documents/commercial_registers', 'public');
            }

            if ($request->hasFile('immigration_certificate')) {
                $documents['immigration_certificate'] = $request->file('immigration_certificate')
                    ->store('enterprise_documents/immigration_certificates', 'public');
            }

            if ($request->hasFile('certificate_of_compliance')) {
                $documents['certificate_of_compliance'] = $request->file('certificate_of_compliance')
                    ->store('enterprise_documents/certificates_of_compliance', 'public');
            }

            if ($request->hasFile('approval')) {
                $documents['approval'] = $request->file('approval')
                    ->store('enterprise_documents/approvals', 'public');
            }

            if ($request->hasFile('patent')) {
                $documents['patent'] = $request->file('patent')
                    ->store('enterprise_documents/patents', 'public');
            }

            $user->enterpriseDocument()->create($documents);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.register_success'),
            'user' => $user->load('contact', 'accountType.lot', 'enterpriseDocument', 'roles')
        ], 201);
    }

    public function loginWorker(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $contact = \App\Models\Contact::where('email', $validated['email'])->first();

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        $user = $contact->user;

        if (!$user || !\Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        if (!$user->accountType) {
            return response()->json([
                'success' => false,
                'message' => __('messages.not_a_worker_account'),
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('messages.login_success'),
            'token' => $token,
            'user' => $user->load('contact', 'accountType', 'roles')
        ], 200);
    }
}
