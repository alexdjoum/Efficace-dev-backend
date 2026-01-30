<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
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
            'amount' => 'required|numeric|min:0',
            'amount_to_perceive' => 'nullable|numeric|min:0',
            'status' => 'required|in:published,unpublished',
            'accepted' => 'sometimes|in:0,1',
            'description' => 'nullable|string',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:png,jpg,jpeg|max:5120',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:pdf,mp4,zip,dwg|max:51200',
        ]);

        $amountToPerceive = $validated['amount_to_perceive'] ?? 0;
        if ($amountToPerceive >= $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => __('messages.amount_to_perceive_must_be_less_than_amount'),
            ], 422);
        }

        $project = Project::create([
            'name' => $validated['name'],
            'amount' => $validated['amount'],
            'amount_to_perceive' => $amountToPerceive,
            'status' => $validated['status'],
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
        $projects = Project::with('user', 'projectImages', 'projectFiles')->get();

        return response()->json([
            'success' => true,
            'message' => __('messages.projects_list'),
            'data' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'accepted' => $project->accepted,
                    'status' => $project->status,
                    'user' => $project->user,
                    'images' => $project->projectImages->map(function ($img) {
                        return [
                            'id' => $img->id,
                            'url' => asset('storage/' . $img->path_image),
                        ];
                    }),
                    'files' => $project->projectFiles->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'url' => asset('storage/' . $file->path_file),
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

        try {
            $projectSold = $this->projectSoldService->createProjectSold(
                $projectId, 
                $validated['customer_of_name']
            );

            return response()->json([
                'success' => true,
                'message' => __('messages.project_sold_created'),
                'data' => $projectSold
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    

}