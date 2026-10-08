<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\LeadStatusAutomationService;
use VentureDrake\LaravelCrm\Services\SalesPlaybookService;
use VentureDrake\LaravelCrm\Services\SystemCheckService;

class AutomationController extends ApiController
{
    /**
     * Trigger automated lead pipeline stage sync based on completed tasks.
     */
    public function syncLeadStages(Request $request, LeadStatusAutomationService $automationService): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $leadFilter = $request->input('lead_id');
        $isDryRun = (bool) $request->input('dry_run', false);

        $query = Task::whereNotNull('completed_at')
            ->where(function ($q) {
                $q->where('taskable_type', Lead::class)
                    ->orWhere('taskable_type', (new Lead)->getMorphClass());
            })
            ->orderBy('completed_at', 'asc');

        if ($leadFilter) {
            $lead = is_numeric($leadFilter)
                ? Lead::find($leadFilter)
                : Lead::where('external_id', $leadFilter)->first();

            if (! $lead) {
                return response()->json(['error' => 'Lead not found'], 404);
            }

            $query->where('taskable_id', $lead->id);
        }

        $tasks = $query->get();
        $updatedLeads = [];

        foreach ($tasks as $task) {
            $lead = $automationService->resolveLeadFromTask($task);

            if (! $lead) {
                continue;
            }

            if (! $isDryRun) {
                $result = $automationService->updateLeadStatusFromTask($lead, $task);
                if ($result) {
                    $updatedLeads[$lead->external_id] = [
                        'id' => $lead->external_id,
                        'title' => $lead->title,
                        'stage' => $lead->pipelineStage?->name,
                        'status' => $lead->status,
                    ];
                }
            } else {
                $updatedLeads[$lead->external_id] = [
                    'id' => $lead->external_id,
                    'title' => $lead->title,
                    'would_sync' => true,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'dry_run' => $isDryRun,
            'total_tasks_processed' => $tasks->count(),
            'leads_synced_count' => count($updatedLeads),
            'synced_leads' => array_values($updatedLeads),
        ]);
    }

    /**
     * Trigger Sales Playbook V2 task generation for leads.
     */
    public function generatePlaybookTasks(Request $request, SalesPlaybookService $playbookService): JsonResponse
    {
        $this->authorize('create', Task::class);

        $query = Lead::whereNull('deleted_at')->with(['person', 'organization', 'pipelineStage']);

        if ($leadId = $request->input('lead_id')) {
            $query->where('external_id', $leadId)->orWhere('id', $leadId);
        }

        if ($limit = (int) $request->input('limit', 0)) {
            $query->limit($limit);
        }

        $leads = $query->get();
        $results = [];

        foreach ($leads as $lead) {
            $task = $playbookService->generateTasksForLead($lead, (bool) $request->input('force', false));
            if ($task) {
                $results[] = [
                    'lead_id' => $lead->external_id,
                    'task_id' => $task->external_id,
                    'task_name' => $task->name,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'leads_evaluated' => $leads->count(),
            'tasks_generated' => count($results),
            'generated' => $results,
        ]);
    }

    /**
     * Retrieve system health and diagnostic telemetry.
     */
    public function health(SystemCheckService $systemCheckService): JsonResponse
    {
        $alerts = $systemCheckService->check();

        return response()->json([
            'status' => count($alerts) === 0 ? 'healthy' : 'warning',
            'timestamp' => now()->toIso8601String(),
            'alerts_count' => count($alerts),
            'alerts' => $alerts,
        ]);
    }
}
