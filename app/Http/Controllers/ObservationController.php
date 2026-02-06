<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Observation;
use Illuminate\Http\Request;

class ObservationController extends Controller
{

    public function store(Request $request, $projectId)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'critical' => 'required|in:accepted,warning,rejected',
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

        $observation = Observation::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'critical' => $validated['critical'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.observation_created'),
            'data' => $observation->load('user.contact')
        ], 201);
    }

    public function index($projectId)
    {
        $project = Project::findOrFail($projectId);

        $observations = $project->observations()
            ->with('user.contact')
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
                    'user' => [
                        'id' => $observation->user->id,
                        'contact' => $observation->user->contact ? [
                            'firstName' => $observation->user->contact->firstName,
                            'lastName' => $observation->user->contact->lastName,
                            'email' => $observation->user->contact->email,
                        ] : null,
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