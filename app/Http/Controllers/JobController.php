<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobWorker;
use App\Models\WorkerAvailability;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class JobController extends Controller
{
    
    // public function store(Request $request)
    // {
    //     $user = auth()->user();

    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string',
    //         'start_date' => 'required|date',
    //         'end_date' => 'required|date|after:start_date',
    //         'localisation_worker_id' => 'required|exists:localisation_workers,id',
    //     ]);

    //     $job = Job::create([
    //         'user_id' => $user->id,
    //         'localisation_worker_id' => $validated['localisation_worker_id'],
    //         'name' => $validated['name'],
    //         'description' => $validated['description'] ?? null,
    //         'start_date' => $validated['start_date'],
    //         'end_date' => $validated['end_date'],
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Job créé avec succès',
    //         'data' => $job->load('localisationWorker')
    //     ], 201);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/jobs",
    //  *     summary="Lister les jobs du travailleur connecté",
    //  *     tags={"Jobs"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Liste des jobs",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="array",
    //  *                 @OA\Items(
    //  *                     @OA\Property(property="id", type="integer", example=1),
    //  *                     @OA\Property(property="name", type="string", example="Construction Villa Tagidor"),
    //  *                     @OA\Property(property="start_date", type="string", example="2026-03-10"),
    //  *                     @OA\Property(property="end_date", type="string", example="2026-06-30"),
    //  *                     @OA\Property(property="workers_found", type="integer", example=3)
    //  *                 )
    //  *             )
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function index()
    // {
    //     $user = auth()->user();

    //     $jobs = Job::where('user_id', $user->id)
    //         ->with('jobWorkers.user.contact')
    //         ->orderBy('start_date', 'asc')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $jobs->map(function ($job) {
    //             return [
    //                 'id' => $job->id,
    //                 'name' => $job->name,
    //                 'description' => $job->description,
    //                 'start_date' => $job->start_date,
    //                 'end_date' => $job->end_date,
    //                 'workers' => $job->jobWorkers->map(function ($jobWorker) {
    //                     return [
    //                         'id' => $jobWorker->user->id,
    //                         'firstName' => $jobWorker->user->contact?->firstName,
    //                         'lastName' => $jobWorker->user->contact?->lastName,
    //                         'worker' => $jobWorker->user->accountType?->worker,
    //                         'note' => $jobWorker->note,
    //                     ];
    //                 }),
    //             ];
    //         })
    //     ]);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/jobs/{id}",
    //  *     summary="Voir les détails d'un job",
    //  *     tags={"Jobs"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID du job",
    //  *         @OA\Schema(type="integer", example=1)
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Détails du job",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="object",
    //  *                 @OA\Property(property="id", type="integer", example=1),
    //  *                 @OA\Property(property="name", type="string", example="Construction Villa Tagidor"),
    //  *                 @OA\Property(property="description", type="string", example="Construction d'une villa R+2"),
    //  *                 @OA\Property(property="start_date", type="string", example="2026-03-10"),
    //  *                 @OA\Property(property="end_date", type="string", example="2026-06-30"),
    //  *                 @OA\Property(
    //  *                     property="localisation",
    //  *                     type="object",
    //  *                     @OA\Property(property="id", type="integer", example=1),
    //  *                     @OA\Property(property="name", type="string", example="Douala")
    //  *                 ),
    //  *                 @OA\Property(
    //  *                     property="workers",
    //  *                     type="array",
    //  *                     @OA\Items(
    //  *                         @OA\Property(property="id", type="integer", example=28),
    //  *                         @OA\Property(property="firstName", type="string", example="Jean"),
    //  *                         @OA\Property(property="lastName", type="string", example="Dupont"),
    //  *                         @OA\Property(property="worker", type="string", example="architect"),
    //  *                         @OA\Property(property="lot", type="string", example="Gros oeuvre"),
    //  *                         @OA\Property(property="note", type="integer", example=8)
    //  *                     )
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=404,
    //  *         description="Job non trouvé",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="Job non trouvé")
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function show($id)
    // {
    //     if (!is_numeric($id) || $id <= 0) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'ID du job invalide',
    //         ], 422);
    //     }

    //     $user = auth()->user();

    //     $job = Job::where('id', $id)
    //         ->where('user_id', $user->id)
    //         ->with([
    //             'localisationWorker',
    //             'jobWorkers.user.contact',
    //             'jobWorkers.user.accountType.lot',
    //         ])
    //         ->firstOrFail();

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'id' => $job->id,
    //             'name' => $job->name,
    //             'description' => $job->description,
    //             'start_date' => $job->start_date,
    //             'end_date' => $job->end_date,
    //             'localisation' => [
    //                 'id' => $job->localisationWorker?->id,
    //                 'name' => $job->localisationWorker?->name,
    //             ],
    //             'workers' => $job->jobWorkers->map(function ($jobWorker) {
    //                 return [
    //                     'id' => $jobWorker->user->id,
    //                     'firstName' => $jobWorker->user->contact?->firstName,
    //                     'lastName' => $jobWorker->user->contact?->lastName,
    //                     'worker' => $jobWorker->user->accountType?->worker,
    //                     'lot' => $jobWorker->user->accountType?->lot?->name,
    //                     'note' => $jobWorker->note,
    //                 ];
    //             }),
    //         ]
    //     ]);
    // }

    // /**
    //  * @OA\Patch(
    //  *     path="/api/jobs/workers/note",
    //  *     summary="Noter un travailleur dans un job",
    //  *     tags={"Jobs"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"job_id","user_id","note"},
    //  *             @OA\Property(property="job_id", type="integer", example=1),
    //  *             @OA\Property(property="user_id", type="integer", example=28),
    //  *             @OA\Property(
    //  *                 property="note",
    //  *                 type="integer",
    //  *                 minimum=0,
    //  *                 maximum=10,
    //  *                 example=8,
    //  *                 description="Note entre 0 et 10"
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Note ajoutée avec succès",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="Note ajoutée avec succès"),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="object",
    //  *                 @OA\Property(property="id", type="integer", example=1),
    //  *                 @OA\Property(property="job_id", type="integer", example=1),
    //  *                 @OA\Property(property="user_id", type="integer", example=28),
    //  *                 @OA\Property(property="note", type="integer", example=8)
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=422,
    //  *         description="Erreur de validation",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="La note doit être un entier entre 0 et 10")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=404,
    //  *         description="Travailleur non trouvé dans ce job",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="Travailleur non trouvé dans ce job")
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function addNote(Request $request)
    // {
    //     $validated = $request->validate([
    //         'job_id' => 'required|exists:jobs,id',
    //         'user_id' => 'required|exists:users,id',
    //         'note' => 'required|integer|min:0|max:10',
    //     ]);

    //     $jobWorker = JobWorker::where('job_id', $validated['job_id'])
    //         ->where('user_id', $validated['user_id'])
    //         ->firstOrFail();

    //     $jobWorker->update(['note' => $validated['note']]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Note ajoutée avec succès',
    //         'data' => $jobWorker->fresh()
    //     ]);
    // }


    // /**
    //  * @OA\Delete(
    //  *     path="/api/jobs/{id}",
    //  *     summary="Supprimer un job",
    //  *     tags={"Jobs"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="id",
    //  *         in="path",
    //  *         required=true,
    //  *         @OA\Schema(type="integer", example=1)
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Job supprimé",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="Job supprimé avec succès")
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function destroy($id)
    // {
    //     $user = auth()->user();

    //     $job = Job::where('id', $id)
    //         ->where('user_id', $user->id)
    //         ->firstOrFail();

    //     $job->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Job supprimé avec succès',
    //     ]);
    // }

    // /**
    //  * @OA\Post(
    //  *     path="/api/jobs/{jobId}/workers",
    //  *     summary="Ajouter un travailleur à un job",
    //  *     tags={"Jobs"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="jobId",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID du job",
    //  *         @OA\Schema(type="integer", example=1)
    //  *     ),
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"user_id"},
    //  *             @OA\Property(property="user_id", type="integer", example=28)
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Travailleur ajouté avec succès",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="Travailleur ajouté avec succès"),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="object",
    //  *                 @OA\Property(property="id", type="integer", example=1),
    //  *                 @OA\Property(property="job_id", type="integer", example=1),
    //  *                 @OA\Property(property="user_id", type="integer", example=28),
    //  *                 @OA\Property(property="note", type="string", nullable=true, example=null)
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=422,
    //  *         description="Erreur de validation",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(
    //  *                 property="message",
    //  *                 type="string",
    //  *                 example="Le travailleur n'est pas dans la même ville que le job"
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=404,
    //  *         description="Job ou travailleur non trouvé",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="Job non trouvé")
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function addWorker(Request $request, $jobId)
    // {
    //     if (!is_numeric($jobId) || $jobId <= 0) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'ID du job invalide',
    //         ], 422);
    //     }

    //     $validated = $request->validate([
    //         'user_id' => 'required|exists:users,id',
    //     ]);

    //     $job = Job::findOrFail($jobId);

    //     $worker = User::with('contact')->findOrFail($validated['user_id']);

    //     if ($worker->contact?->localisation_worker_id !== $job->localisation_worker_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Le travailleur n\'est pas dans la même ville que le job',
    //         ], 422);
    //     }

    //     $availability = WorkerAvailability::where('user_id', $validated['user_id'])
    //         ->where('start_date', '<=', $job->start_date)
    //         ->where('end_date', '>=', $job->end_date)
    //         ->first();

    //     if (!$availability) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Le travailleur n\'est pas disponible sur toute la période du job',
    //         ], 422);
    //     }

    //     $exists = JobWorker::where('job_id', $jobId)
    //         ->where('user_id', $validated['user_id'])
    //         ->first();

    //     if ($exists) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Ce travailleur est déjà dans ce job',
    //         ], 422);
    //     }

    //     $jobWorker = JobWorker::create([
    //         'job_id' => $jobId,
    //         'user_id' => $validated['user_id'],
    //         'note' => null,
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Travailleur ajouté avec succès',
    //         'data' => $jobWorker
    //     ], 201);
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/jobs/{jobId}/available-workers",
    //  *     summary="Lister les travailleurs disponibles pour un job",
    //  *     tags={"Jobs"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="jobId",
    //  *         in="path",
    //  *         required=true,
    //  *         description="ID du job",
    //  *         @OA\Schema(type="integer", example=1)
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Liste des travailleurs disponibles",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="array",
    //  *                 @OA\Items(
    //  *                     @OA\Property(property="id", type="integer", example=28),
    //  *                     @OA\Property(property="firstName", type="string", example="Jean"),
    //  *                     @OA\Property(property="lastName", type="string", example="Dupont"),
    //  *                     @OA\Property(property="email", type="string", example="jean@example.com"),
    //  *                     @OA\Property(property="phone", type="string", example="+237698765432"),
    //  *                     @OA\Property(property="localisation", type="string", example="Douala"),
    //  *                     @OA\Property(property="worker", type="string", example="architect"),
    //  *                     @OA\Property(property="lot", type="string", example="Gros oeuvre"),
    //  *                     @OA\Property(property="years_of_experience", type="integer", example=5),
    //  *                     @OA\Property(property="average_note", type="number", example=4.75)
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=404,
    //  *         description="Job non trouvé",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="Job non trouvé")
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function availableWorkers($jobId)
    // {
    //     if (!is_numeric($jobId) || $jobId <= 0) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'ID du job invalide',
    //         ], 422);
    //     }

    //     $job = Job::findOrFail($jobId);

    //     $availableWorkerIds = WorkerAvailability::where('start_date', '<=', $job->start_date)
    //         ->where('end_date', '>=', $job->end_date)
    //         ->pluck('user_id')
    //         ->unique();

    //     $busyWorkerIds = JobWorker::whereHas('job', function ($query) use ($job) {
    //             $query->where(function ($q) use ($job) {
    //                 $q->whereBetween('start_date', [$job->start_date, $job->end_date])
    //                     ->orWhereBetween('end_date', [$job->start_date, $job->end_date])
    //                     ->orWhere(function ($q) use ($job) {
    //                         $q->where('start_date', '<=', $job->start_date)
    //                             ->where('end_date', '>=', $job->end_date);
    //                     });
    //             });
    //         })
    //         ->pluck('user_id')
    //         ->unique();

    //     $eligibleWorkerIds = $availableWorkerIds->diff($busyWorkerIds);

    //     $workers = User::whereIn('id', $eligibleWorkerIds)
    //         ->whereHas('contact', function ($query) use ($job) {
    //             $query->where('localisation_worker_id', $job->localisation_worker_id);
    //         })
    //         ->whereHas('roles', function ($query) {
    //             $query->whereNotIn('name', ['admin', 'validator']);
    //         })
    //         ->with([
    //             'contact.localisationWorker',
    //             'accountType.lot',
    //             'jobWorkers',
    //         ])
    //         ->get()
    //         ->map(function ($worker) {

    //             $notes = $worker->jobWorkers
    //                 ->whereNotNull('note')
    //                 ->pluck('note')
    //                 ->map(fn($note) => is_numeric($note) ? (float) $note : null)
    //                 ->filter()
    //                 ->values();

    //             $average = $notes->isNotEmpty()
    //                 ? round($notes->avg(), 2)
    //                 : null;

    //             return [
    //                 'id' => $worker->id,
    //                 'firstName' => $worker->contact?->firstName,
    //                 'lastName' => $worker->contact?->lastName,
    //                 'email' => $worker->contact?->email,
    //                 'phone' => $worker->contact?->phoneNumber,
    //                 'localisation' => $worker->contact?->localisationWorker?->name,
    //                 'worker' => $worker->accountType?->worker,
    //                 'lot' => $worker->accountType?->lot?->name,
    //                 'years_of_experience' => $worker->accountType?->years_of_experience,
    //                 'total_jobs' => $worker->jobWorkers->count(),
    //                 'average_note' => $average,
    //             ];
    //         })
    //         ->sortBy([
    //             fn($a, $b) => match(true) {
    //                 $a['average_note'] !== null && $b['average_note'] !== null 
    //                     => $b['average_note'] <=> $a['average_note'],
    //                 $a['average_note'] !== null 
    //                     => -1,
    //                 $b['average_note'] !== null 
    //                     => 1,
    //                 default 
    //                     => strcmp($a['firstName'], $b['firstName']),
    //             }
    //         ])
    //         ->values();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $workers
    //     ]);
    // }
}