<?php

namespace VentureDrake\LaravelCrm\Livewire\Leads;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\AccountRelayService;
use VentureDrake\LaravelCrm\Services\SalesPlaybookService;

class LeadShow extends Component
{
    use AuthorizesRequests, Toast;

    public $lead;

    public $email;

    public $phone;

    public $address;

    public function mount(Lead $lead)
    {
        $this->lead = $lead;
        $this->email = $lead->getPrimaryEmail();
        $this->phone = $lead->getPrimaryPhone();
        $this->address = $lead->getPrimaryAddress();
    }

    #[On('task-completed')]
    #[On('task-updated')]
    public function onTaskCompleted(): void
    {
        $this->lead->refresh();
    }

    public function updateStage(int $stageId): void
    {
        $stage = PipelineStage::find($stageId);
        if ($stage) {
            $this->lead->update([
                'pipeline_stage_id' => $stage->id,
                'pipeline_id' => $stage->pipeline_id,
            ]);

            $this->success("Lead stage updated to '{$stage->name}'");
        }
    }

    public function createStageTask(string $type): void
    {
        $playbookService = app(SalesPlaybookService::class);

        // Map short alias to template key
        $templateKey = match ($type) {
            'connection_request', 'connect' => $playbookService->detectRecommendedAngle($this->lead),
            'angle_a' => 'connection_angle_a',
            'angle_b' => 'connection_angle_b',
            'angle_c' => 'connection_angle_c',
            'intro_dm', 'scenario_1' => 'scenario_1',
            'scenario_2', 'offer_kit' => 'scenario_2',
            'scenario_3', 'send_kit' => 'scenario_3',
            'scenario_4', 'follow_up', 'nudge' => 'scenario_4',
            'scenario_5', 'disconnect' => 'scenario_5',
            'schedule_call', 'call', 'call_transition' => 'call_transition',
            default => $type,
        };

        $templates = $playbookService->getTemplates();

        if (isset($templates[$templateKey])) {
            $task = $playbookService->createPlaybookTask($this->lead, $templateKey);
            $this->lead->refresh();
            $this->success("Playbook Task '{$task->name}' created with pre-filled lead details!");
            $this->dispatch('select-activity-tab', tab: 'tasks');
            $this->dispatch('task-added');
            $this->dispatch('activity-logged');

            return;
        }

        // Fallback for legacy custom stage tasks
        $taskConfigs = [
            'conduct_call' => [
                'name' => 'Conduct Discovery Meeting',
                'description' => 'Host discovery call, take call notes, and validate budget & requirements.',
                'due_in_days' => 2,
            ],
            'send_proposal' => [
                'name' => 'Prepare & Send Formal Proposal / Quote',
                'description' => 'Draft proposal or quote and send to decision maker.',
                'due_in_days' => 2,
            ],
        ];

        if (isset($taskConfigs[$type])) {
            $config = $taskConfigs[$type];
            $task = Task::create([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => $config['name'],
                'description' => $config['description'],
                'taskable_type' => get_class($this->lead),
                'taskable_id' => $this->lead->id,
                'due_at' => now()->addDays($config['due_in_days']),
                'user_owner_id' => auth()->id() ?: ($this->lead->user_owner_id ?: 1),
                'user_assigned_id' => auth()->id() ?: ($this->lead->user_assigned_id ?: 1),
            ]);

            $this->lead->activities()->create([
                'causeable_type' => auth()->user() ? auth()->user()->getMorphClass() : null,
                'causeable_id' => auth()->id(),
                'timelineable_type' => $this->lead->getMorphClass(),
                'timelineable_id' => $this->lead->id,
                'recordable_type' => $task->getMorphClass(),
                'recordable_id' => $task->id,
            ]);

            $this->success("Task '{$config['name']}' created!");
            $this->dispatch('select-activity-tab', tab: 'tasks');
            $this->dispatch('task-added');
            $this->dispatch('activity-logged');
        }
    }

    public function getPlaybookProperty(): array
    {
        $playbookService = app(SalesPlaybookService::class);
        $recommendedAngle = $playbookService->detectRecommendedAngle($this->lead);
        $templates = $playbookService->getTemplates();

        $rendered = [];
        foreach ($templates as $key => $tmpl) {
            $rendered[$key] = array_merge(
                $playbookService->renderTemplate($key, $this->lead),
                ['is_recommended' => ($key === $recommendedAngle)]
            );
        }

        return [
            'id' => SalesPlaybookService::PLAYBOOK_ID,
            'name' => SalesPlaybookService::PLAYBOOK_NAME,
            'target_audience' => SalesPlaybookService::TARGET_AUDIENCE,
            'guiding_principle' => SalesPlaybookService::GUIDING_PRINCIPLE,
            'recommended_angle' => $recommendedAngle,
            'templates' => $rendered,
        ];
    }

    public function delete($id)
    {
        if ($lead = Lead::find($id)) {
            $this->authorize('delete', $lead);

            $lead->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.lead_deleted')), redirectTo: route('laravel-crm.leads.index'));
        }
    }

    public function getPipelineStagesProperty()
    {
        $pipeline = $this->lead->pipeline ?: Pipeline::where('model', get_class(new Lead))->first();

        if ($pipeline) {
            return $pipeline->pipelineStages()->orderBy('order', 'asc')->get();
        }

        return PipelineStage::orderBy('order', 'asc')->get();
    }

    public function getCompanyOutreachProperty(): ?array
    {
        return $this->lead->organization?->outreachSummary($this->lead->id);
    }

    public function markCompanyDisqualified(): void
    {
        if ($this->lead->organization) {
            $this->lead->organization->markDisqualified();
            $this->lead->organization->refresh();
            $this->lead->refresh();
            $this->success("Company '{$this->lead->organization->name}' marked as Do Not Contact / Disqualified.");
        }
    }

    public function clearCompanyDisqualified(): void
    {
        if ($this->lead->organization) {
            $this->lead->organization->clearDisqualified();
            $this->lead->organization->refresh();
            $this->lead->refresh();
            $this->success("Company '{$this->lead->organization->name}' disqualification cleared.");
        }
    }

    public function initializeRelayQueue(): void
    {
        if ($this->lead->organization) {
            try {
                app(AccountRelayService::class)->initializeOrganizationBasket($this->lead->organization);
                $this->lead->refresh();
                $this->success("Account Relay Basket initialized for {$this->lead->organization->name}.");
            } catch (\Throwable $e) {
                Log::error('initializeRelayQueue failed: '.$e->getMessage());
                $this->error('Could not initialize relay basket: '.$e->getMessage().'. Please ensure migrations have run (`php artisan migrate`).');
            }
        }
    }

    public function rotateRelay(string $reason = 'unresponsive'): void
    {
        if (! $this->lead->organization) {
            return;
        }

        try {
            $relayService = app(AccountRelayService::class);
            $nextLead = $relayService->rotateToNext($this->lead, $reason);

            $this->lead->refresh();

            if ($nextLead) {
                $this->success("Relay passed to {$nextLead->title}!");
                $this->redirect(route('laravel-crm.leads.show', $nextLead));
            } else {
                $this->warning("Account queue exhausted. No more standby leads for {$this->lead->organization->name}.");
            }
        } catch (\Throwable $e) {
            Log::error('rotateRelay failed: '.$e->getMessage());
            $this->error('Could not rotate relay: '.$e->getMessage().'. Please ensure migrations have run (`php artisan migrate`).');
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.leads.lead-show');
    }
}
