<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Observation;
use App\Models\ProjectFile;   
use Illuminate\Http\Request;
use App\Models\ProjectImage;  

class ObservationController extends Controller
{

    public function store(Request $request, $projectId)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'critical' => 'required|in:accepted,warning,rejected',
            
            'document_type' => 'required|in:image,pdf,dwg,bim',
            'project_image_id' => 'required_if:document_type,image|nullable|exists:project_images,id',
            'project_file_id' => 'required_if:document_type,pdf,dwg,bim|nullable|exists:project_files,id',
            
            'coordinates' => 'required|array',
            'coordinates.x' => 'required|numeric|min:0',
            'coordinates.y' => 'required|numeric|min:0',
            'coordinates.width' => 'nullable|numeric|min:0',
            'coordinates.height' => 'nullable|numeric|min:0',
            'coordinates.page' => 'nullable|integer|min:1', 
            'coordinates.layer' => 'nullable|string', 
            'coordinates.element_id' => 'nullable|string', 
        ]);

        $project = Project::findOrFail($projectId);

        if ($project->accepted) {
            return response()->json([
                'success' => false,
                'message' => __('messages.cannot_add_observation_to_accepted_project'),
            ], 422);
        }

        if ($project->amount > 0 || $project->amount_to_perceive > 0) {
            return response()->json([
                'success' => false,
                'message' => __('messages.cannot_add_observation_with_amounts_set'),
            ], 422);
        }

        if ($validated['document_type'] === 'image' && $validated['project_image_id']) {
            $image = ProjectImage::where('id', $validated['project_image_id'])
                ->where('project_id', $projectId)
                ->first();
            
            if (!$image) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.image_does_not_belong_to_project'),
                ], 422);
            }
        }

        if (in_array($validated['document_type'], ['pdf', 'dwg', 'bim']) && $validated['project_file_id']) {
            $file = ProjectFile::where('id', $validated['project_file_id'])
                ->where('project_id', $projectId)
                ->first();
            
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.file_does_not_belong_to_project'),
                ], 422);
            }
        }

        $observation = Observation::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'critical' => $validated['critical'],
            'document_type' => $validated['document_type'],
            'project_image_id' => $validated['project_image_id'] ?? null,
            'project_file_id' => $validated['project_file_id'] ?? null,
            'coordinates' => $validated['coordinates'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.observation_created'),
            'data' => $observation->load('user.contact', 'projectImage', 'projectFile')
        ], 201);
    }

    public function index($projectId)
    {
        $project = Project::findOrFail($projectId);

        $observations = $project->observations()
            ->with('user.contact', 'projectImage', 'projectFile')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $observations->map(function ($observation) {
                return [
                    'id' => $observation->id,
                    'name' => $observation->name,
                    'description' => $observation->description,
                    'critical' => $observation->critical,
                    'document_type' => $observation->document_type,
                    'coordinates' => $observation->coordinates,
                    'document' => $observation->document_type === 'image' 
                        ? [
                            'id' => $observation->projectImage?->id,
                            'url' => $observation->projectImage 
                                ? asset('storage/' . $observation->projectImage->path_image) 
                                : null,
                        ]
                        : [
                            'id' => $observation->projectFile?->id,
                            'url' => $observation->projectFile 
                                ? asset('storage/' . $observation->projectFile->path_file) 
                                : null,
                            'filename' => $observation->projectFile 
                                ? basename($observation->projectFile->path_file) 
                                : null,
                        ],
                    'user' => [
                        'id' => $observation->user->id,
                        'contact' => $observation->user->contact,
                    ],
                    'created_at' => $observation->created_at,
                    'updated_at' => $observation->updated_at,
                ];
            })
        ]);
    }

    public function update(Request $request, $projectId, $observationId)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:5000',
            'critical' => 'sometimes|in:accepted,warning,rejected',
        ]);

        $project = Project::findOrFail($projectId);
        $observation = Observation::where('project_id', $projectId)
            ->where('id', $observationId)
            ->firstOrFail();

        if ($observation->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized_action'),
            ], 403);
        }

        $observation->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('messages.observation_updated'),
            'data' => $observation->load('user.contact')
        ]);
    }

    public function destroy($projectId, $observationId)
    {
        $user = auth()->user();

        $project = Project::findOrFail($projectId);
        $observation = Observation::where('project_id', $projectId)
            ->where('id', $observationId)
            ->firstOrFail();

        if ($observation->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized_action'),
            ], 403);
        }

        $observation->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.observation_deleted'),
        ]);
    }
}