<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUser;
use App\Models\ProjectSold;
use App\Models\ProjectImage;  
use Illuminate\Http\Request;
use App\Models\PaymentProject;
use App\Models\WorkerAvailability;
use App\Services\ProjectSoldService;

class ProjectController extends Controller
{
    protected $projectSoldService;

    public function __construct(ProjectSoldService $projectSoldService)
    {
        $this->projectSoldService = $projectSoldService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'amount_to_perceive' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:published,unpublished',
            'accepted' => 'sometimes|in:0,1',
            'description' => 'nullable|string',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:png,jpg,jpeg|max:5120',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,mp4,zip,dwg|max:102400',
        ]);

        if (isset($validated['amount']) && isset($validated['amount_to_perceive'])) {
            $amountToPerceive = $validated['amount_to_perceive'];
            if ($amountToPerceive >= $validated['amount']) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.amount_to_perceive_must_be_less_than_amount'),
                ], 422);
            }
        }

        $project = Project::create([
            'name' => $validated['name'],
            'amount' => $validated['amount'] ?? 0,
            'amount_to_perceive' => $amountToPerceive ?? 0,
            'status' => $validated['status'] ?? 'unpublished',
            'accepted' => $validated['accepted'] ?? false,
            'user_id' => auth()->id(),
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('projects/images', 'public');
                
                ProjectImage::create([
                    'project_id' => $project->id,
                    'path_image' => $path,
                ]);
            }
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('projects/files', 'public');
                
                ProjectFile::create([
                    'project_id' => $project->id,
                    'path_file' => $path,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.project_created'),
            'data' => $project->load('projectImages', 'projectFiles')
        ], 201);
    }

    public function index()
    {
        $user = auth()->user();
        
        if ($user->hasRole('admin') || $user->hasRole('validator')) {
            $projects = Project::with('user', 'projectImages', 'projectFiles', 'projectSolds')->get();
        } else {
            $projects = Project::with('user', 'projectImages', 'projectFiles', 'projectSolds')
                ->where('user_id', $user->id)
                ->get();
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.projects_list'),
            'data' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'uuid' => $project->uuid,
                    'amount' => $project->amount,
                    'amount_to_perceive' => $project->amount_to_perceive,
                    'description' => $project->description,
                    'accepted' => $project->accepted,
                    'status' => $project->status,
                    'user' => [
                        'id' => $project->user->id,
                        'email' => $project->user->email,
                    ],
                    'project_solds' => $project->projectSolds,
                    'images' => $project->projectImages->map(function ($img) {
                        return [
                            'id' => $img->id,
                            'url' => url('/api/file/' . $img->path_image),
                        ];
                    }),
                    'files' => $project->projectFiles->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'url' => url('/api/file/' . $file->path_file),
                            'filename' => basename($file->path_file),
                        ];
                    }),
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                    'launch_status' => $project->launch_status, 
                    'deadline' => $project->deadline,
                    'started_at' => $project->started_at,
                    'ended_at' => $project->ended_at,
                    'localisation_worker_id' => $project->localisation_worker_id,
                ];
            })
        ]);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'accepted' => 'sometimes|boolean',
            'status' => 'sometimes|in:published,unpublished',
            'description' => 'nullable|string',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:png,jpg,jpeg|max:5120',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,mp4,zip,dwg|max:51200',
        ]);

        $project->update([
            'name' => $validated['name'] ?? $project->name,
            'accepted' => $validated['accepted'] ?? $project->accepted,
            'status' => $validated['status'] ?? $project->status,
            'description' => $validated['description'] ?? $project->description,
        ]);

        if ($request->hasFile('images')) {
            $currentImagesCount = $project->projectImages()->count();
            $newImagesCount = count($request->file('images'));
            
            if ($currentImagesCount + $newImagesCount > 5) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.max_images_exceeded', ['max' => 5]),
                ], 422);
            }

            foreach ($request->file('images') as $image) {
                $path = $image->store('projects/images', 'public');
                
                ProjectImage::create([
                    'project_id' => $project->id,
                    'path_image' => $path,
                ]);
            }
        }

        if ($request->hasFile('files')) {
            $currentFilesCount = $project->projectFiles()->count();
            $newFilesCount = count($request->file('files'));
            
            if ($currentFilesCount + $newFilesCount > 5) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.max_files_exceeded', ['max' => 5]),
                ], 422);
            }

            foreach ($request->file('files') as $file) {
                $path = $file->store('projects/files', 'public');
                
                ProjectFile::create([
                    'project_id' => $project->id,
                    'path_file' => $path,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.project_updated'),
            'data' => $project->fresh(['projectImages', 'projectFiles'])
        ]);
    }

    public function deleteImage($projectId, $imageId)
    {
        $project = Project::findOrFail($projectId);
        $image = ProjectImage::where('project_id', $projectId)
            ->where('id', $imageId)
            ->firstOrFail();

        Storage::disk('public')->delete($image->path_image);

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.image_deleted'),
        ]);
    }

    public function deleteFile($projectId, $fileId)
    {
        $project = Project::findOrFail($projectId);
        $file = ProjectFile::where('project_id', $projectId)
            ->where('id', $fileId)
            ->firstOrFail();

        Storage::disk('public')->delete($file->path_file);

        $file->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.file_deleted'),
        ]);
    }

    public function createProjectSold(Request $request, $projectId)
    {
        $validated = $request->validate([
            'customer_of_name' => 'required|string|max:255',
        ]);

        $project = Project::findOrFail($projectId);

        if (!$project->accepted) {
            return response()->json([
                'success' => false,
                'message' => __('messages.project_must_be_accepted_before_selling'),
            ], 422);
        }

        if ($project->amount <= 0 || $project->amount_to_perceive <= 0) {
            return response()->json([
                'success' => false,
                'message' => __('messages.amounts_must_be_set_before_selling'),
            ], 422);
        }

        try {
            $projectSold = $this->projectSoldService->createProjectSold(
                $projectId, 
                $validated['customer_of_name']
            );

            if ($project->status !== 'published') {
                $project->update(['status' => 'published']);
                $project->refresh(); 
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.project_sold_created'),
                'data' => [
                    'project_sold' => $projectSold,
                    'project_published' => $project->status === 'published', 
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        
        $project = Project::with([
            'user.contact',
            'projectImages',
            'projectFiles',
            'projectSolds',
            'observations.user.contact',
            'projectUsers.user.contact',  
            'projectUsers.user.accountType.lot',  
            'localisationWorker'  
        ])->findOrFail($id);

        if (!$user->hasRole('admin') && !$user->hasRole('validator') && $project->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized_access'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'uuid' => $project->uuid,
                'amount' => $project->amount,
                'amount_to_perceive' => $project->amount_to_perceive,
                'status' => $project->status,
                'description' => $project->description,
                'accepted' => $project->accepted,
                'user' => [
                    'id' => $project->user->id,
                    'contact' => $project->user->contact,
                ],
                'localisation' => [
                    'id' => $project->localisationWorker?->id,
                    'name' => $project->localisationWorker?->name,
                ],
                'launch_status' => $project->launch_status, 
                'deadline' => $project->deadline,
                'started_at' => $project->started_at,
                'ended_at' => $project->ended_at,
                'assigned_workers' => $project->projectUsers->map(function ($pu) {
                    return [
                        'id' => $pu->id,
                        'user_id' => $pu->user_id,
                        'firstName' => $pu->user->contact?->firstName,
                        'lastName' => $pu->user->contact?->lastName,
                        'email' => $pu->user->contact?->email,
                        'phoneNumber' => $pu->user->contact?->phoneNumber,
                        'lot' => $pu->user->accountType?->lot?->name,
                        'task' => $pu->task,
                        'note' => $pu->note,
                        'start_at' => $pu->start_at,
                        'end_at' => $pu->end_at,
                    ];
                }),
                'observations' => $project->observations->map(function ($obs) {
                    return [
                        'id' => $obs->id,
                        'name' => $obs->name,
                        'description' => $obs->description,
                        'critical' => $obs->critical,
                        'user' => [
                            'id' => $obs->user->id,
                            'contact' => $obs->user->contact,
                        ],
                        'created_at' => $obs->created_at,
                    ];
                }),
                'project_solds' => $project->projectSolds,
                'images' => $project->projectImages->map(fn($img) => [
                    'id' => $img->id,
                    'url' => url('/api/file/' . $img->path_image),
                ]),
                'files' => $project->projectFiles->map(fn($file) => [
                    'id' => $file->id,
                    'url' => url('/api/file/' . $file->path_file),
                    'filename' => basename($file->path_file),
                ]),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        ]);
    }
    
    public function setProjectAmounts(Request $request, $projectId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized_action'),
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'amount_to_perceive' => 'required|numeric|min:0',
        ]);

        $project = Project::findOrFail($projectId);

        if (!$project->accepted) {
            return response()->json([
                'success' => false,
                'message' => __('messages.project_not_accepted'),
            ], 422);
        }

        if ($validated['amount_to_perceive'] >= $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => __('messages.amount_to_perceive_must_be_less_than_amount'),
            ], 422);
        }

        $project->update([
            'amount' => $validated['amount'],
            'amount_to_perceive' => $validated['amount_to_perceive'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.project_amounts_updated'),
            'data' => $project->fresh()->load('user.contact', 'projectImages', 'projectFiles', 'projectSolds')
        ]);
    }

    public function acceptProject(Request $request, $projectId)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized_action'),
            ], 403);
        }

        $project = Project::findOrFail($projectId);

        $project->update(['accepted' => true]);

        return response()->json([
            'success' => true,
            'message' => __('messages.project_accepted'),
            'data' => $project
        ]);
    }


    public function publicIndex()
    {
        $projects = Project::with([
            'user.contact',
            'projectImages',
            'projectFiles',
        ])
        ->where('status', 'published')
        ->orderBy('created_at', 'desc')
        ->paginate(10); 

        return response()->json([
            'success' => true,
            'message' => __('messages.published_projects_list'),
            'data' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'uuid' => $project->uuid,
                    'amount' => $project->amount,
                    'amount_to_perceive' => $project->amount_to_perceive,
                    'description' => $project->description,
                    'accepted' => $project->accepted,
                    'status' => $project->status,
                    'user' => [
                        'id' => $project->user->id,
                        'contact' => $project->user->contact ? [
                            'firstName' => $project->user->contact->firstName,
                            'lastName' => $project->user->contact->lastName,
                        ] : null,
                    ],
                    'images' => $project->projectImages->map(function ($img) {
                        return [
                            'id' => $img->id,
                            'url' => url('/api/file/' . $img->path_image),
                        ];
                    }),
                    'files' => $project->projectFiles->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'url' => url('/api/file/' . $file->path_file),
                            'filename' => basename($file->path_file),
                        ];
                    }),
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            }),
            'pagination' => [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
            ]
        ]);
    }

    public function publicShow($id)
    {
        $project = Project::with([
            'user.contact',
            'projectImages',
            'projectFiles',
        ])
        ->where('status', 'published')
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'uuid' => $project->uuid,
                'amount' => $project->amount,
                'amount_to_perceive' => $project->amount_to_perceive,
                'status' => $project->status,
                'description' => $project->description,
                'accepted' => $project->accepted,
                'user' => [
                    'id' => $project->user->id,
                    'contact' => $project->user->contact ? [
                        'firstName' => $project->user->contact->firstName,
                        'lastName' => $project->user->contact->lastName,
                        'phoneNumber' => $project->user->contact->phoneNumber,
                    ] : null,
                ],
                'images' => $project->projectImages->map(fn($img) => [
                    'id' => $img->id,
                    'url' => url('/api/file/' . $img->path_image),
                ]),
                'files' => $project->projectFiles->map(fn($file) => [
                    'id' => $file->id,
                    'url' => url('/api/file/' . $file->path_file),
                    'filename' => basename($file->path_file),
                ]),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        ]);
    }

    public function workerStats()
    {
        $user = auth()->user();

        $projects = Project::where('user_id', $user->id)->get();

        $acceptedProjects = $projects->where('accepted', true)->count();

        $rejectedProjects = $projects->where('accepted', false)
            ->where('status', 'unpublished')
            ->count();

        $totalAmountReceived = ProjectSold::whereIn('project_id', $projects->pluck('id'))
            ->sum('amount_received');

        $totalAmount = $projects->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'accepted_projects' => $acceptedProjects,
                'rejected_projects' => $rejectedProjects,
                'total_amount_received' => $totalAmountReceived,
                'total_amount' => $totalAmount,
                'total_projects' => $projects->count(),
            ]
        ]);
    }

    public function workerAcceptedProjects()
    {
        $user = auth()->user();

        $projects = Project::where('user_id', $user->id)
            ->where('accepted', true)
            ->with(['projectSolds'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects->flatMap(function ($project) {

                if ($project->projectSolds->isEmpty()) {
                    return [[
                        'id' => $project->id,
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                        'current_amount' => $project->amount,
                        'amount_to_perceive' => $project->amount_to_perceive,
                        'total_sales' => 0,
                        'total_amount_received' => 0,
                        'amount_set_at' => $project->updated_at,
                        'sold_at' => null, 
                    ]];
                }

                return $project->projectSolds->map(function ($sold) use ($project) {
                    return [
                        'id' => $project->id,
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                        'current_amount' => $sold->amount,
                        'amount_to_perceive' => $project->amount_to_perceive,
                        'total_sales' => $project->projectSolds->count(),
                        'total_amount_received' => $sold->amount_received,
                        'amount_set_at' => $sold->created_at,
                        'sold_at' => $sold->created_at, 
                    ];
                })->toArray();
            })
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/projects/{id}/set-launch-info",
     *     summary="Définir les informations de lancement du projet",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"deadline","started_at","launch_status","localisation_worker_id"},
     *             @OA\Property(property="deadline", type="integer", example=8, description="Nombre de semaines"),
     *             @OA\Property(property="started_at", type="string", format="date", example="2026-03-01"),
     *             @OA\Property(property="launch_status", type="string", enum={"pending","ongoing","onpause","onfinish","oncancel"}, example="ongoing"),
     *             @OA\Property(property="localisation_worker_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Informations de lancement définies",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Informations de lancement mises à jour")
     *         )
     *     )
     * )
     */
    public function setLaunchInfo(Request $request, $id)
    {
        $user = auth()->user();

        // ✅ Seuls admin et validator peuvent définir ces informations
        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $validated = $request->validate([
            'deadline' => 'required|integer|min:1',
            'started_at' => 'required|date',
            'launch_status' => 'required|in:pending,ongoing,onpause,onfinish,oncancel',
            'localisation_worker_id' => 'required|exists:localisation_workers,id',
        ]);

        $project = Project::findOrFail($id);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Informations de lancement mises à jour',
            'data' => $project->fresh()
        ]);
    }


    /**
     * @OA\Patch(
     *     path="/api/projects/{id}/update-launch-info",
     *     summary="Modifier les informations de lancement du projet",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="deadline", type="integer", example=10),
     *             @OA\Property(property="started_at", type="string", format="date", example="2026-03-05"),
     *             @OA\Property(property="launch_status", type="string", enum={"pending","ongoing","onpause","onfinish","oncancel"}, example="onpause"),
     *             @OA\Property(property="localisation_worker_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Informations modifiées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Informations de lancement modifiées")
     *         )
     *     )
     * )
     */
    public function updateLaunchInfo(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $validated = $request->validate([
            'deadline' => 'sometimes|integer|min:1',
            'started_at' => 'sometimes|date',
            'launch_status' => 'sometimes|in:pending,ongoing,onpause,onfinish,oncancel',
            'localisation_worker_id' => 'sometimes|exists:localisation_workers,id',
        ]);

        $project = Project::findOrFail($id);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Informations de lancement modifiées',
            'data' => $project->fresh()
        ]);
    }


    /**
     * @OA\Delete(
     *     path="/api/projects/{id}/delete-launch-info",
     *     summary="Supprimer les informations de lancement du projet",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Informations supprimées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Informations de lancement supprimées")
     *         )
     *     )
     * )
     */
    public function deleteLaunchInfo($id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $project = Project::findOrFail($id);

        $project->update([
            'deadline' => null,
            'started_at' => null,
            'launch_status' => 'pending',
            'localisation_worker_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Informations de lancement supprimées',
            'data' => $project->fresh()
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/projects/{id}/set-end-date",
     *     summary="Définir la date de fin du projet",
     *     tags={"Projects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ended_at"},
     *             @OA\Property(property="ended_at", type="string", format="date", example="2026-05-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Date de fin définie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Date de fin définie")
     *         )
     *     )
     * )
     */
    public function setEndDate(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !$user->hasRole('validator')) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée',
            ], 403);
        }

        $validated = $request->validate([
            'ended_at' => 'required|date|after:started_at',
        ]);

        $project = Project::findOrFail($id);

        $project->update([
            'ended_at' => $validated['ended_at'],
            'launch_status' => 'onfinish',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Date de fin définie',
            'data' => $project->fresh()
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/projects/{projectId}/assign-user",
     *     summary="Assigner un utilisateur à un projet",
     *     tags={"Project Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer", example=28),
     *             @OA\Property(property="task", type="string", example="Supervision des travaux"),
     *             @OA\Property(property="note", type="integer", nullable=true, example=8),
     *             @OA\Property(property="start_at", type="string", format="date", example="2026-03-01"),
     *             @OA\Property(property="end_at", type="string", format="date", example="2026-05-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Utilisateur assigné",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Utilisateur assigné au projet")
     *         )
     *     )
     * )
     */
    public function assignUser(Request $request, $projectId)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'task' => 'nullable|string',
            'note' => 'nullable|integer|min:0|max:10',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
        ]);

        $project = Project::findOrFail($projectId);

        $exists = ProjectUser::where('project_id', $projectId)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur est déjà assigné à ce projet',
            ], 422);
        }

        $projectUser = ProjectUser::create([
            'project_id' => $projectId,
            'user_id' => $validated['user_id'],
            'task' => $validated['task'] ?? null,
            'note' => $validated['note'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $validated['end_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur assigné au projet',
            'data' => $projectUser->load('user.contact')
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/projects/{projectId}/assigned-users",
     *     summary="Lister les utilisateurs assignés à un projet",
     *     tags={"Project Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des utilisateurs assignés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function listAssignedUsers($projectId)
    {
        $project = Project::findOrFail($projectId);

        $assignedUsers = ProjectUser::where('project_id', $projectId)
            ->with('user.contact', 'user.accountType.lot')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignedUsers->map(function ($pu) {
                return [
                    'id' => $pu->id,
                    'user_id' => $pu->user_id,
                    'firstName' => $pu->user->contact?->firstName,
                    'lastName' => $pu->user->contact?->lastName,
                    'lot' => $pu->user->accountType?->lot?->name,
                    'task' => $pu->task,
                    'note' => $pu->note,
                    'start_at' => $pu->start_at,
                    'end_at' => $pu->end_at,
                ];
            })
        ]);
    }


    /**
     * @OA\Patch(
     *     path="/api/project-users/{id}",
     *     summary="Modifier l'assignation d'un utilisateur",
     *     tags={"Project Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="task", type="string", example="Nouvelle tâche"),
     *             @OA\Property(property="note", type="integer", example=9),
     *             @OA\Property(property="start_at", type="string", format="date", example="2026-03-05"),
     *             @OA\Property(property="end_at", type="string", format="date", example="2026-05-10")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignation modifiée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function updateAssignment(Request $request, $id)
    {
        $validated = $request->validate([
            'task' => 'sometimes|string',
            'note' => 'sometimes|integer|min:0|max:10',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
        ]);

        $projectUser = ProjectUser::findOrFail($id);

        $projectUser->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Assignation modifiée',
            'data' => $projectUser->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/project-users/{id}",
     *     summary="Retirer un utilisateur d'un projet",
     *     tags={"Project Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur retiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function removeUser($id)
    {
        $projectUser = ProjectUser::findOrFail($id);

        $projectUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur retiré du projet',
        ]);
    }

    public function availableWorkers($projectId)
    {
        if (!is_numeric($projectId) || $projectId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID du projet invalide',
            ], 422);
        }

        $project = Project::findOrFail($projectId);

        $today = now()->format('Y-m-d');

        $availableWorkerIds = WorkerAvailability::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('worker_availabilities')
                    ->groupBy('user_id');
            })
            ->pluck('user_id')
            ->unique();

        $workers = User::whereIn('id', $availableWorkerIds)
            ->whereHas('contact', function ($query) use ($project) {
                $query->where('localisation_worker_id', $project->localisation_worker_id);
            })
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'validator']);
            })
            ->with([
                'contact.localisationWorker',
                'accountType.lot',
                'jobWorkers',
            ])
            ->get()
            ->map(function ($worker) {

                $notes = $worker->jobWorkers
                    ->whereNotNull('note')
                    ->pluck('note')
                    ->map(fn($note) => is_numeric($note) ? (float) $note : null)
                    ->filter()
                    ->values();

                $average = $notes->isNotEmpty()
                    ? round($notes->avg(), 2)
                    : null;

                return [
                    'id' => $worker->id,
                    'firstName' => $worker->contact?->firstName,
                    'lastName' => $worker->contact?->lastName,
                    'email' => $worker->contact?->email,
                    'phone' => $worker->contact?->phoneNumber,
                    'localisation' => $worker->contact?->localisationWorker?->name,
                    'worker' => $worker->accountType?->worker,
                    'lot' => $worker->accountType?->lot?->name,
                    'years_of_experience' => $worker->accountType?->years_of_experience,
                    'total_jobs' => $worker->jobWorkers->count(),
                    'average_note' => $average,
                ];
            })
            ->sortBy([
                fn($a, $b) => match(true) {
                    $a['average_note'] !== null && $b['average_note'] !== null 
                        => $b['average_note'] <=> $a['average_note'],
                    $a['average_note'] !== null 
                        => -1,
                    $b['average_note'] !== null 
                        => 1,
                    default 
                        => strcmp($a['firstName'], $b['firstName']),
                }
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $workers
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/manager/my-projects",
     *     summary="Récupérer les projets assignés au manager avec recherche et pagination",
     *     tags={"Manager"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", nullable=true, example="Villa", description="Nom du projet ou nom/prénom d'un travailleur"),
     *             @OA\Property(property="perPage", type="integer", example=10, description="Nombre d'éléments par page"),
     *             @OA\Property(property="page", type="integer", example=1, description="Numéro de la page")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des projets",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Construction Villa")
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
    public function myProjects(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux managers',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['perPage'] ?? 10;
        $page = $validated['page'] ?? 1;
        $searchName = $validated['name'] ?? null;

        $query = ProjectUser::where('user_id', $user->id)
            ->with([
                'project.localisationWorker',
                'project.projectUsers.user.contact',
                'project.projectUsers.user.accountType.lot'
            ]);

        if ($searchName) {
            $query->where(function ($q) use ($searchName) {
                $q->whereHas('project', function ($projectQuery) use ($searchName) {
                    $projectQuery->where('name', 'ILIKE', '%' . $searchName . '%');
                })
                ->orWhereHas('project.projectUsers.user.contact', function ($contactQuery) use ($searchName) {
                    $contactQuery->where('firstName', 'ILIKE', '%' . $searchName . '%')
                        ->orWhere('lastName', 'ILIKE', '%' . $searchName . '%');
                });
            });
        }

        $projectUsers = $query->paginate($perPage, ['*'], 'page', $page);

        $projects = $projectUsers->getCollection()->map(function ($pu) {
            $project = $pu->project;
            
            return [
                'id' => $project->id,
                'name' => $project->name,
                'uuid' => $project->uuid,
                'description' => $project->description,
                'deadline' => $project->deadline,
                'started_at' => $project->started_at,
                'launch_status' => $project->launch_status,
                'ended_at' => $project->ended_at,
                'localisation' => [
                    'id' => $project->localisationWorker?->id,
                    'name' => $project->localisationWorker?->name,
                ],
                'my_task' => $pu->task,
                'my_start_at' => $pu->start_at,
                'my_end_at' => $pu->end_at,
                'assigned_workers' => $project->projectUsers->map(function ($worker) {
                    return [
                        'id' => $worker->id,
                        'user_id' => $worker->user_id,
                        'firstName' => $worker->user->contact?->firstName,
                        'lastName' => $worker->user->contact?->lastName,
                        'lot' => $worker->user->accountType?->lot?->name,
                        'task' => $worker->task,
                        'note' => $worker->note,
                        'start_at' => $worker->start_at,
                        'end_at' => $worker->end_at,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $projects,
            'pagination' => [
                'total' => $projectUsers->total(),
                'perPage' => $projectUsers->perPage(),
                'currentPage' => $projectUsers->currentPage(),
                'lastPage' => $projectUsers->lastPage(),
                'from' => $projectUsers->firstItem(),
                'to' => $projectUsers->lastItem(),
            ]
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/manager/projects/{id}/update-launch",
     *     summary="Lancer ou modifier les infos de lancement d'un projet (manager)",
     *     tags={"Manager"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="deadline", type="integer", example=10),
     *             @OA\Property(property="launch_status", type="string", enum={"pending","ongoing","onpause","onfinish","oncancel"}, example="ongoing"),
     *             @OA\Property(property="started_at", type="string", format="date", example="2026-03-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projet mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Projet mis à jour avec succès")
     *         )
     *     )
     * )
     */
    public function updateProjectLaunch(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux managers',
            ], 403);
        }

        $isAssigned = ProjectUser::where('project_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas assigné à ce projet',
            ], 403);
        }

        $validated = $request->validate([
            'deadline' => 'sometimes|integer|min:1',
            'launch_status' => 'sometimes|in:pending,ongoing,onpause,onfinish,oncancel',
            'started_at' => 'sometimes|date',
        ]);

        $project = Project::findOrFail($id);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Projet mis à jour avec succès',
            'data' => $project->fresh()
        ]);
    }


    /**
     * @OA\Patch(
     *     path="/api/manager/project-users/{id}/note",
     *     summary="Noter un travailleur sur un projet (manager)",
     *     tags={"Manager"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'assignation (project_users.id)",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note"},
     *             @OA\Property(property="note", type="integer", minimum=0, maximum=10, example=8)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Note ajoutée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Note ajoutée avec succès")
     *         )
     *     )
     * )
     */
    public function noteWorker(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée aux managers',
            ], 403);
        }

        $projectUser = ProjectUser::findOrFail($id);

        $isAssigned = ProjectUser::where('project_id', $projectUser->project_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas assigné à ce projet',
            ], 403);
        }

        if ($projectUser->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas vous noter vous-même',
            ], 422);
        }

        $validated = $request->validate([
            'note' => 'required|integer|min:0|max:10',
        ]);

        $projectUser->update(['note' => $validated['note']]);

        return response()->json([
            'success' => true,
            'message' => 'Note ajoutée avec succès',
            'data' => $projectUser->fresh()
        ]);
    }
 
}