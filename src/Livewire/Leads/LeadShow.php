<?php

namespace VentureDrake\LaravelCrm\Livewire\Leads;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Task;

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
        $taskConfigs = [
            'connection_request' => [
                'name' => 'Send a Connection Request and get accepted',
                'description' => 'Send a personalized connection request on LinkedIn and wait for acceptance.',
                'due_in_days' => 2,
            ],
            'intro_dm' => [
                'name' => 'Send an introductory DM',
                'description' => 'Send introductory message via LinkedIn DM once connection request is accepted.',
                'due_in_days' => 3,
            ],
            'schedule_call' => [
                'name' => 'Schedule Discovery / Pitch Call',
                'description' => 'Propose a 15-min discovery call via chat / email and book calendar slot.',
                'due_in_days' => 1,
            ],
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
            'follow_up' => [
                'name' => 'Follow up on Proposal / Discussion',
                'description' => 'Follow up via chat or email regarding proposal feedback.',
                'due_in_days' => 3,
            ],
        ];

        if (isset($taskConfigs[$type])) {
            $config = $taskConfigs[$type];
            Task::create([
                'external_id' => Uuid::uuid4()->toString(),
                'name' => $config['name'],
                'description' => $config['description'],
                'taskable_type' => get_class($this->lead),
                'taskable_id' => $this->lead->id,
                'due_at' => now()->addDays($config['due_in_days']),
                'user_owner_id' => auth()->id() ?: ($this->lead->user_owner_id ?: 1),
                'user_assigned_id' => auth()->id() ?: ($this->lead->user_assigned_id ?: 1),
            ]);

            $this->success("Task '{$config['name']}' created!");
            $this->dispatch('select-activity-tab', tab: 'tasks');
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

    public function render()
    {
        return view('laravel-crm::livewire.leads.lead-show');
    }
}
