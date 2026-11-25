<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits\HasSequenceAndSafetyForm;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;

class LinkedinCampaignCreate extends Component
{
    use AuthorizesRequests;
    use HasSequenceAndSafetyForm;
    use Toast;

    public function mount(): void
    {
        $this->timezone = (string) (config('laravel-crm.timezone', 'Africa/Johannesburg') ?: 'Africa/Johannesburg');
        $this->initDefaultSequenceSteps();
    }

    protected function rules(): array
    {
        return array_merge(
            $this->safetyValidationRules(),
            $this->sequenceValidationRules(),
            [
                'status' => 'required|in:'.implode(',', [
                    LinkedinCampaign::STATUS_DRAFT,
                    LinkedinCampaign::STATUS_ACTIVE,
                ]),
            ]
        );
    }

    public function save()
    {
        $this->authorize('create', LinkedinCampaign::class);

        $this->validate();

        $campaign = LinkedinCampaign::create([
            'name' => $this->name,
            'status' => $this->status,
            'sequence_steps' => $this->normalizedSequenceSteps(),
            'daily_connect_limit' => $this->daily_connect_limit,
            'daily_dm_limit' => $this->daily_dm_limit,
            'weekly_connect_limit' => $this->weekly_connect_limit,
            'min_delay_seconds' => $this->min_delay_seconds,
            'max_delay_seconds' => $this->max_delay_seconds,
            'active_days' => array_values(array_unique(array_map('intval', $this->active_days))),
            'active_start_hour' => $this->active_start_hour,
            'active_end_hour' => $this->active_end_hour,
            'timezone' => $this->timezone,
            'user_owner_id' => auth()->id(),
        ]);

        $this->success(
            ucfirst(__('laravel-crm::lang.linkedin_campaign')).' '.__('laravel-crm::lang.created'),
            redirectTo: route('laravel-crm.linkedin-campaigns.show', $campaign)
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.linkedin-campaigns.linkedin-campaign-create', [
            'templates' => $this->templates(),
            'actionOptions' => $this->stepActionOptions(),
            'weekdays' => $this->weekdayOptions(),
            'timezones' => $this->timezoneOptions(),
        ]);
    }
}
