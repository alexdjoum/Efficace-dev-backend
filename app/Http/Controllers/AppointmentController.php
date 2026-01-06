<?php

namespace App\Http\Controllers;

use App\Services\AppointmentService;
use Illuminate\Http\Request;
use App\Models\Appointment;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|date|after_or_equal:today',
            'hour' => 'required|date_format:H:i',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        try {
            $appointment = $this->appointmentService->create(
                $validated,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous créé avec succès',
                'data' => $appointment
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du rendez-vous',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $user = auth()->user();
        
        $isAdmin = $user->roles()
            ->where(function($query) {
                $query->where('name', 'Administrateur')
                    ->orWhere('slug', 'administrateur');
            })
            ->exists();
        
        \Log::info('User check:', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_admin' => $isAdmin,
            'roles' => $user->roles->pluck('name')
        ]);
        
        $query = Appointment::with([
            'product.productable',
            'user:id,email,userable_type,userable_id'
        ]);
        
        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }
        
        $appointments = $query->latest('day')
                            ->latest('hour')
                            ->get();

        $appointments->each(function ($appointment) {
            if (isset($appointment->product->productable)) {
                $appointment->product->productable->makeHidden([
                    'proposed_sites',
                    'accommodations',
                    'retail_spaces'
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => $isAdmin 
                ? 'Liste de tous les rendez-vous' 
                : 'Liste de vos rendez-vous',
            'data' => $appointments,
            'debug' => [
                'is_admin' => $isAdmin,
                'total_appointments' => $appointments->count()
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:In pending,Done',
        ]);

        try {
            $appointment = $this->appointmentService->updateStatus($id, $validated['status']);

            return response()->json([
                'success' => true,
                'message' => 'Statut du rendez-vous mis à jour',
                'data' => $appointment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}