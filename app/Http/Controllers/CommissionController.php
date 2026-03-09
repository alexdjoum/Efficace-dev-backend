<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\ProjectSold;
use App\Models\AccountType;
use App\Models\User;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/admin/commissions",
     *     summary="Créer une commission pour un commercial (Admin/Corrector uniquement)",
     *     tags={"Commissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"project_sold_id","account_type_id","rate"},
     *             @OA\Property(property="project_sold_id", type="integer", example=1),
     *             @OA\Property(property="account_type_id", type="integer", example=5, description="ID du compte commercial"),
     *             @OA\Property(property="rate", type="number", format="float", example=5.50, description="Taux en pourcentage")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Commission créée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('corrector')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et correctors',
            ], 403);
        }

        $validated = $request->validate([
            'project_sold_id' => 'required|exists:project_solds,id',
            'account_type_id' => 'required|exists:account_types,id',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $accountType = AccountType::with('user.roles')->findOrFail($validated['account_type_id']);
        
        $isCommercial = $accountType->user->roles()
            ->where('name', 'commercial')
            ->exists();

        if (!$isCommercial) {
            return response()->json([
                'success' => false,
                'message' => 'Le compte sélectionné n\'est pas un commercial',
            ], 422);
        }

        $projectSold = ProjectSold::with('project')->findOrFail($validated['project_sold_id']);

        $baseAmount = $projectSold->amount - $projectSold->project->amount_to_perceive;
        $commissionAmount = ($baseAmount * $validated['rate']) / 100;

        $exists = Commission::where('project_sold_id', $validated['project_sold_id'])
            ->where('account_type_id', $validated['account_type_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Une commission existe déjà pour ce commercial sur cette vente',
            ], 422);
        }

        $commission = Commission::create([
            'project_sold_id' => $validated['project_sold_id'],
            'account_type_id' => $validated['account_type_id'],
            'rate' => $validated['rate'],
            'commission_amount' => $commissionAmount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission créée avec succès',
            'data' => $commission->load('projectSold', 'accountType.user.contact')
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/commissions/list",
     *     summary="Lister toutes les commissions avec pagination et recherche",
     *     tags={"Commissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="commercial_name", type="string", nullable=true, example="Sophie", description="Nom ou prénom du commercial"),
     *             @OA\Property(property="perPage", type="integer", example=15, description="Nombre d'éléments par page (défaut: 15)"),
     *             @OA\Property(property="page", type="integer", example=1, description="Numéro de la page")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des commissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="project_name", type="string", example="Villa Tagidor"),
     *                     @OA\Property(property="commission_amount", type="number", example=27500.00),
     *                     @OA\Property(property="total_paid", type="number", example=10000.00),
     *                     @OA\Property(property="remaining_amount", type="number", example=17500.00)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="perPage", type="integer", example=15),
     *                 @OA\Property(property="currentPage", type="integer", example=1),
     *                 @OA\Property(property="lastPage", type="integer", example=4)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('corrector', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et correctors',
            ], 403);
        }

        $validated = $request->validate([
            'commercial_name' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['perPage'] ?? 15;
        $page = $validated['page'] ?? 1;
        $commercialName = $validated['commercial_name'] ?? null;

        $query = Commission::with([
            'projectSold.project',
            'accountType.user.contact',
            'payments'
        ]);

        if ($commercialName) {
            $query->whereHas('accountType.user.contact', function ($q) use ($commercialName) {
                $q->where('firstName', 'ILIKE', '%' . $commercialName . '%')
                ->orWhere('lastName', 'ILIKE', '%' . $commercialName . '%');
            });
        }

        $commissions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $commissions->getCollection()->map(function ($commission) {
                $totalPaid = $commission->payments->sum('amount_paid');
                $remainingAmount = $commission->commission_amount - $totalPaid;

                return [
                    'id' => $commission->id,
                    'project_name' => $commission->projectSold?->project?->name,
                    'project_sold_id' => $commission->project_sold_id,
                    'commercial' => [
                        'id' => $commission->accountType?->user?->id,
                        'firstName' => $commission->accountType?->user?->contact?->firstName,
                        'lastName' => $commission->accountType?->user?->contact?->lastName,
                    ],
                    'rate' => $commission->rate,
                    'commission_amount' => $commission->commission_amount,
                    'total_paid' => round($totalPaid, 2),
                    'remaining_amount' => round($remainingAmount, 2),
                    'created_at' => $commission->created_at,
                ];
            }),
            'pagination' => [
                'total' => $commissions->total(),
                'perPage' => $commissions->perPage(),
                'currentPage' => $commissions->currentPage(),
                'lastPage' => $commissions->lastPage(),
                'from' => $commissions->firstItem(),
                'to' => $commissions->lastItem(),
            ]
        ]);
    }
    

    /**
     * @OA\Delete(
     *     path="/api/admin/commissions/{id}",
     *     summary="Supprimer une commission",
     *     tags={"Commissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Commission supprimée")
     * )
     */
    public function destroy($id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('corrector')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et correctors',
            ], 403);
        }

        $commission = Commission::findOrFail($id);

        $commission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commission supprimée',
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/commercial/my-commissions",
     *     summary="Voir toutes mes commissions (Commercial uniquement)",
     *     tags={"Commissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"paid","unpaid"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste de mes commissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="project_name", type="string", example="Villa Tagidor"),
     *                     @OA\Property(property="rate", type="number", example=5.50),
     *                     @OA\Property(property="commission_amount", type="number", example=27500.00),
     *                     @OA\Property(property="status", type="string", example="unpaid")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="total_commissions", type="number", example=150000.00),
     *                 @OA\Property(property="total_paid", type="number", example=80000.00),
     *                 @OA\Property(property="total_unpaid", type="number", example=70000.00),
     *                 @OA\Property(property="count_paid", type="integer", example=4),
     *                 @OA\Property(property="count_unpaid", type="integer", example=3)
     *             )
     *         )
     *     )
     * )
     */
    public function myCommissions(Request $request)
    {
        $user = auth()->user();

        $accountType = $user->accountType;

        if (!$accountType) {
            return response()->json([
                'success' => false,
                'message' => 'Compte type non trouvé',
            ], 404);
        }

        $query = Commission::where('account_type_id', $accountType->id)
            ->with([
                'projectSold.project',
                'projectSold',
                'payments' 
            ]);

        $commissions = $query->orderBy('created_at', 'desc')->get();

        $totalCommissions = $commissions->sum('commission_amount');
        
        $totalPaid = 0;
        foreach ($commissions as $commission) {
            $totalPaid += $commission->payments->sum('amount_paid');
        }

        return response()->json([
            'success' => true,
            'data' => $commissions->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'project_name' => $commission->projectSold?->project?->name,
                    'project_uuid' => $commission->projectSold?->project?->uuid,
                    'project_sold_id' => $commission->project_sold_id,
                    'customer_name' => $commission->projectSold?->customer_of_name,
                    'sale_amount' => $commission->projectSold?->amount,
                    'rate' => $commission->rate,
                    'commission_amount' => $commission->commission_amount,
                    'total_paid' => $commission->payments->sum('amount_paid'),
                    'remaining_amount' => $commission->commission_amount - $commission->payments->sum('amount_paid'),
                    'created_at' => $commission->created_at,
                ];
            }),
            'summary' => [
                'total_commissions' => round($totalCommissions, 2),
                'total_paid' => round($totalPaid, 2), 
                'total_remaining' => round($totalCommissions - $totalPaid, 2), 
                'count_commissions' => $commissions->count(),
            ]
        ]);
    }
}