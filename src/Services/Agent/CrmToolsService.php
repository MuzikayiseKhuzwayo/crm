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
     * Query leads by keyword, stage, relay status.
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

        $limit = min($args['limit'] ?? 5, 20);
        $results = $query->latest()->limit($limit)->get();

        return $results->map(function ($lead) {
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
     * Query tasks by filter (overdue, due_today, pending, completed).
     */
    public function queryTasks(array $args): array
    {
        $query = Task::query();

        $filter = $args['filter'] ?? 'pending';

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

        if (! empty($args['lead_id'])) {
            $leadId = $args['lead_id'];
            $lead = is_numeric($leadId) ? Lead::find($leadId) : Lead::where('lead_id', $leadId)->first();
            if ($lead) {
                $query->where('taskable_type', Lead::class)->where('taskable_id', $lead->id);
            }
        }

        $limit = min($args['limit'] ?? 10, 30);
        $tasks = $query->orderBy('due_at', 'asc')->limit($limit)->get();

        return $tasks->map(function ($task) {
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
                'linked_lead' => $linkedLead,
            ];
        })->toArray();
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
