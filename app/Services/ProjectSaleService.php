<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSale;
use App\Models\IntentionToSellProject;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectSaleService
{

    public function createProjectSale($projectId, array $data)
    {
        $project = Project::findOrFail($projectId);

        ProjectSale::where('project_id', $project->id)
            ->update(['status' => 'unpublished']);

        $projectSale = ProjectSale::create([
            'project_id' => $project->id,
            'status' => $data['status'],
        ]);

        $intention = IntentionToSellProject::create([
            'project_id' => $project->id,
            'project_sale_id' => $projectSale->id,
            'amount_project' => $data['amount_project'],
            'amount_to_be_collected' => $data['amount_to_be_collected'] ?? 0,
            'is_sold' => $data['is_sold'] ?? false,
        ]);

        return $projectSale->load('intentionToSell', 'project');
    }

    public function updateProjectSaleStatus($projectId, $saleId, string $status)
    {
        $project = Project::findOrFail($projectId);
        
        $sale = ProjectSale::where('project_id', $projectId)
            ->where('id', $saleId)
            ->firstOrFail();

        $sale->update(['status' => $status]);

        return $sale->load('intentionToSell');
    }

    public function updateIntentionToSell($projectId, $saleId, array $data)
    {
        $project = Project::findOrFail($projectId);
        
        $sale = ProjectSale::where('project_id', $projectId)
            ->where('id', $saleId)
            ->firstOrFail();

        if (!$sale->intentionToSell) {
            throw new ModelNotFoundException(__('messages.intention_not_found'));
        }

        $sale->intentionToSell->update($data);

        return $sale->load('intentionToSell');
    }

    public function deleteProjectSale($projectId, $saleId)
    {
        $project = Project::findOrFail($projectId);
        
        $sale = ProjectSale::where('project_id', $projectId)
            ->where('id', $saleId)
            ->firstOrFail();

        if ($sale->intentionToSell) {
            $sale->intentionToSell->delete();
        }

        $sale->delete();

        return true;
    }
}