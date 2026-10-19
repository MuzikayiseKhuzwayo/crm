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
     * @param  bool  $forceNotify  Send briefing even if no critical issues found or if suppressed by mode/cooldown
     * @return array Status report of the heartbeat execution
     */
    public function runHeartbeat(bool $forceNotify = false): array
    {
        $now = Carbon::now();
        $state = $this->tools->getNotificationState();
        $mode = $state['mode'] ?? 'balanced';
        $modeMeta = CrmToolsService::PROACTIVITY_MODES[$mode] ?? CrmToolsService::PROACTIVITY_MODES['balanced'];

        // Gate 1: Silent / On-Demand mode
        if (! $forceNotify && $mode === 'silent') {
            Log::info('CRM Heartbeat: Suppressed (Silent / On-Demand mode).');

            return [
                'status' => 'suppressed_silent',
                'action_taken' => false,
                'mode' => $mode,
                'message' => 'Autonomous notifications are muted (Silent / On-Demand mode).',
            ];
        }

        // Gate 2: Active Snooze window
        if (! $forceNotify && ! empty($state['snoozed_until'])) {
            $snoozedUntil = Carbon::parse($state['snoozed_until']);
            if ($snoozedUntil->isFuture()) {
                Log::info("CRM Heartbeat: Snoozed until {$snoozedUntil->toDateTimeString()}.");

                return [
                    'status' => 'snoozed',
                    'action_taken' => false,
                    'mode' => $mode,
                    'snoozed_until' => $snoozedUntil->toDateTimeString(),
                    'message' => "Autonomous notifications are snoozed until {$snoozedUntil->toDateTimeString()}.",
                ];
            }
        }

        // Gate 3: Mode Cooldown interval
        $cooldownMinutes = $modeMeta['cooldown_minutes'] ?? 240;
        if (! $forceNotify && $cooldownMinutes && ! empty($state['last_notified_at'])) {
            $lastNotifiedAt = Carbon::parse($state['last_notified_at']);
            $nextEligibleAt = $lastNotifiedAt->copy()->addMinutes($cooldownMinutes);

            if ($nextEligibleAt->isFuture()) {
                Log::info("CRM Heartbeat: Cooldown active for mode [{$mode}] until {$nextEligibleAt->toDateTimeString()}.");

                return [
                    'status' => 'cooldown',
                    'action_taken' => false,
                    'mode' => $mode,
                    'next_eligible_at' => $nextEligibleAt->toDateTimeString(),
                    'message' => "Cooldown active for mode [{$modeMeta['label']}] until {$nextEligibleAt->toDateTimeString()}.",
                ];
            }
        }

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
                'mode' => $mode,
                'message' => 'No urgent tasks or stale leads detected.',
            ];
        }

        // Compute deterministic fingerprint of current alert items for Smart Delta suppression
        $alertHash = md5(json_encode([
            'overdue_tasks' => $overdueLeadTasks->pluck('id')->sort()->values()->toArray(),
            'stale_leads' => $staleActiveLeads->pluck('id')->sort()->values()->toArray(),
            'ready_leads' => $readyLeads->pluck('id')->sort()->values()->toArray(),
        ]));

        // Gate 4: Smart Delta deduplication in Balanced mode
        if (! $forceNotify && ($modeMeta['delta_only'] ?? false) && ! empty($state['last_alert_hash']) && $state['last_alert_hash'] === $alertHash) {
            Log::info('CRM Heartbeat: Suppressed duplicate alert (no delta change since last notification).');

            return [
                'status' => 'no_delta',
                'action_taken' => false,
                'mode' => $mode,
                'alert_hash' => $alertHash,
                'message' => 'Alert items have not changed since last notification (Smart Delta suppression).',
            ];
        }

        // Prepare context summary for Gemini reasoning
        $summaryPayload = [
            'timestamp' => $now->toDateTimeString(),
            'proactivity_mode' => $modeMeta['label'],
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

        $modePromptInstruction = match ($mode) {
            'high' => "Generate a proactive, high-energy Chief of Staff briefing for Muzi on Telegram (Mode: {$modeMeta['short_label']}).\nHighlight:\n1. Urgent tasks requiring immediate outreach.\n2. Stale leads at risk of dropping off.\n3. Leads ready to advance in the pipeline + concrete recommended next actions.",
            'low' => "Generate a calm, ultra-compact Daily Executive Digest for Muzi on Telegram (Mode: {$modeMeta['short_label']}).\nSummarize the pipeline health and top priority in at most 3 short bullet points with zero noise.",
            default => "Generate a punchy, delta-focused executive briefing for Muzi on Telegram (Mode: {$modeMeta['short_label']}).\nHighlight only the most critical overdue tasks, stale leads, and ready-to-advance leads concisely.",
        };

        $prompt = "You are running the scheduled autonomous CRM heartbeat.\n".
            "Review these lead and task alerts:\n".
            json_encode($summaryPayload, JSON_PRETTY_PRINT)."\n\n".
            $modePromptInstruction."\n".
            "Make every lead reference a 1-click clickable Markdown link using its provided URL, e.g.: [TFA-L1013](https://crm.techfusion-alchemy.xyz/crm/leads/...) - Title.\n".
            'Keep it compact, clear, and actionable with emoji indicators. If no emergencies exist, give a brief 2-bullet status update.';

        $briefing = $this->gemini->executeTurn($prompt);

        $replyMarkup = $this->tools->buildAlertInlineKeyboard();

        $this->telegram->sendAlertToAdmin($briefing, $replyMarkup);
        $this->tools->recordHeartbeatSent($alertHash);

        return [
            'status' => 'dispatched',
            'action_taken' => true,
            'mode' => $mode,
            'alert_hash' => $alertHash,
            'critical_issues' => $criticalIssueCount,
            'briefing' => $briefing,
        ];
    }

    /**
     * Handle Telegram inline button callbacks for proactivity modes and snooze controls.
     *
     * @return array{handled: bool, toast: ?string, deck: ?array}|null
     */
    public function handleTelegramControlCallback(string $data, string|int $chatId, ?int $messageId = null): ?array
    {
        $toast = null;

        if ($data === 'agent_mode_menu') {
            $toast = '🎛️ Proactivity Control Deck';
        } elseif (str_starts_with($data, 'agent_mode:')) {
            $targetMode = substr($data, strlen('agent_mode:'));
            $result = $this->tools->updateNotificationSettings([
                'mode' => $targetMode,
                'clear_snooze' => $targetMode !== 'silent',
            ]);
            $toast = "Mode switched to {$result['mode_label']}";
        } elseif (str_starts_with($data, 'agent_snooze:')) {
            $hours = (float) substr($data, strlen('agent_snooze:'));
            $result = $this->tools->updateNotificationSettings([
                'snooze_hours' => $hours,
            ]);
            $toast = $hours > 0
                ? "💤 Alerts snoozed for {$hours}h"
                : '🔔 Alerts resumed';
        } else {
            return null;
        }

        $deck = $this->tools->buildModeControlDeck($toast);

        if ($messageId) {
            $edited = $this->telegram->editMessageText($chatId, $messageId, $deck['text'], $deck['reply_markup']);
            if (! $edited) {
                $this->telegram->sendMessage($chatId, $deck['text'], $deck['reply_markup']);
            }
        } else {
            $this->telegram->sendMessage($chatId, $deck['text'], $deck['reply_markup']);
        }

        return [
            'handled' => true,
            'toast' => $toast,
            'deck' => $deck,
        ];
    }
}
