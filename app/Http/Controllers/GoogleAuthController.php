<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * @OA\Get(
     *     path="/api/auth/google",
     *     summary="Rediriger vers Google pour l'authentification",
     *     tags={"Authentification Google"},
     *     @OA\Response(
     *         response=302,
     *         description="Redirection vers Google"
     *     )
     * )
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $contact = Contact::where('email', $googleUser->getEmail())->first();

            if ($contact && $contact->user) {
                $user = $contact->user;
                
                if (!$user->email_verified_at) {
                    $user->update(['email_verified_at' => now()]);
                }
            } else {
                $user = User::where('email', $googleUser->getEmail())->first();
                
                if ($user) {
                    if (!$user->contact) {
                        $user->contact()->create([
                            'email' => $googleUser->getEmail(),
                            'firstName' => $googleUser->offsetGet('given_name') ?? 'User',
                            'lastName' => $googleUser->offsetGet('family_name') ?? 'Account',
                            'phoneNumber' => null,
                        ]);
                    }
                    
                    if (!$user->email_verified_at) {
                        $user->update(['email_verified_at' => now()]);
                    }
                } else {
                    DB::beginTransaction();
                    
                    try {
                        $newContact = Contact::create([
                            'email' => $googleUser->getEmail(),
                            'firstName' => $googleUser->offsetGet('given_name') ?? 'User',
                            'lastName' => $googleUser->offsetGet('family_name') ?? 'Account',
                            'phoneNumber' => null,
                        ]);

                        $user = User::create([
                            'email' => null, 
                            'password' => Hash::make(Str::random(32)),
                            'privacy_policy' => false,
                            'email_verified_at' => now(),
                        ]);

                        $newContact->update(['user_id' => $user->id]);
                        $role = \Spatie\Permission\Models\Role::findByName('client', 'api');
                        $user->roles()->attach($role->id);

                        DB::commit();
                        
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }
            }

            $token = $user->createToken('google-oauth', ['*'], now()->addDays(30))->plainTextToken;

            $frontendUrl = config('app.frontend_url');
            
            if (!$frontendUrl) {
                throw new \Exception('Frontend URL is not configured');
            }

            return redirect()->away("{$frontendUrl}/auth/callback?token={$token}");

        } catch (\Exception $e) {
            \Log::error('Google OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $frontendUrl = config('app.frontend_url', config('app.url'));
            
            return redirect()->away("{$frontendUrl}/auth/error?message=" . urlencode('Authentication failed. Please try again.'));
        }
    }


    /**
     * @OA\Get(
     *     path="/api/auth/verify",
     *     summary="Vérifier le token et obtenir les infos utilisateur",
     *     tags={"Authentification Google"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="email", type="string", example="user@gmail.com"),
     *                 @OA\Property(property="role", type="string", example="client")
     *             )
     *         )
     *     )
     * )
     */
    public function verifyToken(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide',
            ], 401);
        }

        $user->load('contact', 'roles');

        return response()->json([
            'success' => true,
            'data' => [
                'email_verified_at' => $user->email_verified_at,
                'token' => $request->bearerToken(),
                'user' => [
                    'id' => $user->id,
                    'email' => $user->contact?->email ?? $user->email,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->roles->map(fn($role) => [
                        'name' => $role->name,
                        'display_name' => $role->name
                    ]),
                ]
            ]
        ]);
    }
}