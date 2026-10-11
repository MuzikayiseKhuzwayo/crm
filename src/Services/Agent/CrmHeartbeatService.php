<?php

namespace VentureDrake\LaravelCrm\Services\Agent;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;

class CrmHeartbeatService
{
    protected CrmToolsService $tools;

    protected GeminiAgentService $gemini;

    protected TelegramBotService $telegram;

    public function __construct(
        CrmToolsService $tools,
        GeminiAgentService $gemini,
        TelegramBotService $telegram
    ) {
        $this->tools = $tools;
        $this->gemini = $gemini;
        $this->telegram = $telegram;
    }

    /**
     * Run the CRM proactive heartbeat evaluation.
     *
     * @param  bool  $forceNotify  Send briefing even if no critical issues found
     * @return array Status report of the heartbeat execution
     */
    public function runHeartbeat(bool $forceNotify = false): array
    {
        $now = Carbon::now();
        $staleDays = config('laravel-crm.agent.stale_lead_days', 3);
        $staleCutoff = $now->copy()->subDays($staleDays);

        // Vector 1: Overdue tasks tied to Leads
        $overdueLeadTasks = Task::whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->where(function ($q) {
                $q->where('taskable_type', Lead::class)
                    ->orWhere('taskable_type', (new Lead)->getMorphClass());
            })
            ->with('taskable')
            ->orderBy('due_at', 'asc')
            ->limit(10)
            ->get();

        // Vector 2: Stale active leads (stuck in active stage/relay without updates)
        $staleActiveLeads = Lead::where(function ($q) {
            $q->where('relay_status', 'active')
                ->orWhereIn('pipeline_stage_id', [2, 3]); // Contacted / Qualified
        })
            ->where('updated_at', '<', $staleCutoff)
            ->whereNull('converted_at')
            ->limit(10)
            ->get();

        // Vector 3: Leads ready to advance (completed playbook tasks, but still in early stage)
        $readyLeads = Lead::whereIn('pipeline_stage_id', [1, 2])
            ->whereHas('tasks', function ($q) {
                $q->whereNotNull('completed_at');
            })
            ->whereDoesntHave('tasks', function ($q) {
                $q->whereNull('completed_at');
            })
            ->with(['pipelineStage', 'tasks'])
            ->limit(5)
            ->get();

        // Vector 4: Relay basket health
        $activeRelayCount = Lead::where('relay_status', 'active')->count();
        $standbyRelayCount = Lead::where('relay_status', 'standby')->count();

        $criticalIssueCount = $overdueLeadTasks->count() + $staleActiveLeads->count();
        $hasActionableItems = $criticalIssueCount > 0 || $readyLeads->count() > 0;

        if (! $hasActionableItems && ! $forceNotify) {
            Log::info('CRM Heartbeat: System nominal, NO_ACTION required.');

            return [
                'status' => 'nominal',
                'action_taken' => false,
                'message' => 'No urgent tasks or stale leads detected.',
            ];
        }

        // Prepare context summary for Gemini reasoning
        $summaryPayload = [
            'timestamp' => $now->toDateTimeString(),
            'overdue_lead_tasks_count' => $overdueLeadTasks->count(),
            'sample_overdue_tasks' => $overdueLeadTasks->take(3)->map(fn ($t) => [
                'task_id' => $t->id,
                'name' => $t->name,
                'due' => $t->due_at?->diffForHumans(),
                'lead_id' => $t->taskable instanceof Lead ? $t->taskable->lead_id : null,
                'lead_url' => $t->taskable instanceof Lead ? $this->tools->getLeadUrl($t->taskable->id) : null,
                'lead_title' => $t->taskable instanceof Lead ? $t->taskable->title : null,
            ])->values()->toArray(),
            'stale_active_leads_count' => $staleActiveLeads->count(),
            'sample_stale_leads' => $staleActiveLeads->take(3)->map(fn ($l) => [
                'lead_id' => $l->lead_id,
                'url' => $this->tools->getLeadUrl($l->id),
                'title' => $l->title,
                'last_activity' => $l->updated_at?->diffForHumans(),
            ])->values()->toArray(),
            'leads_ready_for_stage_advancement' => $readyLeads->map(fn ($l) => [
                'lead_id' => $l->lead_id,
                'url' => $this->tools->getLeadUrl($l->id),
                'title' => $l->title,
                'current_stage' => $l->pipelineStage?->name,
            ])->values()->toArray(),
            'relay_basket' => [
                'active' => $activeRelayCount,
                'standby' => $standbyRelayCount,
            ],
        ];

        $prompt = 'You are running the scheduled autonomous CRM heartbeat.
Review these lead and task alerts:
'.json_encode($summaryPayload, JSON_PRETTY_PRINT).'

Generate a punchy, high-priority executive briefing for Muzi on Telegram.
Highlight:
1. Urgent tasks that require immediate outreach or resolution.
2. Any stale leads at risk of dropping off.
3. Leads ready to advance in the pipeline.
Make every lead reference a 1-click clickable Markdown link using its provided URL, e.g.: [TFA-L1013](https://crm.techfusion-alchemy.xyz/crm/leads/...) - Title.
Keep it compact, clear, and actionable with emoji indicators. If no emergencies exist, give a brief 2-bullet status update.';

        $briefing = $this->gemini->executeTurn($prompt);

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 Tasks List', 'callback_data' => 'view_tasks'],
                    ['text' => '🎯 Lead Alerts', 'callback_data' => 'view_alerts'],
                ],
            ],
        ];

        $this->telegram->sendAlertToAdmin($briefing, $replyMarkup);

        return [
            'status' => 'dispatched',
            'action_taken' => true,
            'critical_issues' => $criticalIssueCount,
            'briefing' => $briefing,
        ];
    }
}
