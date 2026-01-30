<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSold;

class ProjectSoldService
{

    public function createProjectSold($projectId, string $customerOfName)
    {
        $project = Project::findOrFail($projectId);

        if ($project->amount_to_perceive <= 0) {
            throw new \Exception(__('messages.no_amount_to_perceive'));
        }

        $projectSold = ProjectSold::create([
            'project_id' => $project->id,
            'amount' => $project->amount,
            'amount_received' => $project->amount_to_perceive, 
            'customer_of_name' => $customerOfName,
        ]);

        $project->update([
            'amount_to_perceive' => 0,
            'status' => 'unpublished', 
        ]);

        return $projectSold->load('project');
    }

    public function getProjectSolds($projectId)
    {
        $project = Project::findOrFail($projectId);
        
        return $project->projectSolds()->with('project')->get();
    }

    public function deleteProjectSold($projectId, $soldId)
    {
        $project = Project::findOrFail($projectId);
        
        $sold = ProjectSold::where('project_id', $projectId)
            ->where('id', $soldId)
            ->firstOrFail();

        $sold->delete();

        return true;
    }
}