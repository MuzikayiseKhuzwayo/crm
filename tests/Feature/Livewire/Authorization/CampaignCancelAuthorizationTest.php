<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\EmailCampaigns\EmailCampaignShow;
use VentureDrake\LaravelCrm\Livewire\SmsCampaigns\SmsCampaignShow;
use VentureDrake\LaravelCrm\Models\EmailCampaign;
use VentureDrake\LaravelCrm\Models\SmsCampaign;

/**
 * Render-stub subclasses -- see ChatAuthorizationTest for the rationale.
 */
class AuthzEmailCampaignShow extends EmailCampaignShow
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzSmsCampaignShow extends SmsCampaignShow
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzEmailCampaign(): EmailCampaign
{
    return EmailCampaign::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Authz email campaign',
        'subject' => 'Subject',
        'body' => 'Body',
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
    ]);
}

function authzSmsCampaign(): SmsCampaign
{
    return SmsCampaign::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Authz sms campaign',
        'body' => 'Body',
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
    ]);
}

it('forbids cancelling an email campaign without the edit permission and leaves the status intact', function () {
    $this->actingAsUserWithPermissions(['view crm email-campaigns']);
    $campaign = authzEmailCampaign();

    Livewire::test(AuthzEmailCampaignShow::class, ['campaign' => $campaign])
        ->call('cancel')
        ->assertForbidden();

    expect($campaign->fresh()->status)->toBe('scheduled');
});

it('allows cancelling an email campaign with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm email-campaigns', 'edit crm email-campaigns']);
    $campaign = authzEmailCampaign();

    Livewire::test(AuthzEmailCampaignShow::class, ['campaign' => $campaign])
        ->call('cancel')
        ->assertOk();

    expect($campaign->fresh()->status)->toBe('cancelled');
});

it('forbids cancelling an sms campaign without the edit permission and leaves the status intact', function () {
    $this->actingAsUserWithPermissions(['view crm sms-campaigns']);
    $campaign = authzSmsCampaign();

    Livewire::test(AuthzSmsCampaignShow::class, ['campaign' => $campaign])
        ->call('cancel')
        ->assertForbidden();

    expect($campaign->fresh()->status)->toBe('scheduled');
});

it('allows cancelling an sms campaign with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm sms-campaigns', 'edit crm sms-campaigns']);
    $campaign = authzSmsCampaign();

    Livewire::test(AuthzSmsCampaignShow::class, ['campaign' => $campaign])
        ->call('cancel')
        ->assertOk();

    expect($campaign->fresh()->status)->toBe('cancelled');
});
