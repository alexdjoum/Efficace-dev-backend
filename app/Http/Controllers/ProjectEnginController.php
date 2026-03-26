<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Engin;
use App\Models\Project;
use App\Models\Location;
use App\Models\ProjectEngin;
use Illuminate\Http\Request;
use App\Models\EnginNotification;
use App\Models\WorkerAvailability;

class ProjectEnginController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/projects/{projectId}/assign-engin",
     *     summary="Assigner un engin à un projet avec notification (Admin/Validator)",
     *     tags={"Project Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id","start_at","end_at"},
     *             @OA\Property(property="user_id", type="integer", example=67),
     *             @OA\Property(property="task", type="string", example="Terrassement"),
     *             @OA\Property(property="start_at", type="string", format="date", example="2026-04-01"),
     *             @OA\Property(property="end_at", type="string", format="date", example="2026-04-30")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Notification envoyée à l'engin")
     * )
     */
    public function assignEngin(Request $request, $projectId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('validator', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et validators',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'task' => 'nullable|string|max:5000',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
        ]);

        $enginUser = User::with('engin.localisationWorker')->find($validated['user_id']);
        
        if (!$enginUser || !$enginUser->engin) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur n\'est pas un engin',
            ], 422);
        }

        $project = Project::with('localisationWorker')->findOrFail($projectId);

        $projectLocalisationId = $project->localisation_worker_id;
        $enginLocalisationId = $enginUser->engin->localisation_worker_id;

        if (!$projectLocalisationId || !$enginLocalisationId) {
            return response()->json([
                'success' => false,
                'message' => 'La localisation du projet ou de l\'engin n\'est pas définie',
            ], 422);
        }

        if ($projectLocalisationId !== $enginLocalisationId) {
            $projectCity = $project->localisationWorker?->name;
            $enginCity = $enginUser->engin->localisationWorker?->name;
            
            return response()->json([
                'success' => false,
                'message' => "L'engin doit être dans la même ville que le projet. Projet: {$projectCity}, Engin: {$enginCity}",
            ], 422);
        }

        $latestAvailability = WorkerAvailability::where('user_id', $validated['user_id'])
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$latestAvailability) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune disponibilité trouvée pour cet engin',
            ], 422);
        }

        $availabilityStart = \Carbon\Carbon::parse($latestAvailability->start_date);
        $availabilityEnd = $availabilityStart->copy()->addDays(15);

        $projectStart = \Carbon\Carbon::parse($validated['start_at']);
        $projectEnd = \Carbon\Carbon::parse($validated['end_at']);

        if ($projectStart->lt($availabilityStart) || $projectEnd->gt($availabilityEnd)) {
            return response()->json([
                'success' => false,
                'message' => "La période du projet ({$projectStart->format('Y-m-d')} - {$projectEnd->format('Y-m-d')}) n'est pas incluse dans la disponibilité de l'engin ({$availabilityStart->format('Y-m-d')} - {$availabilityEnd->format('Y-m-d')})",
            ], 422);
        }

        $exists = ProjectEngin::where('project_id', $projectId)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Cet engin est déjà assigné à ce projet',
            ], 422);
        }

        $projectEngin = ProjectEngin::create([
            'project_id' => $projectId,
            'user_id' => $validated['user_id'],
            'task' => $validated['task'] ?? null,
            'note' => 0,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'is_accepted' => false,
        ]);

        $message = "Vous avez été assigné au projet '{$project->name}' pour la période du {$validated['start_at']} au {$validated['end_at']}. Tâche: " . ($validated['task'] ?? 'Non spécifiée');

        EnginNotification::create([
            'project_engin_id' => $projectEngin->id,
            'user_id' => $validated['user_id'],
            'status' => 'pending',
            'message' => $message,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification d\'assignation envoyée à l\'engin',
            'data' => $projectEngin->load('user.engin', 'notification')
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/engin/my-notifications",
     *     summary="Voir mes notifications d'assignation (Engin)",
     *     tags={"Project Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Liste des notifications")
     * )
     */
    public function myNotifications()
    {
        $user = auth()->user();

        if (!$user->engin) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux engins',
            ], 403);
        }

        $notifications = EnginNotification::where('user_id', $user->id)
            ->with('projectEngin.project')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'project_engin_id' => $notif->project_engin_id,
                    'project' => [
                        'id' => $notif->projectEngin?->project?->id,
                        'name' => $notif->projectEngin?->project?->name,
                        'uuid' => $notif->projectEngin?->project?->uuid,
                    ],
                    'task' => $notif->projectEngin?->task,
                    'start_at' => $notif->projectEngin?->start_at,
                    'end_at' => $notif->projectEngin?->end_at,
                    'message' => $notif->message,
                    'status' => $notif->status,
                    'read_at' => $notif->read_at,
                    'created_at' => $notif->created_at,
                ];
            })
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/engin/notifications/{id}/respond",
     *     summary="Accepter ou refuser une notification (Engin)",
     *     tags={"Project Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"accepted","rejected"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Réponse enregistrée")
     * )
     */
    public function respondToNotification(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('engin', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux engins',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        $notification = EnginNotification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($notification->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cette notification a déjà été traitée',
            ], 422);
        }

        $notification->update([
            'status' => $validated['status'],
            'read_at' => now(),
        ]);

        if ($validated['status'] === 'accepted') {
            $notification->projectEngin->update(['is_accepted' => true]);
            $message = 'Vous avez accepté l\'assignation au projet';
        } else {
            $notification->projectEngin->delete();
            $message = 'Vous avez refusé l\'assignation au projet';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $notification
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/projects/{projectId}/assigned-engins",
     *     summary="Lister les engins assignés à un projet",
     *     tags={"Project Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Liste des engins assignés")
     * )
     */
    public function getAssignedEngins($projectId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('validator', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et validators',
            ], 403);
        }

        $project = Project::findOrFail($projectId);

        $assignedEngins = ProjectEngin::where('project_id', $projectId)
            ->with('user.engin', 'notification')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignedEngins->map(function ($pe) {
                return [
                    'id' => $pe->id,
                    'user_id' => $pe->user_id,
                    'engin' => [
                        'nameOfTheEngin' => $pe->user?->engin?->nameOfTheEngin,
                        'brandOfTheDevice' => $pe->user?->engin?->brandOfTheDevice,
                        'feature' => $pe->user?->engin?->feature,
                    ],
                    'task' => $pe->task,
                    'note' => $pe->note,
                    'start_at' => $pe->start_at,
                    'end_at' => $pe->end_at,
                    'is_accepted' => $pe->is_accepted,
                    'notification_status' => $pe->notification?->status,
                    'created_at' => $pe->created_at,
                ];
            })
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/projects/{projectId}/available-engins",
     *     summary="Lister les engins disponibles pour un projet (Admin/Validator)",
     *     tags={"Project Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des engins disponibles",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user_id", type="integer", example=67),
     *                     @OA\Property(
     *                         property="engin",
     *                         type="object",
     *                         @OA\Property(property="nameOfTheEngin", type="string", example="Bulldozer Caterpillar D6T"),
     *                         @OA\Property(property="brandOfTheDevice", type="string", example="Caterpillar"),
     *                         @OA\Property(property="feature", type="string")
     *                     ),
     *                     @OA\Property(property="city", type="string", example="Douala"),
     *                     @OA\Property(
     *                         property="latest_availability",
     *                         type="object",
     *                         @OA\Property(property="date", type="string", format="date"),
     *                         @OA\Property(property="end_date", type="string", format="date", description="Date + 15 jours")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAvailableEngins($projectId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('validator', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et validators',
            ], 403);
        }

        $project = Project::with('localisationWorker')->findOrFail($projectId);

        if (!$project->localisation_worker_id) {
            return response()->json([
                'success' => false,
                'message' => 'La localisation du projet n\'est pas définie',
            ], 422);
        }

        $projectLocalisationId = $project->localisation_worker_id;
        $projectCity = $project->localisationWorker?->name;

        $availableEngins = User::whereHas('roles', function ($query) {
                $query->where('name', 'engin')->where('guard_name', 'api');
            })
            ->whereHas('engin', function ($query) use ($projectLocalisationId) {
                $query->where('localisation_worker_id', $projectLocalisationId);
            })
            ->with([
                'engin.localisationWorker'
            ])
            ->get()
            ->filter(function ($enginUser) {
                $latestAvailability = WorkerAvailability::where('user_id', $enginUser->id)
                    ->orderBy('start_date', 'desc')
                    ->first();
                
                return $latestAvailability !== null;
            })
            ->map(function ($enginUser) {
                $latestAvailability = WorkerAvailability::where('user_id', $enginUser->id)
                    ->orderBy('start_date', 'desc')
                    ->first();

                $availabilityStart = \Carbon\Carbon::parse($latestAvailability->date);
                $availabilityEnd = $availabilityStart->copy()->addDays(15);

                return [
                    'user_id' => $enginUser->id,
                    'engin' => [
                        'nameOfTheEngin' => $enginUser->engin?->nameOfTheEngin,
                        'brandOfTheDevice' => $enginUser->engin?->brandOfTheDevice,
                        'feature' => $enginUser->engin?->feature,
                    ],
                    'city' => $enginUser->engin?->localisationWorker?->name,
                    'latest_availability' => [
                        'date' => $availabilityStart->format('Y-m-d'),
                        'end_date' => $availabilityEnd->format('Y-m-d'),
                        'days_remaining' => now()->diffInDays($availabilityEnd, false) > 0 
                            ? now()->diffInDays($availabilityEnd, false) 
                            : 0,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'project_city' => $projectCity,
            'total_available' => $availableEngins->count(),
            'data' => $availableEngins
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/projects/{projectId}/engins/{userId}",
     *     summary="Retirer un engin d'un projet (Admin/Validator)",
     *     tags={"Project Engins"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="projectId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Engin retiré du projet")
     * )
     */
    public function removeEngin($projectId, $userId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin', 'api') && !$user->hasRole('validator', 'api')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux admins et validators',
            ], 403);
        }

        $projectEngin = ProjectEngin::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        if (!$projectEngin) {
            return response()->json([
                'success' => false,
                'message' => 'Cet engin n\'est pas assigné à ce projet',
            ], 404);
        }

        if ($projectEngin->start_at || $projectEngin->end_at || $projectEngin->note > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer cet engin car des informations (date de début, date de fin ou note) ont déjà été enregistrées',
            ], 422);
        }

        $projectEngin->delete();

        EnginNotification::where('project_engin_id', $projectEngin->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Engin retiré du projet',
        ]);
    }
}