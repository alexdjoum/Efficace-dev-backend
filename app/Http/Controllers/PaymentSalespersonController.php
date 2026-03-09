<?php

namespace App\Http\Controllers;

use App\Models\PaymentSalesperson;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentSalespersonController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/admin/payment-salespersons",
     *     summary="Créer un paiement pour un commercial (Admin/Corrector uniquement)",
     *     tags={"Payment Salespersons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"commission_id","amount_paid"},
     *             @OA\Property(property="commission_id", type="integer", example=1),
     *             @OA\Property(property="amount_paid", type="number", format="float", example=10000.00),
     *             @OA\Property(property="note", type="string", nullable=true, example="Paiement partiel 1/3")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Paiement créé",
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
            'commission_id' => 'required|exists:commissions,id',
            'amount_paid' => 'required|numeric|min:0.01',
        ]);

        $commission = Commission::with('payments')->findOrFail($validated['commission_id']);

        $totalPaid = $commission->payments()->sum('amount_paid');

        $newTotal = $totalPaid + $validated['amount_paid'];

        if ($newTotal > $commission->commission_amount) {
            $remaining = $commission->commission_amount - $totalPaid;
            return response()->json([
                'success' => false,
                'message' => 'Le montant du paiement dépasse le montant restant',
                'details' => [
                    'commission_amount' => $commission->commission_amount,
                    'total_paid' => $totalPaid,
                    'remaining_amount' => $remaining,
                    'attempted_payment' => $validated['amount_paid'],
                ]
            ], 422);
        }

        $commercialId = $commission->accountType->user_id;

        $payment = PaymentSalesperson::create([
            'commission_id' => $validated['commission_id'],
            'commercial_id' => $commercialId,
            'amount_paid' => $validated['amount_paid'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement créé avec succès',
            'data' => $payment->load('commission', 'commercial.contact'),
            'payment_status' => [
                'total_paid' => $newTotal,
                'remaining_amount' => $commission->commission_amount - $newTotal,
                'is_fully_paid' => $newTotal >= $commission->commission_amount,
            ]
        ], 201);
    }


    /**
     * @OA\Get(
     *     path="/api/admin/commissions/{commissionId}/payments",
     *     summary="Voir tous les paiements d'une commission",
     *     tags={"Payment Salespersons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="commissionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Détails des paiements de la commission")
     * )
     */
    public function commissionPayments($commissionId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('corrector')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et correctors',
            ], 403);
        }

        $commission = Commission::with([
            'payments',
            'projectSold.project',
            'accountType.user.contact'
        ])->findOrFail($commissionId);

        $totalPaid = $commission->payments->sum('amount_paid');
        $remaining = $commission->commission_amount - $totalPaid;

        return response()->json([
            'success' => true,
            'commission' => [
                'id' => $commission->id,
                'project_name' => $commission->projectSold?->project?->name,
                'commercial' => [
                    'id' => $commission->accountType?->user?->id,
                    'firstName' => $commission->accountType?->user?->contact?->firstName,
                    'lastName' => $commission->accountType?->user?->contact?->lastName,
                ],
                'commission_amount' => $commission->commission_amount,
                'total_paid' => $totalPaid,
                'remaining_amount' => $remaining,
                'is_fully_paid' => $remaining <= 0,
            ],
            'payments' => $commission->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount_paid' => $payment->amount_paid,
                    'created_at' => $payment->created_at,
                ];
            })
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/commercial/my-payments",
     *     summary="Voir tous mes paiements (Commercial uniquement)",
     *     tags={"Payment Salespersons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Liste de mes paiements")
     * )
     */
    public function myPayments()
    {
        $user = auth()->user();

        if (!$user->hasRole('commercial')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux commerciaux',
            ], 403);
        }

        $payments = PaymentSalesperson::where('commercial_id', $user->id)
            ->with(['commission.projectSold.project'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalReceived = $payments->sum('amount_paid');

        return response()->json([
            'success' => true,
            'data' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'commission_id' => $payment->commission_id,
                    'project_name' => $payment->commission?->projectSold?->project?->name,
                    'amount_paid' => $payment->amount_paid,
                    'created_at' => $payment->created_at,
                ];
            }),
            'summary' => [
                'total_received' => round($totalReceived, 2),
                'count_payments' => $payments->count(),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/payment-salespersons/{id}",
     *     summary="Supprimer un paiement",
     *     tags={"Payment Salespersons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Paiement supprimé")
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

        $payment = PaymentSalesperson::findOrFail($id);

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paiement supprimé',
        ]);
    }
}