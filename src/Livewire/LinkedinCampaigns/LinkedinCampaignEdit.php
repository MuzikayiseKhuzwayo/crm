<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits\HasSequenceAndSafetyForm;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;

class LinkedinCampaignEdit extends Component
{
    use AuthorizesRequests;
    use HasSequenceAndSafetyForm;
    use Toast;

    public LinkedinCampaign $campaign;

    public function mount(LinkedinCampaign $campaign): void
    {
        $this->campaign = $campaign;
        $this->fillFormFromCampaign($campaign);
    }

    protected function rules(): array
    {
        return array_merge(
            $this->safetyValidationRules(),
            $this->sequenceValidationRules()
        );
    }

    public function save()
    {
        $this->authorize('update', $this->campaign);

        $this->validate();

        $this->campaign->update([
            'name' => $this->name,
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
        ]);

        $this->success(
            ucfirst(__('laravel-crm::lang.linkedin_campaign')).' '.__('laravel-crm::lang.updated'),
            redirectTo: route('laravel-crm.linkedin-campaigns.show', $this->campaign)
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.linkedin-campaigns.linkedin-campaign-edit', [
            'templates' => $this->templates(),
            'actionOptions' => $this->stepActionOptions(),
            'weekdays' => $this->weekdayOptions(),
            'timezones' => $this->timezoneOptions(),
        ]);
    }
}
