<?php

namespace App\Http\Controllers;

use App\Models\OTP;
use App\Models\Lot;
use App\Models\User;
use App\Models\Contact;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\AccountTypeLot;
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
     *     path="/api/auth/register/worker",
     *     summary="Inscription d'un travailleur ou d'un engin",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"email","password","password_confirmation","privacy_policy","phoneNumber","years_of_experience","is_enterprise"},
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     example="worker@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="privacy_policy",
     *                     type="boolean",
     *                     example=true
     *                 ),
     *                 @OA\Property(
     *                     property="phoneNumber",
     *                     type="string",
     *                     example="+237698765432"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="firstName",
     *                     type="string",
     *                     example="Jean",
     *                     description="Requis pour worker (pas pour engin)"
     *                 ),
     *                 @OA\Property(
     *                     property="lastName",
     *                     type="string",
     *                     example="Dupont",
     *                     description="Requis pour worker (pas pour engin)"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="nameOfTheEngin",
     *                     type="string",
     *                     example="Bulldozer Caterpillar D6T",
     *                     description="Requis pour engin (pas pour worker)"
     *                 ),
     *                 @OA\Property(
     *                     property="brandOfTheDevice",
     *                     type="string",
     *                     example="Caterpillar",
     *                     description="Requis pour engin (pas pour worker)"
     *                 ),
     *                 @OA\Property(
     *                     property="feature",
     *                     type="string",
     *                     example="Bulldozer avec lame hydraulique, puissance 215 HP",
     *                     description="Optionnel pour engin"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="localisation_worker_id",
     *                     type="integer",
     *                     example=1,
     *                     description="ID de localisation existante (optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="localisation_name",
     *                     type="string",
     *                     example="Douala",
     *                     description="Nom de localisation (créée si n'existe pas, optionnel)"
     *                 ),
     *                 @OA\Property(
     *                     property="years_of_experience",
     *                     type="integer",
     *                     example=5
     *                 ),
     *                 @OA\Property(
     *                     property="presentation",
     *                     type="string",
     *                     example="Professionnel expérimenté dans le domaine",
     *                     description="Optionnel"
     *                 ),
     *                 @OA\Property(
     *                     property="is_enterprise",
     *                     type="boolean",
     *                     example=false
     *                 ),
     *                 @OA\Property(
     *                     property="lot_id",
     *                     type="integer",
     *                     example=1,
     *                     description="ID du lot (engin, architect, engineer, commercial, etc.)"
     *                 ),
     *                 @OA\Property(
     *                     property="lot_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={18, 19, 20},
     *                     description="IDs des lots enfants (uniquement pour commercial)"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="commercial_register",
     *                     type="string",
     *                     format="binary",
     *                     description="Requis si is_enterprise=true"
     *                 ),
     *                 @OA\Property(
     *                     property="immigration_certificate",
     *                     type="string",
     *                     format="binary",
     *                     description="Requis si is_enterprise=true"
     *                 ),
     *                 @OA\Property(
     *                     property="certificate_of_compliance",
     *                     type="string",
     *                     format="binary",
     *                     description="Requis si is_enterprise=true"
     *                 ),
     *                 @OA\Property(
     *                     property="approval",
     *                     type="string",
     *                     format="binary",
     *                     description="Optionnel si is_enterprise=true"
     *                 ),
     *                 @OA\Property(
     *                     property="patent",
     *                     type="string",
     *                     format="binary",
     *                     description="Optionnel si is_enterprise=true"
     *                 ),
     *                 @OA\Property(
     *                     property="worker_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2, 3},
     *                     description="IDs de 3 travailleurs (requis si is_enterprise=true)"
     *                 )
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
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="email", type="string", example="worker@example.com"),
     *                 @OA\Property(
     *                     property="contact",
     *                     type="object",
     *                     description="Présent pour worker (pas pour engin)",
     *                     @OA\Property(property="firstName", type="string", example="Jean"),
     *                     @OA\Property(property="lastName", type="string", example="Dupont"),
     *                     @OA\Property(property="phoneNumber", type="string", example="+237698765432")
     *                 ),
     *                 @OA\Property(
     *                     property="engin",
     *                     type="object",
     *                     description="Présent pour engin (pas pour worker)",
     *                     @OA\Property(property="nameOfTheEngin", type="string", example="Bulldozer Caterpillar D6T"),
     *                     @OA\Property(property="brandOfTheDevice", type="string", example="Caterpillar"),
     *                     @OA\Property(property="feature", type="string", example="Bulldozer avec lame hydraulique")
     *                 ),
     *                 @OA\Property(
     *                     property="accountType",
     *                     type="object",
     *                     @OA\Property(property="years_of_experience", type="integer", example=5),
     *                     @OA\Property(property="is_enterprise", type="boolean", example=false),
     *                     @OA\Property(property="account_creation_request", type="string", example="pending")
     *                 ),
     *                 @OA\Property(
     *                     property="roles",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="name", type="string", example="engin")
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
     *             @OA\Property(property="message", type="string", example="Les données fournies sont invalides")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
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
            'localisation_worker_id' => 'nullable|exists:localisation_workers,id',
            'localisation_name' => 'nullable|string|max:255',
            
            'years_of_experience' => 'required|integer|min:0',
            'presentation' => 'nullable|string|max:5000',
            
            'is_enterprise' => 'required|boolean',
        ];

        $isEngin = false;
        $lotForCheck = null;
        
        if ($request->has('lot_id')) {
            $lotForCheck = Lot::with('parent')->find($request->input('lot_id'));
            if ($lotForCheck) {
                $lotName = strtolower(trim($lotForCheck->name));
                $parentLotName = $lotForCheck->parent ? strtolower(trim($lotForCheck->parent->name)) : null;
                
                if ($lotName === 'engin' || $parentLotName === 'engin') {
                    $isEngin = true;
                }
            }
        }

        if ($isEngin) {
            $rules['nameOfTheEngin'] = 'required|string|max:255';
            $rules['brandOfTheDevice'] = 'required|string|max:255';
            $rules['feature'] = 'nullable|string|max:5000';
        } else {
            $rules['firstName'] = 'required|string|max:255';
            $rules['lastName'] = 'required|string|max:255';
        }

        $rules['lot_id'] = 'nullable|exists:lots,id';
        $rules['lot_ids'] = 'nullable|array';
        $rules['lot_ids.*'] = 'exists:lots,id';

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
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'privacy_policy' => $validated['privacy_policy'],
            ]);

            $assignedRole = 'user';
            $isCommercial = false;
            $commercialParentId = null;
            $finalLotId = null;

            if (!empty($validated['lot_ids'])) {
                $firstChildLot = Lot::with('parent')->find($validated['lot_ids'][0]);
                
                if ($firstChildLot && $firstChildLot->parent) {
                    $parentLotName = strtolower(trim($firstChildLot->parent->name));
                    
                    if ($parentLotName === 'commercial') {
                        $assignedRole = 'commercial';
                        $isCommercial = true;
                        $commercialParentId = $firstChildLot->parent->id;
                        $finalLotId = $commercialParentId;
                        
                        foreach ($validated['lot_ids'] as $childId) {
                            $childLot = Lot::find($childId);
                            if (!$childLot || $childLot->main_id !== $commercialParentId) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Tous les lots doivent appartenir au lot parent "commercial"',
                                ], 422);
                            }
                        }
                    }
                }
            }

            if (!empty($validated['lot_id']) && !$isCommercial) {
                $lot = Lot::with('parent')->find($validated['lot_id']);

                \Log::info('Lot trouvé', [
                    'lot_id' => $validated['lot_id'],
                    'lot_name' => $lot ? $lot->name : 'null',
                    'parent_name' => $lot && $lot->parent ? $lot->parent->name : 'null',
                ]);
                
                if ($lot) {
                    $lotName = strtolower(trim($lot->name));
                    $parentLotName = $lot->parent ? strtolower(trim($lot->parent->name)) : null;

                    \Log::info('Vérification engin', [
                        'lotName' => $lotName,
                        'parentLotName' => $parentLotName,
                        'isEngin' => ($lotName === 'engin' || $parentLotName === 'engin'),
                    ]);
                    
                    if ($lotName === 'engin' || $parentLotName === 'engin') {
                        $assignedRole = 'engin';
                        $finalLotId = $validated['lot_id'];
                    }
                    elseif ($parentLotName === 'commercial') {
                        $assignedRole = 'commercial';
                        $isCommercial = true;
                        $finalLotId = $lot->main_id;
                    }
                    elseif (in_array($lotName, ['site supervisor', 'site manager', 'technical director']) ||
                        in_array($parentLotName, ['site supervisor', 'site manager', 'technical director'])) {
                        $assignedRole = 'manager';
                        $finalLotId = $validated['lot_id'];
                    }
                    elseif (in_array($lotName, ['architect', 'engineer']) ||
                            in_array($parentLotName, ['architect', 'engineer'])) {
                        $assignedRole = 'corrector';
                        $finalLotId = $validated['lot_id'];
                    }
                    else {
                        $assignedRole = 'user';
                        $finalLotId = $validated['lot_id'];
                    }
                }
    
            }

            try {
                $role = \Spatie\Permission\Models\Role::where('name', $assignedRole)
                    ->where('guard_name', 'api')
                    ->firstOrFail();
                
                $user->assignRole($assignedRole);
                
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                DB::rollBack();
                
                \Log::error('Rôle manquant lors de l\'inscription', [
                    'assignedRole' => $assignedRole,
                    'user_email' => $validated['email'],
                    'lot_id' => $validated['lot_id'] ?? null,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "Le rôle '{$assignedRole}' n'existe pas dans le système. Veuillez contacter l'administrateur.",
                    'debug' => [
                        'role_recherché' => $assignedRole,
                        'roles_disponibles' => \Spatie\Permission\Models\Role::where('guard_name', 'api')
                            ->pluck('name')->toArray()
                    ]
                ], 500);
            }

            if ($isEngin) {
                $user->engin()->create([
                    'nameOfTheEngin' => $validated['nameOfTheEngin'],
                    'brandOfTheDevice' => $validated['brandOfTheDevice'],
                    'feature' => $validated['feature'] ?? null,
                    'localisation_worker_id' => $localisationWorkerId,
                ]);
            } else {
                $user->contact()->create([
                    'phoneNumber' => $validated['phoneNumber'],
                    'firstName' => $validated['firstName'],
                    'lastName' => $validated['lastName'],
                    'email' => $validated['email'],
                    'localisation_worker_id' => $localisationWorkerId,
                ]);
            }

            $accountType = $user->accountType()->create([
                'lot_id' => $finalLotId,
                'is_enterprise' => $validated['is_enterprise'],
                'years_of_experience' => $validated['years_of_experience'],
                'presentation' => $validated['presentation'] ?? null,
                'account_creation_request' => 'pending',
            ]);

            if ($isCommercial && !empty($validated['lot_ids'])) {
                foreach ($validated['lot_ids'] as $childLotId) {
                    AccountTypeLot::create([
                        'account_type_id' => $accountType->id,
                        'lot_id' => $childLotId,
                    ]);
                }
            }

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
                    'engin', 
                    'accountType.lot',
                    'accountType.lots',
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

        if ($user->accountType && $user->accountType->account_creation_request === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été rejeté',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load([
            'contact',
            'engin',
            'accountType.lot.parent',
            'enterpriseDocument'
        ]);

        $childLotName = null;
        $parentLotName = null;

        if ($user->accountType && $user->accountType->lot) {
            $childLotName = $user->accountType->lot->name;
            
            if ($user->accountType->lot->parent) {
                $parentLotName = $user->accountType->lot->parent->name;
            }
        }

        $userData = [
            'id' => $user->id,
            'role' => $user->role,
            'profil' => $user->profil ? asset('storage/' . $user->profil) : null,
            'nationalIDCard' => $user->nationalIDCard ? asset('storage/' . $user->nationalIDCard) : null,
            'child_lot_name' => $childLotName,
            'parent_lot_name' => $parentLotName,
            'account_status' => $user->accountType?->account_creation_request,
            'is_enterprise' => $user->accountType?->is_enterprise ?? false,
            'years_of_experience' => $user->accountType?->years_of_experience,
            'presentation' => $user->accountType?->presentation,
            'engin' => $user->engin ? [
                'nameOfTheEngin' => $user->engin->nameOfTheEngin,
                'brandOfTheDevice' => $user->engin->brandOfTheDevice,
                'feature' => $user->engin->feature,
                'registration_document' => $user->engin->registration_document ? asset('storage/' . $user->engin->registration_document) : null,
                'purchase_invoice' => $user->engin->purchase_invoice ? asset('storage/' . $user->engin->purchase_invoice) : null,
                'last_gear_report' => $user->engin->last_gear_report ? asset('storage/' . $user->engin->last_gear_report) : null,
            ] : null,
            'contact' => $user->contact ? [
                'firstName' => $user->contact->firstName,
                'lastName' => $user->contact->lastName,
                'phoneNumber' => $user->contact->phoneNumber,
                'email' => $user->contact->email,
            ] : null,
            'enterprise_document' => $user->enterpriseDocument ? [
                'commercial_register' => $user->enterpriseDocument->commercial_register ? asset('storage/' . $user->enterpriseDocument->commercial_register) : null,
                'immigration_certificate' => $user->enterpriseDocument->immigration_certificate ? asset('storage/' . $user->enterpriseDocument->immigration_certificate) : null,
                'certificate_of_compliance' => $user->enterpriseDocument->certificate_of_compliance ? asset('storage/' . $user->enterpriseDocument->certificate_of_compliance) : null,
                'approval' => $user->enterpriseDocument->approval ? asset('storage/' . $user->enterpriseDocument->approval) : null,
                'patent' => $user->enterpriseDocument->patent ? asset('storage/' . $user->enterpriseDocument->patent) : null,
            ] : null,
        ];

        $isProfileIncomplete = false;

        if ($user->engin) {
            $isProfileIncomplete = empty($user->profil) 
                || empty($user->nationalIDCard)
                || empty($user->engin->registration_document)
                || empty($user->engin->purchase_invoice)
                || empty($user->engin->last_gear_report);
        } elseif ($user->contact) {
            $isProfileIncomplete = empty($user->profil) 
                || empty($user->nationalIDCard);
            
            if ($user->accountType?->is_enterprise) {
                $isProfileIncomplete = $isProfileIncomplete 
                    || empty($user->enterpriseDocument?->commercial_register)
                    || empty($user->enterpriseDocument?->immigration_certificate)
                    || empty($user->enterpriseDocument?->certificate_of_compliance);
            }
        }

        if ($isProfileIncomplete) {
            $profileUrl = env('FRONTEND_URL', 'http://localhost:3000') . '/user/profil/' . $user->id;
            
            return response()->json([
                'success' => true,
                'message' => 'Veuillez compléter votre profil',
                'redirect_url' => $profileUrl,
                'token' => $token,
                'user' => $userData, 
            ], 302);
        }

        return response()->json([
            'success' => true,
            'redirect_url' => null,
            'message' => __('messages.login_success'),
            'token' => $token,
            'user' => $userData,
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


    /**
     * @OA\Patch(
     *     path="/api/admin/users/{id}/update-account-status",
     *     summary="Accepter ou rejeter une demande de compte",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"accepted","rejected"}, example="accepted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte accepté")
     *         )
     *     )
     * )
     */
    public function updateAccountStatus(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        $targetUser = User::with('accountType')->findOrFail($id);

        if (!$targetUser->accountType) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur n\'a pas de compte type',
            ], 404);
        }

        $targetUser->accountType->update([
            'account_creation_request' => $validated['status']
        ]);

        $message = $validated['status'] === 'accepted' 
            ? 'Compte accepté' 
            : 'Compte rejeté';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'user_id' => $targetUser->id,
                'account_status' => $validated['status']
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/accounts",
     *     summary="Lister les comptes par statut avec recherche et pagination",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", nullable=true, enum={"pending","accepted","rejected"}, example="pending", description="Statut du compte"),
     *             @OA\Property(property="name", type="string", nullable=true, example="Jean", description="Recherche par nom ou prénom"),
     *             @OA\Property(property="perPage", type="integer", example=10, description="Nombre d'éléments par page"),
     *             @OA\Property(property="page", type="integer", example=1, description="Numéro de la page")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des comptes filtrés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=50),
     *                     @OA\Property(property="firstName", type="string", example="Jean"),
     *                     @OA\Property(property="account_status", type="string", example="pending")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="perPage", type="integer", example=10),
     *                 @OA\Property(property="currentPage", type="integer", example=1),
     *                 @OA\Property(property="lastPage", type="integer", example=3)
     *             )
     *         )
     *     )
     * )
     */
    public function listAccounts(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:pending,accepted,rejected',
            'name' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['perPage'] ?? 10;
        $page = $validated['page'] ?? 1;
        $status = $validated['status'] ?? null;
        $searchName = $validated['name'] ?? null;

        $query = User::whereHas('accountType');

        if ($status) {
            $query->whereHas('accountType', function ($q) use ($status) {
                $q->where('account_creation_request', $status);
            });
        }

        if ($searchName) {
            $query->whereHas('contact', function ($q) use ($searchName) {
                $q->where('firstName', 'ILIKE', '%' . $searchName . '%')
                ->orWhere('lastName', 'ILIKE', '%' . $searchName . '%');
            });
        }

        $users = $query->with('contact', 'accountType.lot')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'firstName' => $user->contact?->firstName,
                'lastName' => $user->contact?->lastName,
                'email' => $user->contact?->email,
                'phoneNumber' => $user->contact?->phoneNumber,
                'lot' => $user->accountType?->lot?->name,
                'years_of_experience' => $user->accountType?->years_of_experience,
                'is_enterprise' => $user->accountType?->is_enterprise,
                'account_status' => $user->accountType?->account_creation_request,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $users->total(),
                'perPage' => $users->perPage(),
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/admin/commercials",
     *     summary="Lister tous les commerciaux",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des commerciaux",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="email", type="string", example="commercial@example.com"),
     *                     @OA\Property(property="firstName", type="string", example="Sophie"),
     *                     @OA\Property(property="lastName", type="string", example="Commercial"),
     *                     @OA\Property(property="phoneNumber", type="string", example="+237698765447"),
     *                     @OA\Property(property="role", type="string", example="commercial"),
     *                     @OA\Property(property="account_type_id", type="integer", example=5),
     *                     @OA\Property(
     *                         property="lots",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=18),
     *                             @OA\Property(property="name", type="string", example="Vente immobilier")
     *                         )
     *                     )
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
    public function listCommercials()
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('corrector')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $commercials = User::whereHas('roles', function ($query) {
                $query->where('name', 'commercial');
            })
            ->with('contact', 'accountType.lots')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $commercials->map(function ($commercial) {
                return [
                    'id' => $commercial->id,
                    'email' => $commercial->contact?->email,
                    'firstName' => $commercial->contact?->firstName,
                    'lastName' => $commercial->contact?->lastName,
                    'phoneNumber' => $commercial->contact?->phoneNumber,
                    'role' => $commercial->role,
                    'account_type_id' => $commercial->accountType?->id,
                    'is_enterprise' => $commercial->accountType?->is_enterprise,
                    'years_of_experience' => $commercial->accountType?->years_of_experience,
                    'lots' => $commercial->accountType?->lots->map(function ($lot) {
                        return [
                            'id' => $lot->id,
                            'name' => $lot->name,
                        ];
                    }),
                    'created_at' => $commercial->created_at,
                ];
            })
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/engin/complete-profile",
     *     summary="Compléter le profil de l'engin avec documents",
     *     tags={"Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profil","nationalIDCard","registration_document","purchase_invoice","last_gear_report"},
     *                 @OA\Property(property="profil", type="string", format="binary", description="Photo de profil"),
     *                 @OA\Property(property="nationalIDCard", type="string", format="binary", description="Carte d'identité nationale (image)"),
     *                 @OA\Property(property="registration_document", type="string", format="binary", description="Document d'immatriculation (PDF)"),
     *                 @OA\Property(property="purchase_invoice", type="string", format="binary", description="Facture d'achat (PDF)"),
     *                 @OA\Property(property="last_gear_report", type="string", format="binary", description="Dernier rapport de contrôle (PDF)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil complété avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profil complété avec succès")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Utilisateur n'est pas un engin"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function completeEnginProfile(Request $request)
    {
        $user = auth()->user();

        if (!$user->engin) {
            return response()->json([
                'success' => false,
                'message' => 'Cet endpoint est réservé aux engins',
            ], 403);
        }

        $validated = $request->validate([
            'profil' => 'required|image|mimes:jpeg,png,jpg|max:5120', 
            'nationalIDCard' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'registration_document' => 'required|file|mimes:pdf|max:10240',
            'purchase_invoice' => 'required|file|mimes:pdf|max:10240',
            'last_gear_report' => 'required|file|mimes:pdf|max:10240',
        ]);

        try {
            DB::beginTransaction();

            if ($request->hasFile('profil')) {
                if ($user->profil && Storage::disk('public')->exists($user->profil)) {
                    Storage::disk('public')->delete($user->profil);
                }

                $user->profil = $request->file('profil')->store('engin_profiles', 'public');
            }

            if ($request->hasFile('nationalIDCard')) {
                if ($user->nationalIDCard && Storage::disk('public')->exists($user->nationalIDCard)) {
                    Storage::disk('public')->delete($user->nationalIDCard);
                }

                $user->nationalIDCard = $request->file('nationalIDCard')->store('national_id_cards', 'public');
            }

            $user->save();

            $engin = $user->engin;

            if ($request->hasFile('registration_document')) {
                if ($engin->registration_document && Storage::disk('public')->exists($engin->registration_document)) {
                    Storage::disk('public')->delete($engin->registration_document);
                }

                $engin->registration_document = $request->file('registration_document')
                    ->store('engin_documents/registration', 'public');
            }

            if ($request->hasFile('purchase_invoice')) {
                if ($engin->purchase_invoice && Storage::disk('public')->exists($engin->purchase_invoice)) {
                    Storage::disk('public')->delete($engin->purchase_invoice);
                }

                $engin->purchase_invoice = $request->file('purchase_invoice')
                    ->store('engin_documents/invoices', 'public');
            }

            if ($request->hasFile('last_gear_report')) {
                if ($engin->last_gear_report && Storage::disk('public')->exists($engin->last_gear_report)) {
                    Storage::disk('public')->delete($engin->last_gear_report);
                }

                $engin->last_gear_report = $request->file('last_gear_report')
                    ->store('engin_documents/reports', 'public');
            }

            $engin->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profil complété avec succès',
                'data' => [
                    'profil' => $user->profil ? asset('storage/' . $user->profil) : null,
                    'nationalIDCard' => $user->nationalIDCard ? asset('storage/' . $user->nationalIDCard) : null, 
                    'registration_document' => $engin->registration_document ? asset('storage/' . $engin->registration_document) : null,
                    'purchase_invoice' => $engin->purchase_invoice ? asset('storage/' . $engin->purchase_invoice) : null,
                    'last_gear_report' => $engin->last_gear_report ? asset('storage/' . $engin->last_gear_report) : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload des documents : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/worker/complete-profile",
     *     summary="Compléter le profil du worker (entreprise ou individuel)",
     *     tags={"Workers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="profil", type="string", format="binary", description="Photo de profil (requis)"),
     *                 @OA\Property(property="nationalIDCard", type="string", format="binary", description="Carte d'identité nationale (requis)"),
     *                 @OA\Property(property="worker_user_ids", type="string", example="15,22,35", description="IDs des workers séparés par virgule (requis si entreprise)"),
     *                 @OA\Property(property="years_of_experience", type="integer", example=5, description="Années d'expérience (optionnel)"),
     *                 @OA\Property(property="presentation", type="string", example="Description professionnelle", description="Présentation (optionnel)"),
     *                 @OA\Property(property="commercial_register", type="string", format="binary", description="Registre de commerce (requis si entreprise)"),
     *                 @OA\Property(property="immigration_certificate", type="string", format="binary", description="Certificat d'immigration (requis si entreprise)"),
     *                 @OA\Property(property="certificate_of_compliance", type="string", format="binary", description="Certificat de conformité (requis si entreprise)"),
     *                 @OA\Property(property="approval", type="string", format="binary", description="Agrément (optionnel)"),
     *                 @OA\Property(property="patent", type="string", format="binary", description="Brevet (optionnel)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profil complété avec succès")
    * )
    */
    public function completeWorkerOrEntrepriseProfile(Request $request)
    {
        $user = auth()->user();

        $user->load('accountType', 'enterpriseDocument');

        $isEnterprise = false;
        
        if ($user->accountType) {
            $rawValue = $user->accountType->is_enterprise;
            
            if (in_array($rawValue, [0, '0', false, 'false', null], true)) {
                $isEnterprise = false;
            } elseif (in_array($rawValue, [1, '1', true, 'true'], true)) {
                $isEnterprise = true;
            }
        }

        $rules = [
            'profil' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'nationalIDCard' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'years_of_experience' => 'nullable|integer|min:0',
            'presentation' => 'nullable|string|max:5000',
        ];

        if ($isEnterprise === true) {
            $rules['worker_user_ids'] = 'required|string';
            $rules['commercial_register'] = 'required|file|mimes:pdf|max:10240';
            $rules['immigration_certificate'] = 'required|file|mimes:pdf|max:10240';
            $rules['certificate_of_compliance'] = 'required|file|mimes:pdf|max:10240';
            $rules['approval'] = 'nullable|file|mimes:pdf|max:10240';
            $rules['patent'] = 'nullable|file|mimes:pdf|max:10240';
        }

        $validated = $request->validate($rules);

        if ($isEnterprise === true && isset($validated['worker_user_ids'])) {
            $workerIds = array_map('trim', explode(',', $validated['worker_user_ids']));
            $workerIds = array_filter($workerIds, 'is_numeric');
            
            if (empty($workerIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun ID de worker valide fourni',
                ], 422);
            }

            $workers = \App\Models\User::whereIn('id', $workerIds)
                ->with('accountType')
                ->get();

            if ($workers->count() !== count($workerIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certains IDs de workers sont invalides',
                ], 422);
            }

            $enterpriseWorkers = $workers->filter(function ($worker) {
                return $worker->accountType && 
                    in_array($worker->accountType->is_enterprise, [1, '1', true, 'true'], true);
            });

            if ($enterpriseWorkers->isNotEmpty()) {
                $enterpriseIds = $enterpriseWorkers->pluck('id')->implode(', ');
                return response()->json([
                    'success' => false,
                    'message' => "Les utilisateurs suivants sont des entreprises et ne peuvent pas être ajoutés comme workers: {$enterpriseIds}",
                ], 422);
            }

            $validated['worker_user_ids'] = $workerIds;
        }

        try {
            DB::beginTransaction();

            if ($request->hasFile('profil')) {
                if ($user->profil && Storage::disk('public')->exists($user->profil)) {
                    Storage::disk('public')->delete($user->profil);
                }

                $user->profil = $request->file('profil')->store('worker_profiles', 'public');
            }

            if ($request->hasFile('nationalIDCard')) {
                if ($user->nationalIDCard && Storage::disk('public')->exists($user->nationalIDCard)) {
                    Storage::disk('public')->delete($user->nationalIDCard);
                }

                $user->nationalIDCard = $request->file('nationalIDCard')->store('national_id_cards', 'public');
            }

            $user->save();

            if ($user->accountType) {
                if ($request->has('years_of_experience')) {
                    $user->accountType->years_of_experience = $validated['years_of_experience'];
                }
                if ($request->has('presentation')) {
                    $user->accountType->presentation = $validated['presentation'];
                }
                $user->accountType->save();
            }

            if ($isEnterprise === true) {
                $documentsData = [];

                if ($request->hasFile('commercial_register')) {
                    if ($user->enterpriseDocument?->commercial_register && Storage::disk('public')->exists($user->enterpriseDocument->commercial_register)) {
                        Storage::disk('public')->delete($user->enterpriseDocument->commercial_register);
                    }
                    $documentsData['commercial_register'] = $request->file('commercial_register')
                        ->store('enterprise_documents/commercial_registers', 'public');
                }

                if ($request->hasFile('immigration_certificate')) {
                    if ($user->enterpriseDocument?->immigration_certificate && Storage::disk('public')->exists($user->enterpriseDocument->immigration_certificate)) {
                        Storage::disk('public')->delete($user->enterpriseDocument->immigration_certificate);
                    }
                    $documentsData['immigration_certificate'] = $request->file('immigration_certificate')
                        ->store('enterprise_documents/immigration_certificates', 'public');
                }

                if ($request->hasFile('certificate_of_compliance')) {
                    if ($user->enterpriseDocument?->certificate_of_compliance && Storage::disk('public')->exists($user->enterpriseDocument->certificate_of_compliance)) {
                        Storage::disk('public')->delete($user->enterpriseDocument->certificate_of_compliance);
                    }
                    $documentsData['certificate_of_compliance'] = $request->file('certificate_of_compliance')
                        ->store('enterprise_documents/certificates_of_compliance', 'public');
                }

                if ($request->hasFile('approval')) {
                    if ($user->enterpriseDocument?->approval && Storage::disk('public')->exists($user->enterpriseDocument->approval)) {
                        Storage::disk('public')->delete($user->enterpriseDocument->approval);
                    }
                    $documentsData['approval'] = $request->file('approval')
                        ->store('enterprise_documents/approvals', 'public');
                }

                if ($request->hasFile('patent')) {
                    if ($user->enterpriseDocument?->patent && Storage::disk('public')->exists($user->enterpriseDocument->patent)) {
                        Storage::disk('public')->delete($user->enterpriseDocument->patent);
                    }
                    $documentsData['patent'] = $request->file('patent')
                        ->store('enterprise_documents/patents', 'public');
                }

                if ($user->enterpriseDocument) {
                    $user->enterpriseDocument->update($documentsData);
                } else {
                    $user->enterpriseDocument()->create($documentsData);
                }

                $addedWorkers = [];
                foreach ($validated['worker_user_ids'] as $workerId) {
                    $workerEnterprise = \App\Models\WorkerEnterprise::updateOrCreate(
                        [
                            'enterprise_user_id' => $user->id,
                            'worker_user_id' => $workerId,
                        ],
                        [
                            'enterprise_user_id' => $user->id,
                            'worker_user_id' => $workerId,
                            'status' => 'pending', 
                        ]
                    );
                    
                    $addedWorkers[] = $workerEnterprise;
                }
            }

            DB::commit();

            $responseData = [
                'profil' => $user->profil ? asset('storage/' . $user->profil) : null,
                'nationalIDCard' => $user->nationalIDCard ? asset('storage/' . $user->nationalIDCard) : null,
                'years_of_experience' => $user->accountType?->years_of_experience,
                'presentation' => $user->accountType?->presentation,
            ];

            if ($isEnterprise === true) {
                if ($user->enterpriseDocument) {
                    $responseData['enterprise_documents'] = [
                        'commercial_register' => $user->enterpriseDocument->commercial_register ? asset('storage/' . $user->enterpriseDocument->commercial_register) : null,
                        'immigration_certificate' => $user->enterpriseDocument->immigration_certificate ? asset('storage/' . $user->enterpriseDocument->immigration_certificate) : null,
                        'certificate_of_compliance' => $user->enterpriseDocument->certificate_of_compliance ? asset('storage/' . $user->enterpriseDocument->certificate_of_compliance) : null,
                        'approval' => $user->enterpriseDocument->approval ? asset('storage/' . $user->enterpriseDocument->approval) : null,
                        'patent' => $user->enterpriseDocument->patent ? asset('storage/' . $user->enterpriseDocument->patent) : null,
                    ];
                }
                
                if (isset($addedWorkers) && !empty($addedWorkers)) {
                    $responseData['workers'] = collect($addedWorkers)->map(function ($we) {
                        $we->load('worker.contact');
                        return [
                            'id' => $we->id,
                            'worker_user_id' => $we->worker_user_id,
                            'worker_name' => $we->worker?->contact?->firstName . ' ' . $we->worker?->contact?->lastName,
                            'worker_email' => $we->worker?->contact?->email,
                        ];
                    });
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Profil complété avec succès',
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     summary="Récupérer les détails du profil de l'utilisateur connecté (Worker ou Engin)",
     *     tags={"User Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Détails du profil - Structure varie selon le type (worker ou engin)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Détails du profil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=32),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="user_type", type="string", enum={"worker","engin"}, example="worker"),
     *                 @OA\Property(property="profil", type="string", nullable=true, example="http://example.com/storage/worker_profiles/photo.png"),
     *                 @OA\Property(property="nationalIDCard", type="string", nullable=true, example="http://example.com/storage/national_id_cards/id.png"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"corrector"}),
     *                 @OA\Property(property="privacy_policy", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-03-15T10:30:00.000000Z"),
     *                 @OA\Property(
     *                     property="account",
     *                     type="object",
     *                     @OA\Property(property="child_lot_name", type="string", nullable=true, example="architect"),
     *                     @OA\Property(property="parent_lot_name", type="string", nullable=true, example="engineering"),
     *                     @OA\Property(property="all_lots", type="array", @OA\Items(type="object"), example={}),
     *                     @OA\Property(property="is_enterprise", type="boolean", example=false),
     *                     @OA\Property(property="years_of_experience", type="integer", nullable=true, example=5),
     *                     @OA\Property(property="presentation", type="string", nullable=true, example="Expert en architecture"),
     *                     @OA\Property(property="account_status", type="string", example="pending")
     *                 ),
     *                 @OA\Property(
     *                     property="contact",
     *                     type="object",
     *                     description="Présent uniquement si user_type = 'worker'",
     *                     @OA\Property(property="firstName", type="string", example="Jean"),
     *                     @OA\Property(property="lastName", type="string", example="Dupont"),
     *                     @OA\Property(property="phoneNumber", type="string", example="568985656"),
     *                     @OA\Property(property="email", type="string", example="jean@example.com"),
     *                     @OA\Property(property="localisation", type="string", nullable=true, example="Douala")
     *                 ),
     *                 @OA\Property(
     *                     property="engin",
     *                     type="object",
     *                     description="Présent uniquement si user_type = 'engin'",
     *                     @OA\Property(property="nameOfTheEngin", type="string", example="Caterpillar D9"),
     *                     @OA\Property(property="brandOfTheDevice", type="string", example="Caterpillar"),
     *                     @OA\Property(property="feature", type="string", nullable=true, example="Bulldozer haute performance"),
     *                     @OA\Property(property="localisation", type="string", nullable=true, example="Yaoundé"),
     *                     @OA\Property(property="registration_document", type="string", nullable=true, example="http://example.com/storage/engin_documents/registration/doc.pdf"),
     *                     @OA\Property(property="purchase_invoice", type="string", nullable=true, example="http://example.com/storage/engin_documents/invoices/invoice.pdf"),
     *                     @OA\Property(property="last_gear_report", type="string", nullable=true, example="http://example.com/storage/engin_documents/reports/report.pdf")
     *                 ),
     *                 @OA\Property(
     *                     property="enterprise_documents",
     *                     type="object",
     *                     description="Présent uniquement si l'utilisateur est une entreprise (is_enterprise = true)",
     *                     @OA\Property(property="commercial_register", type="string", nullable=true),
     *                     @OA\Property(property="immigration_certificate", type="string", nullable=true),
     *                     @OA\Property(property="certificate_of_compliance", type="string", nullable=true),
     *                     @OA\Property(property="approval", type="string", nullable=true),
     *                     @OA\Property(property="patent", type="string", nullable=true)
     *                 ),
     *                 @OA\Property(
     *                     property="workers",
     *                     type="array",
     *                     description="Liste des workers de l'entreprise (présent uniquement si is_enterprise = true)",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function getUserProfile()
    {
        $user = auth()->user();

        $user->load([
            'contact.localisationWorker',
            'engin.localisationWorker',
            'accountType.lot.parent',
            'accountType.lots', 
            'enterpriseDocument',
            'roles'
        ]);

        $userType = null;
        if ($user->engin) {
            $userType = 'engin';
        } elseif ($user->contact) {
            $userType = 'worker';
        }

        $childLotName = null;
        $parentLotName = null;
        $allLots = [];

        if ($user->accountType) {
            if ($user->accountType->lot) {
                $childLotName = $user->accountType->lot->name;
                
                if ($user->accountType->lot->parent) {
                    $parentLotName = $user->accountType->lot->parent->name;
                }
            }

            if ($user->accountType->lots && $user->accountType->lots->isNotEmpty()) {
                $allLots = $user->accountType->lots->map(function ($lot) {
                    return [
                        'id' => $lot->id,
                        'name' => $lot->name,
                        'parent_name' => $lot->parent?->name,
                    ];
                });
            }
        }

        $isEnterprise = false;
        if ($user->accountType) {
            $rawValue = $user->accountType->is_enterprise;
            if (in_array($rawValue, [1, '1', true, 'true'], true)) {
                $isEnterprise = true;
            }
        }

        $profileData = [
            'id' => $user->id,
            'email' => $user->email,
            'user_type' => $userType,
            'profil' => $user->profil ? asset('storage/' . $user->profil) : null,
            'nationalIDCard' => $user->nationalIDCard ? asset('storage/' . $user->nationalIDCard) : null,
            'roles' => $user->roles->pluck('name'),
            'privacy_policy' => $user->privacy_policy,
            'created_at' => $user->created_at,
        ];

        if ($user->accountType) {
            $profileData['account'] = [
                'child_lot_name' => $childLotName,
                'parent_lot_name' => $parentLotName,
                'all_lots' => $allLots,
                'is_enterprise' => $isEnterprise,
                'years_of_experience' => $user->accountType->years_of_experience,
                'presentation' => $user->accountType->presentation,
                'account_status' => $user->accountType->account_creation_request,
            ];
        }

        if ($user->engin) {
            $profileData['engin'] = [
                'nameOfTheEngin' => $user->engin->nameOfTheEngin,
                'brandOfTheDevice' => $user->engin->brandOfTheDevice,
                'feature' => $user->engin->feature,
                'localisation' => $user->engin->localisationWorker?->name,
                'registration_document' => $user->engin->registration_document ? asset('storage/' . $user->engin->registration_document) : null,
                'purchase_invoice' => $user->engin->purchase_invoice ? asset('storage/' . $user->engin->purchase_invoice) : null,
                'last_gear_report' => $user->engin->last_gear_report ? asset('storage/' . $user->engin->last_gear_report) : null,
            ];
        }

        if ($user->contact) {
            $profileData['contact'] = [
                'firstName' => $user->contact->firstName,
                'lastName' => $user->contact->lastName,
                'phoneNumber' => $user->contact->phoneNumber,
                'email' => $user->contact->email,
                'localisation' => $user->contact->localisationWorker?->name,
            ];
        }

        if ($user->enterpriseDocument) {
            $profileData['enterprise_documents'] = [
                'commercial_register' => $user->enterpriseDocument->commercial_register ? asset('storage/' . $user->enterpriseDocument->commercial_register) : null,
                'immigration_certificate' => $user->enterpriseDocument->immigration_certificate ? asset('storage/' . $user->enterpriseDocument->immigration_certificate) : null,
                'certificate_of_compliance' => $user->enterpriseDocument->certificate_of_compliance ? asset('storage/' . $user->enterpriseDocument->certificate_of_compliance) : null,
                'approval' => $user->enterpriseDocument->approval ? asset('storage/' . $user->enterpriseDocument->approval) : null,
                'patent' => $user->enterpriseDocument->patent ? asset('storage/' . $user->enterpriseDocument->patent) : null,
            ];
        }

        if ($isEnterprise === true) {
            $workerEnterprises = \App\Models\WorkerEnterprise::where('enterprise_user_id', $user->id)
                ->with(['worker.contact.localisationWorker', 'worker.accountType.lot'])
                ->get();

            $profileData['workers'] = $workerEnterprises->map(function ($we) {
                return [
                    'id' => $we->id,
                    'worker_user_id' => $we->worker_user_id,
                    'status' => $we->status,
                    'worker_name' => $we->worker?->contact?->firstName . ' ' . $we->worker?->contact?->lastName,
                    'worker_email' => $we->worker?->contact?->email,
                    'worker_phone' => $we->worker?->contact?->phoneNumber,
                    'worker_localisation' => $we->worker?->contact?->localisationWorker?->name,
                    'worker_lot' => $we->worker?->accountType?->lot?->name,
                    'worker_profil' => $we->worker?->profil ? asset('storage/' . $we->worker->profil) : null,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails du profil',
            'data' => $profileData
        ]);
    }
}
