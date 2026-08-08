<?php

use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\EmailCampaigns\EmailCampaignCreate;
use VentureDrake\LaravelCrm\Livewire\EmailCampaigns\EmailCampaignEdit;
use VentureDrake\LaravelCrm\Models\EmailCampaign;

/**
 * Render-stub subclasses -- see NoteAuthorizationTest for the rationale. Only render()
 * is replaced; every guarded action method runs for real against the real
 * EmailCampaignPolicy.
 */
class AuthzEmailCampaignCreate extends EmailCampaignCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzEmailCampaignEdit extends EmailCampaignEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzEditableEmailCampaign(): EmailCampaign
{
    return EmailCampaign::create([
        'name' => 'Original campaign',
        'subject' => 'Original subject',
        'body' => 'Original body',
        'status' => 'draft',
    ]);
}

beforeEach(function () {
    // save() ends in EmailCampaignService::schedule(), which dispatches the send job
    // for an immediate campaign. The guard is what is under test, not the delivery.
    Queue::fake();
});

/*
 * ---------------------------------------------------------------------------
 * EmailCampaignCreate::save
 * ---------------------------------------------------------------------------
 */

it('forbids creating an email campaign without the create permission and stores nothing', function () {
    $this->actingAsUserWithPermissions(['view crm email-campaigns']);
    $before = EmailCampaign::count();

    Livewire::test(AuthzEmailCampaignCreate::class)
        ->set('name', 'Denied campaign')
        ->set('subject', 'Denied subject')
        ->set('body', 'Denied body')
        ->call('save')
        ->assertForbidden();

    expect(EmailCampaign::count())->toBe($before)
        ->and(EmailCampaign::where('name', 'Denied campaign')->exists())->toBeFalse();
});

it('creates an email campaign with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm email-campaigns', 'create crm email-campaigns']);

    Livewire::test(AuthzEmailCampaignCreate::class)
        ->set('name', 'Allowed campaign')
        ->set('subject', 'Allowed subject')
        ->set('body', 'Allowed body')
        ->call('save')
        ->assertOk();

    expect(EmailCampaign::where('name', 'Allowed campaign')->exists())->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * EmailCampaignEdit::save
 * ---------------------------------------------------------------------------
 */

it('forbids updating an email campaign without the edit permission and leaves it intact', function () {
    $this->actingAsUserWithPermissions(['view crm email-campaigns']);
    $campaign = authzEditableEmailCampaign();

    Livewire::test(AuthzEmailCampaignEdit::class, ['campaign' => $campaign])
        ->set('name', 'Tampered')
        ->set('subject', 'Tampered subject')
        ->set('body', 'Tampered body')
        ->call('save')
        ->assertForbidden();

    expect($campaign->fresh()->name)->toBe('Original campaign')
        ->and($campaign->fresh()->subject)->toBe('Original subject');
});

it('updates an email campaign with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm email-campaigns', 'edit crm email-campaigns']);
    $campaign = authzEditableEmailCampaign();

    Livewire::test(AuthzEmailCampaignEdit::class, ['campaign' => $campaign])
        ->set('name', 'Renamed campaign')
        ->call('save')
        ->assertOk();

    expect($campaign->fresh()->name)->toBe('Renamed campaign');
});
