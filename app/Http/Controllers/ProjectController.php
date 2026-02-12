<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectSold;
use App\Models\ProjectImage;  
use Illuminate\Http\Request;
use App\Models\PaymentProject;
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
        
        if ($user->hasRole('admin')) {
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
            'observations.user.contact' 
        ])->findOrFail($id);

        if (!$user->hasRole('admin') && $project->user_id !== $user->id) {
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
}