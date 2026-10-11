<?php

namespace VentureDrake\LaravelCrm\Services\Agent;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\LeadStatusAutomationService;

class CrmToolsService
{
    protected LeadStatusAutomationService $leadAutomation;

    public function __construct(LeadStatusAutomationService $leadAutomation)
    {
        $this->leadAutomation = $leadAutomation;
    }

    /**
     * Resolve a Lead instance flexibly from lead_id (e.g. LD-TFA-L1542, TFA-L1542, 1542), ID, or external_id.
     */
    public function resolveLead(string|int|null $leadRef): ?Lead
    {
        if (! $leadRef) {
            return null;
        }

        if (is_numeric($leadRef)) {
            $lead = Lead::find($leadRef);
            if ($lead) {
                return $lead;
            }
        }

        // Direct match on lead_id or external_id
        $lead = Lead::where('lead_id', $leadRef)->orWhere('external_id', $leadRef)->first();
        if ($lead) {
            return $lead;
        }

        // Match with stripped LD- prefix (e.g. LD-TFA-L1542 -> TFA-L1542)
        $stripped = preg_replace('/^LD-/i', '', (string) $leadRef);
        $lead = Lead::where('lead_id', $stripped)->first();
        if ($lead) {
            return $lead;
        }

        // Match on numeric portion (e.g. 1542)
        if (preg_match('/(\d+)/', (string) $leadRef, $matches)) {
            $number = (int) $matches[1];
            $lead = Lead::where('number', $number)->first();
            if ($lead) {
                return $lead;
            }
        }

        return null;
    }

    /**
     * Get CRM overview metrics.
     */
    public function getCrmOverview(): array
    {
        $totalLeads = Lead::count();
        $totalTasks = Task::count();
        $pendingTasks = Task::whereNull('completed_at')->count();
        $overdueTasks = Task::whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->count();

        $leadsByStage = Lead::select('pipeline_stage_id', DB::raw('count(*) as total'))
            ->groupBy('pipeline_stage_id')
            ->get()
            ->mapWithKeys(function ($item) {
                $stage = PipelineStage::find($item->pipeline_stage_id);
                $name = $stage ? $stage->name : "Stage {$item->pipeline_stage_id}";

                return [$name => $item->total];
            })->toArray();

        $leadsByRelay = Lead::select('relay_status', DB::raw('count(*) as total'))
            ->whereNotNull('relay_status')
            ->groupBy('relay_status')
            ->pluck('total', 'relay_status')
            ->toArray();

        return [
            'total_leads' => $totalLeads,
            'total_tasks' => $totalTasks,
            'pending_tasks' => $pendingTasks,
            'overdue_tasks' => $overdueTasks,
            'leads_by_pipeline_stage' => $leadsByStage,
            'leads_by_relay_status' => $leadsByRelay,
        ];
    }

    /**
     * Query leads by keyword, stage, relay status, year, or date range.
     */
    public function queryLeads(array $args): array
    {
        $query = Lead::with(['person', 'organisation', 'pipelineStage']);

        if (! empty($args['search'])) {
            $search = $args['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('lead_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('person', function ($pq) use ($search) {
                        $pq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('organisation', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($args['stage'])) {
            $stageTerm = $args['stage'];
            if (is_numeric($stageTerm)) {
                $query->where('pipeline_stage_id', $stageTerm);
            } else {
                $stage = PipelineStage::where('name', 'like', "%{$stageTerm}%")->first();
                if ($stage) {
                    $query->where('pipeline_stage_id', $stage->id);
                }
            }
        }

        if (! empty($args['relay_status'])) {
            $query->where('relay_status', $args['relay_status']);
        }

        if (! empty($args['year'])) {
            $query->whereYear('created_at', (int) $args['year']);
        }

        if (! empty($args['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($args['created_before'])->endOfDay());
        }

        if (! empty($args['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($args['created_after'])->startOfDay());
        }

        $totalMatching = (clone $query)->count();
        $limit = min((int) ($args['limit'] ?? 10), 100);
        $results = $query->latest()->limit($limit)->get();

        $leads = $results->map(function ($lead) {
            return [
                'id' => $lead->id,
                'lead_id' => $lead->lead_id,
                'title' => $lead->title,
                'person' => $lead->person ? $lead->person->name : null,
                'organization' => $lead->organisation ? $lead->organisation->name : null,
                'stage' => $lead->pipelineStage ? $lead->pipelineStage->name : null,
                'relay_status' => $lead->relay_status,
                'linkedin' => $lead->linkedin,
                'created_at' => $lead->created_at?->toIso8601String(),
            ];
        })->toArray();

        return [
            'total_matching' => $totalMatching,
            'returned_count' => count($leads),
            'leads' => $leads,
        ];
    }

    /**
     * Retrieve full details of a specific lead.
     */
    public function getLeadDetails(array $args): array
    {
        $idOrRef = $args['lead_id'] ?? null;
        if (! $idOrRef) {
            return ['error' => 'lead_id is required'];
        }

        $lead = $this->resolveLead($idOrRef);

        if (! $lead) {
            return ['error' => "Lead {$idOrRef} not found"];
        }

        $lead->load(['person.emails', 'person.phones', 'organisation', 'pipelineStage']);

        $tasks = Task::where(function ($q) use ($lead) {
            $q->where('taskable_type', Lead::class)->where('taskable_id', $lead->id);
        })->orderBy('due_at', 'asc')->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'due_at' => $t->due_at?->toIso8601String(),
                'completed' => (bool) $t->completed_at,
                'completed_at' => $t->completed_at?->toIso8601String(),
            ];
        });

        return [
            'id' => $lead->id,
            'lead_id' => $lead->lead_id,
            'title' => $lead->title,
            'description' => $lead->description,
            'stage' => $lead->pipelineStage ? $lead->pipelineStage->name : null,
            'relay_status' => $lead->relay_status,
            'linkedin' => $lead->linkedin,
            'person' => $lead->person ? [
                'name' => $lead->person->name,
                'emails' => $lead->person->emails->pluck('address')->toArray(),
                'phones' => $lead->person->phones->pluck('number')->toArray(),
            ] : null,
            'organization' => $lead->organisation ? $lead->organisation->name : null,
            'tasks' => $tasks->toArray(),
        ];
    }

    /**
     * Query tasks by filter (overdue, due_today, pending, completed, all), year, or date range.
     */
    public function queryTasks(array $args): array
    {
        $query = Task::query();

        $filter = $args['filter'] ?? null;

        if ($filter === 'overdue') {
            $query->whereNull('completed_at')
                ->whereNotNull('due_at')
                ->where('due_at', '<', Carbon::now());
        } elseif ($filter === 'due_today') {
            $query->whereNull('completed_at')
                ->whereDate('due_at', Carbon::today());
        } elseif ($filter === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($filter === 'completed') {
            $query->whereNotNull('completed_at');
        }

        if (! empty($args['year'])) {
            $year = (int) $args['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('created_at', $year)
                    ->orWhereYear('completed_at', $year);
            });
        }

        if (! empty($args['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($args['created_before'])->endOfDay());
        }

        if (! empty($args['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($args['created_after'])->startOfDay());
        }

        if (! empty($args['lead_id'])) {
            $lead = $this->resolveLead($args['lead_id']);
            if ($lead) {
                $query->where('taskable_type', Lead::class)->where('taskable_id', $lead->id);
            }
        }

        $totalMatching = (clone $query)->count();
        $limit = min((int) ($args['limit'] ?? 10), 100);
        $tasks = $query->orderBy('due_at', 'asc')->limit($limit)->get();

        $taskList = $tasks->map(function ($task) {
            $linkedLead = null;
            if ($task->taskable instanceof Lead) {
                $linkedLead = [
                    'id' => $task->taskable->id,
                    'lead_id' => $task->taskable->lead_id,
                    'title' => $task->taskable->title,
                ];
            }

            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'due_at' => $task->due_at?->toIso8601String(),
                'completed' => (bool) $task->completed_at,
                'completed_at' => $task->completed_at?->toIso8601String(),
                'created_at' => $task->created_at?->toIso8601String(),
                'linked_lead' => $linkedLead,
            ];
        })->toArray();

        return [
            'total_matching' => $totalMatching,
            'returned_count' => count($taskList),
            'tasks' => $taskList,
        ];
    }

    /**
     * Create a task and attach to a lead.
     */
    public function createTask(array $args): array
    {
        $name = $args['name'] ?? null;
        if (! $name) {
            return ['error' => 'Task name is required'];
        }

        $taskableType = null;
        $taskableId = null;

        if (! empty($args['lead_id'])) {
            $lead = $this->resolveLead($args['lead_id']);

            if ($lead) {
                $taskableType = Lead::class;
                $taskableId = $lead->id;
            }
        }

        $dueAt = ! empty($args['due_at']) ? Carbon::parse($args['due_at']) : Carbon::now()->addDay();

        $task = Task::create([
            'name' => $name,
            'description' => $args['description'] ?? null,
            'due_at' => $dueAt,
            'taskable_type' => $taskableType,
            'taskable_id' => $taskableId,
            'user_assigned_id' => $args['assigned_user_id'] ?? 1,
            'user_owner_id' => 1,
        ]);

        return [
            'success' => true,
            'task_id' => $task->id,
            'name' => $task->name,
            'due_at' => $task->due_at?->toIso8601String(),
            'linked_lead_id' => $lead->lead_id ?? null,
        ];
    }

    /**
     * Mark a task as completed and trigger lead stage automation if applicable.
     */
    public function completeTask(array $args): array
    {
        $taskId = $args['task_id'] ?? null;
        if (! $taskId) {
            return ['error' => 'task_id is required'];
        }

        $task = Task::find($taskId);
        if (! $task) {
            return ['error' => "Task #{$taskId} not found"];
        }

        $task->update([
            'completed_at' => Carbon::now(),
        ]);

        // Attempt automated lead stage progression
        $advancedLead = $this->leadAutomation->handleTaskCompleted($task);

        return [
            'success' => true,
            'task_id' => $task->id,
            'name' => $task->name,
            'completed_at' => $task->completed_at->toIso8601String(),
            'lead_advanced' => $advancedLead ? [
                'lead_id' => $advancedLead->lead_id,
                'title' => $advancedLead->title,
                'new_stage' => $advancedLead->pipelineStage ? $advancedLead->pipelineStage->name : null,
            ] : null,
        ];
    }

    /**
     * Update lead pipeline stage or relay status.
     */
    public function updateLeadStage(array $args): array
    {
        $leadRef = $args['lead_id'] ?? null;
        if (! $leadRef) {
            return ['error' => 'lead_id is required'];
        }

        $lead = $this->resolveLead($leadRef);

        if (! $lead) {
            return ['error' => "Lead {$leadRef} not found"];
        }

        $updates = [];

        if (! empty($args['stage'])) {
            $stageTerm = $args['stage'];
            $stage = is_numeric($stageTerm)
                ? PipelineStage::find($stageTerm)
                : PipelineStage::where('name', 'like', "%{$stageTerm}%")->first();

            if ($stage) {
                $updates['pipeline_stage_id'] = $stage->id;
            }
        }

        if (! empty($args['relay_status'])) {
            $updates['relay_status'] = $args['relay_status'];
        }

        if (! empty($updates)) {
            $lead->update($updates);
        }

        $lead->refresh();

        return [
            'success' => true,
            'lead_id' => $lead->lead_id,
            'title' => $lead->title,
            'stage' => $lead->pipelineStage ? $lead->pipelineStage->name : null,
            'relay_status' => $lead->relay_status,
        ];
    }

    /**
     * Delete or soft-delete a lead and clean up its associated pending tasks.
     */
    public function deleteLead(array $args): array
    {
        $leadRef = $args['lead_id'] ?? null;
        if (! $leadRef) {
            return ['error' => 'lead_id is required'];
        }

        $lead = $this->resolveLead($leadRef);

        if (! $lead) {
            return ['error' => "Lead {$leadRef} not found"];
        }

        $leadId = $lead->lead_id;
        $title = $lead->title;

        // Clean up pending tasks attached to this lead
        Task::where(function ($q) use ($lead) {
            $q->where('taskable_type', Lead::class)
                ->orWhere('taskable_type', (new Lead)->getMorphClass());
        })->where('taskable_id', $lead->id)->delete();

        $lead->delete();

        return [
            'success' => true,
            'lead_id' => $leadId,
            'title' => $title,
            'status' => 'deleted',
        ];
    }

    /**
     * Bulk delete tasks by specific task IDs, year (e.g. 2023), before_date, or filter.
     */
    public function deleteTasks(array $args): array
    {
        $query = Task::query();

        if (! empty($args['task_ids'])) {
            $ids = is_array($args['task_ids']) ? $args['task_ids'] : [$args['task_ids']];
            $query->whereIn('id', $ids);
        } elseif (! empty($args['year'])) {
            $year = (int) $args['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('created_at', $year)
                    ->orWhereYear('completed_at', $year);
            });

            if (! empty($args['filter']) && $args['filter'] === 'completed') {
                $query->whereNotNull('completed_at');
            } elseif (! empty($args['filter']) && $args['filter'] === 'pending') {
                $query->whereNull('completed_at');
            }
        } elseif (! empty($args['before_date'])) {
            $query->where('created_at', '<=', Carbon::parse($args['before_date'])->endOfDay());
        } else {
            return ['error' => 'Must provide task_ids, year, or before_date to delete tasks.'];
        }

        $count = $query->count();
        if ($count === 0) {
            return [
                'success' => true,
                'deleted_count' => 0,
                'message' => 'No matching tasks found to delete.',
            ];
        }

        $query->delete();

        return [
            'success' => true,
            'deleted_count' => $count,
            'message' => "Successfully deleted {$count} task(s).",
        ];
    }

    /**
     * Bulk delete leads by specific lead IDs, year (e.g. 2023), or before_date.
     * Automatically deletes all tasks attached to those leads.
     */
    public function deleteLeads(array $args): array
    {
        $query = Lead::query();

        if (! empty($args['lead_ids'])) {
            $resolvedIds = [];
            $leadRefs = is_array($args['lead_ids']) ? $args['lead_ids'] : [$args['lead_ids']];
            foreach ($leadRefs as $ref) {
                $lead = $this->resolveLead($ref);
                if ($lead) {
                    $resolvedIds[] = $lead->id;
                }
            }
            if (empty($resolvedIds)) {
                return ['error' => 'None of the provided lead IDs could be resolved in the CRM.'];
            }
            $query->whereIn('id', $resolvedIds);
        } elseif (! empty($args['year'])) {
            $query->whereYear('created_at', (int) $args['year']);
        } elseif (! empty($args['before_date'])) {
            $query->where('created_at', '<=', Carbon::parse($args['before_date'])->endOfDay());
        } else {
            return ['error' => 'Must provide lead_ids, year, or before_date to delete leads.'];
        }

        $leads = $query->get();
        $count = $leads->count();

        if ($count === 0) {
            return [
                'success' => true,
                'deleted_count' => 0,
                'message' => 'No matching leads found to delete.',
            ];
        }

        $deletedLeadIds = $leads->pluck('lead_id')->filter()->values()->toArray();
        $internalIds = $leads->pluck('id')->toArray();

        // Clean up tasks attached to these leads
        Task::where(function ($q) {
            $q->where('taskable_type', Lead::class)
                ->orWhere('taskable_type', (new Lead)->getMorphClass());
        })->whereIn('taskable_id', $internalIds)->delete();

        // Delete leads
        Lead::whereIn('id', $internalIds)->delete();

        return [
            'success' => true,
            'deleted_count' => $count,
            'deleted_lead_ids' => array_slice($deletedLeadIds, 0, 20),
            'message' => "Successfully deleted {$count} lead(s) and their attached tasks.",
        ];
    }

    /**
     * Scan and synthesize lead & task alerts for the Heartbeat.
     */
    public function getLeadAlerts(): array
    {
        $now = Carbon::now();
        $staleDays = config('laravel-crm.agent.stale_lead_days', 3);
        $staleThreshold = $now->copy()->subDays($staleDays);

        // 1. Overdue tasks linked to leads
        $overdueTasks = Task::whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->where(function ($q) {
                $q->where('taskable_type', Lead::class)
                    ->orWhere('taskable_type', (new Lead)->getMorphClass());
            })
            ->with('taskable')
            ->orderBy('due_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'task_id' => $t->id,
                    'name' => $t->name,
                    'due_at' => $t->due_at?->diffForHumans(),
                    'lead_id' => $t->taskable instanceof Lead ? $t->taskable->lead_id : null,
                    'lead_title' => $t->taskable instanceof Lead ? $t->taskable->title : null,
                ];
            })->toArray();

        // 2. Active relay leads with no task or activity in > 3 days
        $staleRelayLeads = Lead::where('relay_status', 'active')
            ->where('updated_at', '<', $staleThreshold)
            ->limit(5)
            ->get(['id', 'lead_id', 'title', 'relay_status', 'updated_at'])
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'lead_id' => $l->lead_id,
                    'title' => $l->title,
                    'last_updated' => $l->updated_at?->diffForHumans(),
                ];
            })->toArray();

        // 3. Leads with all tasks completed (ready for next stage advancement)
        $readyToAdvanceLeads = Lead::whereHas('tasks', function ($q) {
            $q->whereNotNull('completed_at');
        })
            ->whereDoesntHave('tasks', function ($q) {
                $q->whereNull('completed_at');
            })
            ->where('pipeline_stage_id', '<', 4) // not already closed/advanced
            ->with('pipelineStage')
            ->limit(5)
            ->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'lead_id' => $l->lead_id,
                    'title' => $l->title,
                    'current_stage' => $l->pipelineStage?->name,
                ];
            })->toArray();

        return [
            'overdue_lead_tasks' => $overdueTasks,
            'stale_active_leads' => $staleRelayLeads,
            'ready_to_advance_leads' => $readyToAdvanceLeads,
            'has_urgent_alerts' => count($overdueTasks) > 0 || count($staleRelayLeads) > 0,
        ];
    }
}
