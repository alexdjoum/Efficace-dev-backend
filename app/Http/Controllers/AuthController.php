<?php

namespace App\Http\Controllers;

use App\Models\OTP;
use App\Models\Lot;
use App\Models\User;
use App\Models\Contact;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Login;
use App\Services\CustomerService;
use App\Services\EmployeeService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\DB;
use App\Models\LocalisationWorker;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Storage;

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

    /**
 * @OA\Post(
 *     path="/api/loginWorker",
 *     summary="Connexion d'un travailleur",
 *     tags={"Authentification"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email", type="string", example="worker@example.com"),
 *             @OA\Property(property="password", type="string", example="password123")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Connexion réussie",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="token", type="string", example="4|rRyQ76Jaqy0..."),
 *         )
 *     ),
 *     @OA\Response(response=401, description="Non autorisé")
 * )
 */
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


    /**
     * @OA\Post(
     *     path="/api/registerWorker",
     *     summary="Inscription d'un travailleur ou d'une entreprise",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"email","password","password_confirmation","privacy_policy","phoneNumber","firstName","lastName","years_of_experience","is_enterprise"},
     *                 @OA\Property(property="email", type="string", format="email", example="worker@example.com", description="Email unique"),
     *                 @OA\Property(property="password", type="string", format="password", example="password123", description="Minimum 8 caractères"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *                 @OA\Property(property="privacy_policy", type="boolean", example=true, description="Acceptation obligatoire"),
     *                 @OA\Property(property="phoneNumber", type="string", example="+237698765432"),
     *                 @OA\Property(property="firstName", type="string", example="Jean"),
     *                 @OA\Property(property="lastName", type="string", example="Dupont"),
     *                 @OA\Property(property="localisation_worker_id", type="integer", nullable=true, example=1, description="ID de la localisation (ou utiliser localisation_name)"),
     *                 @OA\Property(property="localisation_name", type="string", nullable=true, example="Douala", description="Nom de la localisation (ou utiliser localisation_worker_id)"),
     *                 @OA\Property(property="years_of_experience", type="integer", example=5, description="Années d'expérience"),
     *                 @OA\Property(property="presentation", type="string", nullable=true, example="Architecte spécialisé en bâtiments résidentiels"),
     *                 @OA\Property(property="is_enterprise", type="boolean", example=false, description="false pour travailleur, true pour entreprise"),
     *                 @OA\Property(property="lot_id", type="integer", nullable=true, example=1, description="Requis si is_enterprise=false. Détermine le rôle: Site supervisor/Site manager/Technical director → manager, Architect/Engineer → corrector, autres → user"),
     *                 @OA\Property(
     *                     property="worker_ids[]",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={28,30,32},
     *                     description="Requis si is_enterprise=true. Exactement 3 IDs de travailleurs (non-entreprises)"
     *                 ),
     *                 @OA\Property(property="commercial_register", type="string", format="binary", description="PDF requis si is_enterprise=true"),
     *                 @OA\Property(property="immigration_certificate", type="string", format="binary", description="PDF requis si is_enterprise=true"),
     *                 @OA\Property(property="certificate_of_compliance", type="string", format="binary", description="PDF requis si is_enterprise=true"),
     *                 @OA\Property(property="approval", type="string", format="binary", nullable=true, description="PDF optionnel si is_enterprise=true"),
     *                 @OA\Property(property="patent", type="string", format="binary", nullable=true, description="PDF optionnel si is_enterprise=true")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inscription réussie"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=50),
     *                 @OA\Property(
     *                     property="contact",
     *                     type="object",
     *                     @OA\Property(property="firstName", type="string", example="Jean"),
     *                     @OA\Property(property="lastName", type="string", example="Dupont"),
     *                     @OA\Property(property="email", type="string", example="worker@example.com"),
     *                     @OA\Property(property="phoneNumber", type="string", example="+237698765432"),
     *                     @OA\Property(
     *                         property="localisation_worker",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Douala")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="account_type",
     *                     type="object",
     *                     @OA\Property(property="lot_id", type="integer", example=1),
     *                     @OA\Property(property="is_enterprise", type="boolean", example=false),
     *                     @OA\Property(property="years_of_experience", type="integer", example=5),
     *                     @OA\Property(
     *                         property="lot",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="architect")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="roles",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="name", type="string", example="corrector")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="worker_enterprises",
     *                     type="array",
     *                     description="Présent uniquement si is_enterprise=true",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="worker",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=28),
     *                             @OA\Property(
     *                                 property="contact",
     *                                 type="object",
     *                                 @OA\Property(property="firstName", type="string", example="Pierre"),
     *                                 @OA\Property(property="lastName", type="string", example="Martin")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Les travailleurs sélectionnés ne doivent pas être des entreprises")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur lors de l'inscription",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de l'inscription")
     *         )
     *     )
     * )
     */
    public function registerWorker(Request $request)
    {
        $rules = [
            'email' => 'required|string|email|max:255|unique:contacts,email',
            'password' => 'required|string|min:8|confirmed',
            'privacy_policy' => 'required|boolean|accepted',
            
            'phoneNumber' => 'required|string|max:20',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'localisation_worker_id' => 'nullable|exists:localisation_workers,id',
            'localisation_name' => 'nullable|string|max:255',
            
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
            $rules['worker_ids'] = 'required|array|size:3';
            $rules['worker_ids.*'] = 'required|exists:users,id';
        }

        $validated = $request->validate($rules);

        if ($validated['is_enterprise']) {
            $enterpriseWorkers = User::whereIn('id', $validated['worker_ids'])
                ->whereHas('accountType', function ($query) {
                    $query->where('is_enterprise', true);
                })
                ->count();

            if ($enterpriseWorkers > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les travailleurs sélectionnés ne doivent pas être des entreprises',
                ], 422);
            }
        }

        $localisationWorkerId = null;
        if (!empty($validated['localisation_worker_id'])) {
            $localisationWorkerId = $validated['localisation_worker_id'];
        } elseif (!empty($validated['localisation_name'])) {
            $localisation = LocalisationWorker::firstOrCreate(
                ['name' => $validated['localisation_name']]
            );
            $localisationWorkerId = $localisation->id;
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'password' => Hash::make($validated['password']),
                'privacy_policy' => $validated['privacy_policy'],
            ]);

            if (!empty($validated['lot_id'])) {
                $lot = Lot::with('parent')->find($validated['lot_id']);
                
                if ($lot) {
                    $lotName = strtolower(trim($lot->name));
                    $parentLotName = $lot->parent ? strtolower(trim($lot->parent->name)) : null;
                    
                    if (in_array($lotName, ['site supervisor', 'site manager', 'technical director']) ||
                        in_array($parentLotName, ['site supervisor', 'site manager', 'technical director'])) {
                        $user->assignRole('manager');
                    }
                    elseif (in_array($lotName, ['architect', 'engineer']) ||
                            in_array($parentLotName, ['architect', 'engineer'])) {
                        $user->assignRole('corrector');
                    }
                    else {
                        $user->assignRole('user');
                    }
                } else {
                    $user->assignRole('user');
                }
            } else {
                $user->assignRole('user');
            }

            $user->contact()->create([
                'phoneNumber' => $validated['phoneNumber'],
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
                'localisation_worker_id' => $localisationWorkerId,
            ]);

            $user->accountType()->create([
                'lot_id' => $validated['lot_id'] ?? null, 
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

                foreach ($validated['worker_ids'] as $workerId) {
                    WorkerEnterprise::create([
                        'enterprise_user_id' => $user->id,
                        'worker_user_id' => $workerId,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('messages.register_success'),
                'user' => $user->load(
                    'contact',
                    'accountType.lot',
                    'contact.localisationWorker',
                    'enterpriseDocument',
                    'workerEnterprises.worker.contact',
                    'roles'
                )
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($documents)) {
                foreach ($documents as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription : ' . $e->getMessage(),
            ], 500);
        }
    }

    
    public function loginWorker(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $contact = \App\Models\Contact::where('email', $validated['email'])->first();

        if ($contact) {
            $user = $contact->user;
        } else {
            $user = \App\Models\User::where('email', $validated['email'])->first();
        }

        if (!$user || !\Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('contact', 'accountType.lot.parent', 'enterpriseDocument');

        $childLotName = $user->accountType?->lot?->name;
        $parentLotName = $user->accountType?->lot?->parent?->name;

        return response()->json([
            'success' => true,
            'message' => __('messages.login_success'),
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'child_lot_name' => $childLotName,
                'parent_lot_name' => $parentLotName,
            ]
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/admin/create-user",
     *     summary="Créer un utilisateur admin ou validator (admin seulement)",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","password_confirmation","firstName","lastName","role"},
     *             @OA\Property(property="email", type="string", format="email", example="validator@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="firstName", type="string", example="Marie"),
     *             @OA\Property(property="lastName", type="string", example="Validator"),
     *             @OA\Property(property="phoneNumber", type="string", example="+237698765432"),
     *             @OA\Property(property="role", type="string", enum={"admin", "validator"}, example="validator")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Utilisateur créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Utilisateur créé avec succès"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=100),
     *                 @OA\Property(property="email", type="string", example="validator@example.com"),
     *                 @OA\Property(property="role", type="string", example="validator")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé (seul admin peut créer)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Action non autorisée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="L'email existe déjà")
     *         )
     *     )
     * )
     */
    public function createAdminUser(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'phoneNumber' => 'required|string|max:20',
            'role' => 'required|in:admin,validator',
        ]);

        try {
            DB::beginTransaction();

            $newUser = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'privacy_policy' => true,
            ]);

            $newUser->assignRole($validated['role']);

            $newUser->contact()->create([
                'phoneNumber' => $validated['phoneNumber'],
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'user' => [
                    'id' => $newUser->id,
                    'email' => $newUser->email,
                    'firstName' => $validated['firstName'],
                    'lastName' => $validated['lastName'],
                    'phoneNumber' => $validated['phoneNumber'],
                    'role' => $validated['role'],
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création : ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/admin/users",
     *     summary="Lister tous les administrateurs et validators",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des admins et validators",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="email", type="string", example="admin@example.com"),
     *                     @OA\Property(property="firstName", type="string", example="John"),
     *                     @OA\Property(property="lastName", type="string", example="Doe"),
     *                     @OA\Property(property="phoneNumber", type="string", example="+237698765432"),
     *                     @OA\Property(property="role", type="string", example="admin")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Action non autorisée")
     *         )
     *     )
     * )
     */ 
    public function listAdminUsers()
    {
        $user = auth()->user();

        // ✅ Vérifier que l'utilisateur connecté est admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'validator']);
            })
            ->with('contact')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'firstName' => $user->contact?->firstName,
                    'lastName' => $user->contact?->lastName,
                    'phoneNumber' => $user->contact?->phoneNumber,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                ];
            })
        ]);
    }


    /**
     * @OA\Patch(
     *     path="/api/admin/users/{id}",
     *     summary="Modifier un utilisateur admin ou validator",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", format="email", example="validator@example.com"),
     *             @OA\Property(property="password", type="string", format="password", nullable=true, example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", nullable=true, example="newpassword123"),
     *             @OA\Property(property="firstName", type="string", example="Marie"),
     *             @OA\Property(property="lastName", type="string", example="Validator"),
     *             @OA\Property(property="phoneNumber", type="string", example="+237698765432"),
     *             @OA\Property(property="role", type="string", enum={"admin", "validator"}, example="validator")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur modifié avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Utilisateur modifié avec succès"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=100),
     *                 @OA\Property(property="email", type="string", example="validator@example.com"),
     *                 @OA\Property(property="role", type="string", example="validator")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Action non autorisée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     *         )
     *     )
     * )
     */
    public function updateAdminUser(Request $request, $id)
    {
        $user = auth()->user();

        // ✅ Vérifier que l'utilisateur connecté est admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $targetUser = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'validator']);
            })
            ->with('contact')
            ->find($id);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé',
            ], 404);
        }

        $rules = [
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'phoneNumber' => 'sometimes|string|max:20',
            'role' => 'sometimes|in:admin,validator',
        ];

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            // Mettre à jour l'utilisateur
            if (isset($validated['email'])) {
                $targetUser->email = $validated['email'];
            }

            if (isset($validated['password'])) {
                $targetUser->password = Hash::make($validated['password']);
            }

            $targetUser->save();

            // Mettre à jour le contact
            if ($targetUser->contact) {
                $contactData = [];
                
                if (isset($validated['firstName'])) {
                    $contactData['firstName'] = $validated['firstName'];
                }
                if (isset($validated['lastName'])) {
                    $contactData['lastName'] = $validated['lastName'];
                }
                if (isset($validated['phoneNumber'])) {
                    $contactData['phoneNumber'] = $validated['phoneNumber'];
                }
                if (isset($validated['email'])) {
                    $contactData['email'] = $validated['email'];
                }

                if (!empty($contactData)) {
                    $targetUser->contact->update($contactData);
                }
            }

            // Mettre à jour le rôle
            if (isset($validated['role'])) {
                $targetUser->syncRoles([$validated['role']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur modifié avec succès',
                'user' => [
                    'id' => $targetUser->id,
                    'email' => $targetUser->email,
                    'firstName' => $targetUser->contact?->firstName,
                    'lastName' => $targetUser->contact?->lastName,
                    'phoneNumber' => $targetUser->contact?->phoneNumber,
                    'role' => $targetUser->role,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification : ' . $e->getMessage(),
            ], 500);
        }
    }
}
