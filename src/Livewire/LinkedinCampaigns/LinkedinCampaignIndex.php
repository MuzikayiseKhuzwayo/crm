<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits\GeneratesExtensionApiToken;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;

class LinkedinCampaignIndex extends Component
{
    use AuthorizesRequests;
    use GeneratesExtensionApiToken;
    use Toast;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $status = '';

    #[Url]
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function statuses(): array
    {
        return [
            ['id' => LinkedinCampaign::STATUS_DRAFT, 'name' => ucfirst(__('laravel-crm::lang.campaign_status_draft'))],
            ['id' => LinkedinCampaign::STATUS_ACTIVE, 'name' => ucfirst(__('laravel-crm::lang.active'))],
            ['id' => LinkedinCampaign::STATUS_PAUSED, 'name' => ucfirst(__('laravel-crm::lang.paused'))],
            ['id' => LinkedinCampaign::STATUS_CIRCUIT_BROKEN, 'name' => ucfirst(__('laravel-crm::lang.linkedin_status_circuit_broken'))],
            ['id' => LinkedinCampaign::STATUS_COMPLETED, 'name' => ucfirst(__('laravel-crm::lang.completed'))],
        ];
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => ucfirst(__('laravel-crm::lang.name'))],
            ['key' => 'status', 'label' => ucfirst(__('laravel-crm::lang.status'))],
            ['key' => 'total_recipients', 'label' => ucfirst(__('laravel-crm::lang.enrolled'))],
            ['key' => 'connects_sent_count', 'label' => ucfirst(__('laravel-crm::lang.connects_sent'))],
            ['key' => 'accepted_count', 'label' => ucfirst(__('laravel-crm::lang.accepted'))],
            ['key' => 'dms_sent_count', 'label' => ucfirst(__('laravel-crm::lang.dms_sent'))],
            ['key' => 'replied_count', 'label' => ucfirst(__('laravel-crm::lang.replied'))],
        ];
    }

    public function campaigns(): LengthAwarePaginator
    {
        return LinkedinCampaign::query()
            ->when($this->search, fn (Builder $q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(25);
    }

    /**
     * @return array{
     *     worker_online: bool,
     *     last_heartbeat_at: ?Carbon,
     *     connects_today: int,
     *     daily_connect_limit: int,
     *     dms_today: int,
     *     daily_dm_limit: int,
     *     due_tasks_count: int,
     *     circuit_broken: bool,
     *     circuit_broken_reason: ?string,
     *     circuit_broken_at: ?Carbon,
     *     circuit_broken_count: int
     * }
     */
    public function telemetry(): array
    {
        $now = now();
        $activeCampaigns = LinkedinCampaign::query()
            ->where('status', LinkedinCampaign::STATUS_ACTIVE)
            ->get();

        $timezone = $activeCampaigns->first()?->timezone
            ?: config('laravel-crm.timezone', 'Africa/Johannesburg')
            ?: 'Africa/Johannesburg';

        $startOfDayUtc = Carbon::now($timezone)->startOfDay()->utc();

        $connectsToday = LinkedinOutreachEvent::query()
            ->where('action', LinkedinOutreachEvent::ACTION_CONNECT_SENT)
            ->where('occurred_at', '>=', $startOfDayUtc)
            ->count();

        $dmsToday = LinkedinOutreachEvent::query()
            ->where('action', LinkedinOutreachEvent::ACTION_DM_SENT)
            ->where('occurred_at', '>=', $startOfDayUtc)
            ->count();

        $dailyConnectLimit = $activeCampaigns->isNotEmpty()
            ? (int) $activeCampaigns->max('daily_connect_limit')
            : 15;

        $dailyDmLimit = $activeCampaigns->isNotEmpty()
            ? (int) $activeCampaigns->max('daily_dm_limit')
            : 30;

        $latestHeartbeatRaw = LinkedinCampaign::query()
            ->whereNotNull('last_worker_heartbeat_at')
            ->max('last_worker_heartbeat_at');

        $lastHeartbeat = $latestHeartbeatRaw ? Carbon::parse($latestHeartbeatRaw) : null;
        $workerOnline = $lastHeartbeat !== null && $lastHeartbeat->gt($now->copy()->subMinutes(5));

        $dueTasksCount = $activeCampaigns->isEmpty()
            ? 0
            : LinkedinCampaignRecipient::query()
                ->whereIn('linkedin_campaign_id', $activeCampaigns->pluck('id'))
                ->dueForAction($now)
                ->count();

        $circuitBrokenCampaigns = LinkedinCampaign::query()
            ->where('status', LinkedinCampaign::STATUS_CIRCUIT_BROKEN)
            ->orderByDesc('circuit_broken_at')
            ->get();

        $latestTripped = $circuitBrokenCampaigns->first();

        return [
            'worker_online' => $workerOnline,
            'last_heartbeat_at' => $lastHeartbeat,
            'connects_today' => $connectsToday,
            'daily_connect_limit' => $dailyConnectLimit,
            'dms_today' => $dmsToday,
            'daily_dm_limit' => $dailyDmLimit,
            'due_tasks_count' => $dueTasksCount,
            'circuit_broken' => $circuitBrokenCampaigns->isNotEmpty(),
            'circuit_broken_reason' => $latestTripped?->circuit_broken_reason,
            'circuit_broken_at' => $latestTripped?->circuit_broken_at,
            'circuit_broken_count' => $circuitBrokenCampaigns->count(),
        ];
    }

    public function toggleStatus(int $id): void
    {
        $campaign = LinkedinCampaign::find($id);

        if (! $campaign) {
            return;
        }

        $this->authorize('update', $campaign);

        if ($campaign->isCircuitBroken()) {
            $this->warning('Reset the circuit breaker before activating this campaign.');

            return;
        }

        $newStatus = $campaign->isActive()
            ? LinkedinCampaign::STATUS_PAUSED
            : LinkedinCampaign::STATUS_ACTIVE;

        $campaign->update(['status' => $newStatus]);

        $this->success(
            $newStatus === LinkedinCampaign::STATUS_ACTIVE
                ? "Campaign \"{$campaign->name}\" activated."
                : "Campaign \"{$campaign->name}\" paused."
        );
    }

    public function resetCircuitBreaker(bool $resume = true): void
    {
        $trippedCampaigns = LinkedinCampaign::query()
            ->where('status', LinkedinCampaign::STATUS_CIRCUIT_BROKEN)
            ->get();

        if ($trippedCampaigns->isEmpty()) {
            return;
        }

        $targetStatus = $resume
            ? LinkedinCampaign::STATUS_ACTIVE
            : LinkedinCampaign::STATUS_PAUSED;

        foreach ($trippedCampaigns as $campaign) {
            $this->authorize('update', $campaign);

            $campaign->update([
                'status' => $targetStatus,
                'circuit_broken_at' => null,
                'circuit_broken_reason' => null,
            ]);
        }

        $count = $trippedCampaigns->count();
        $actionLabel = $resume ? 'reset and resumed' : 'reset (kept paused)';
        $this->success("Circuit breaker cleared — {$count} campaign(s) {$actionLabel}.");
    }

    public function delete(int $id): void
    {
        if ($campaign = LinkedinCampaign::find($id)) {
            $this->authorize('delete', $campaign);
            $campaign->delete();
            $this->success(ucfirst(__('laravel-crm::lang.linkedin_campaign')).' '.__('laravel-crm::lang.deleted'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.linkedin-campaigns.linkedin-campaign-index', [
            'headers' => $this->headers(),
            'campaigns' => $this->campaigns(),
            'statuses' => $this->statuses(),
            'telemetry' => $this->telemetry(),
        ]);
    }
}
