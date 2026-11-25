<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\LinkedinCampaignCreate;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\LinkedinCampaignIndex;
use VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\LinkedinCampaignShow;
use VentureDrake\LaravelCrm\Livewire\LinkedinTemplates\LinkedinTemplateCreate;
use VentureDrake\LaravelCrm\Livewire\LinkedinTemplates\LinkedinTemplateEdit;
use VentureDrake\LaravelCrm\Livewire\LinkedinTemplates\LinkedinTemplateIndex;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinOutreachEvent;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Outreach Admin',
        'email' => 'outreach_'.Str::random(6).'@example.com',
        'crm_access' => 1,
    ]);

    $this->actingAs($this->user);
});

it('creates, edits, filters, and deletes linkedin templates with variable chips and 300-char limit', function () {
    // Rejects connect_note exceeding 300 characters
    Livewire::test(LinkedinTemplateCreate::class)
        ->set('name', 'Too Long Connect Note')
        ->set('type', LinkedinTemplate::TYPE_CONNECT_NOTE)
        ->set('body', str_repeat('a', 301))
        ->call('save')
        ->assertHasErrors(['body']);

    // Inserts variable chips and creates valid template
    Livewire::test(LinkedinTemplateCreate::class)
        ->set('name', 'Apify Founder Connect')
        ->set('type', LinkedinTemplate::TYPE_CONNECT_NOTE)
        ->set('body', 'Hi')
        ->call('insertVariable', '{{first_name}}')
        ->call('insertVariable', '{{personalized_opener}}')
        ->assertSet('body', 'Hi {{first_name}} {{personalized_opener}}')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('laravel-crm.linkedin-templates.index'));

    $template = LinkedinTemplate::where('name', 'Apify Founder Connect')->first();
    expect($template)->not->toBeNull()
        ->and($template->type)->toBe(LinkedinTemplate::TYPE_CONNECT_NOTE);

    // Edit template
    Livewire::test(LinkedinTemplateEdit::class, ['template' => $template])
        ->set('name', 'Apify Founder Connect v2')
        ->set('type', LinkedinTemplate::TYPE_DM)
        ->set('body', 'Thanks for connecting, {{first_name}} at {{company}}!')
        ->call('save')
        ->assertHasNoErrors();

    expect($template->fresh()->name)->toBe('Apify Founder Connect v2')
        ->and($template->fresh()->type)->toBe(LinkedinTemplate::TYPE_DM);

    // Index search + type filter + delete
    Livewire::test(LinkedinTemplateIndex::class)
        ->set('type', LinkedinTemplate::TYPE_DM)
        ->assertSee('Apify Founder Connect v2')
        ->call('delete', $template->id);

    expect(LinkedinTemplate::find($template->id))->toBeNull();
});

it('creates a linkedin campaign with custom multi-step sequence and safety schedule', function () {
    $connectTemplate = LinkedinTemplate::create([
        'name' => 'Default Connect',
        'type' => LinkedinTemplate::TYPE_CONNECT_NOTE,
        'body' => 'Hi {{first_name}}, would love to connect!',
    ]);

    Livewire::test(LinkedinCampaignCreate::class)
        ->set('name', 'Apify SaaS CTOs Q4')
        ->set('status', LinkedinCampaign::STATUS_DRAFT)
        ->set('daily_connect_limit', 12)
        ->set('daily_dm_limit', 25)
        ->set('weekly_connect_limit', 60)
        ->set('min_delay_seconds', 200)
        ->set('max_delay_seconds', 450)
        ->set('active_days', [1, 2, 3, 4])
        ->set('active_start_hour', 9)
        ->set('active_end_hour', 17)
        ->set('timezone', 'Africa/Johannesburg')
        ->set('sequence_steps.0.template_id', $connectTemplate->id)
        ->call('addStep', 'follow_up')
        ->call('moveStepUp', 3)
        ->call('save')
        ->assertHasNoErrors();

    $campaign = LinkedinCampaign::where('name', 'Apify SaaS CTOs Q4')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->status)->toBe(LinkedinCampaign::STATUS_DRAFT)
        ->and($campaign->daily_connect_limit)->toBe(12)
        ->and($campaign->daily_dm_limit)->toBe(25)
        ->and($campaign->weekly_connect_limit)->toBe(60)
        ->and($campaign->active_days)->toBe([1, 2, 3, 4])
        ->and($campaign->sequence_steps)->toHaveCount(4)
        ->and($campaign->sequence_steps[0]['template_id'])->toBe($connectTemplate->id);
});

it('bulk enrolls filtered leads with deduplication and resolves person linkedin_url', function () {
    $apifySource = LeadSource::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Apify',
    ]);
    $otherSource = LeadSource::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Website',
    ]);
    $vipLabel = Label::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'VIP',
        'hex' => '10b981',
    ]);

    $personWithUrl = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => 'Elena',
        'last_name' => 'Vance',
        'linkedin' => 'https://www.linkedin.com/in/elena-vance-cto',
    ]);

    $leadDirect = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Acme Corp - CEO',
        'lead_source_id' => $apifySource->id,
        'relay_status' => 'active',
        'linkedin' => 'https://www.linkedin.com/in/john-acme/',
    ]);
    $leadDirect->labels()->attach($vipLabel->id);

    $leadViaPerson = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Globex - CTO',
        'lead_source_id' => $apifySource->id,
        'relay_status' => 'active',
        'person_id' => $personWithUrl->id,
    ]);
    $leadViaPerson->labels()->attach($vipLabel->id);

    // Duplicate of john-acme with query params
    $leadDuplicate = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Acme Corp Duplicate',
        'lead_source_id' => $apifySource->id,
        'relay_status' => 'active',
        'linkedin' => 'https://linkedin.com/in/JOHN-ACME?trk=public_profile',
    ]);
    $leadDuplicate->labels()->attach($vipLabel->id);

    // Non-personal /company/ URL (should be skipped)
    $leadCompanyPage = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Company Page Only',
        'lead_source_id' => $apifySource->id,
        'relay_status' => 'active',
        'linkedin' => 'https://www.linkedin.com/company/acme-inc',
    ]);
    $leadCompanyPage->labels()->attach($vipLabel->id);

    // Different source (should be filtered out)
    Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Website Lead',
        'lead_source_id' => $otherSource->id,
        'relay_status' => 'active',
        'linkedin' => 'https://www.linkedin.com/in/website-lead',
    ]);

    $campaign = LinkedinCampaign::create([
        'name' => 'Apify Outreach Wave 1',
        'status' => LinkedinCampaign::STATUS_DRAFT,
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'delay_hours' => 0, 'body' => 'Hi {{first_name}}, {{personalized_opener}}'],
        ],
    ]);

    $component = Livewire::test(LinkedinCampaignShow::class, ['campaign' => $campaign])
        ->call('openEnrollModal')
        ->set('enrollLeadSourceId', $apifySource->id)
        ->set('enrollLabelId', $vipLabel->id)
        ->set('enrollRelayStatus', 'active');

    $preview = $component->instance()->enrollmentPreview;

    expect($preview['total_matched'])->toBe(4)
        ->and($preview['enrollable_count'])->toBe(2)
        ->and($preview['duplicate_count'])->toBe(1)
        ->and($preview['invalid_url_count'])->toBe(1);

    $component->call('enrollFilteredLeads');

    $campaign->refresh();
    expect($campaign->total_recipients)->toBe(2)
        ->and($campaign->recipients()->pluck('normalized_linkedin_slug')->sort()->values()->all())
        ->toBe(['elena-vance-cto', 'john-acme']);
});

it('supports inline personalized opener editing, rendered message preview, and skip/retry in recipients queue', function () {
    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Hyperion Systems',
    ]);
    $person = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => 'Marcus',
        'last_name' => 'Vane',
        'organization_id' => $org->id,
    ]);
    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Hyperion Systems - VP Engineering',
        'person_id' => $person->id,
        'organization_id' => $org->id,
        'linkedin' => 'https://www.linkedin.com/in/marcus-vane',
    ]);

    $campaign = LinkedinCampaign::create([
        'name' => 'VIP Sequence',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'sequence_steps' => [
            ['step' => 1, 'action' => 'connect', 'delay_hours' => 0, 'body' => 'Hi {{first_name}} at {{company}}, {{personalized_opener}}'],
        ],
    ]);

    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/marcus-vane',
        'normalized_linkedin_slug' => 'marcus-vane',
        'status' => LinkedinCampaignRecipient::STATUS_QUEUED,
        'current_step' => 0,
    ]);

    Livewire::test(LinkedinCampaignShow::class, ['campaign' => $campaign])
        ->call('startEditingOpener', $recipient->id)
        ->set('editingOpener', 'saw your keynote on distributed queues!')
        ->call('saveOpener')
        ->call('openMessagePreview', $recipient->id)
        ->assertSet('showMessagePreviewModal', true)
        ->assertSet('previewRenderedBody', 'Hi Marcus at Hyperion Systems, saw your keynote on distributed queues!')
        ->call('skipRecipient', $recipient->id);

    expect($recipient->fresh()->personalized_opener)->toBe('saw your keynote on distributed queues!')
        ->and($recipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_SKIPPED);

    Livewire::test(LinkedinCampaignShow::class, ['campaign' => $campaign])
        ->call('retryRecipient', $recipient->id);

    expect($recipient->fresh()->status)->toBe(LinkedinCampaignRecipient::STATUS_QUEUED);
});

it('displays telemetry, toggles campaign status, resets circuit breaker, and generates sanctum extension token', function () {
    $activeCampaign = LinkedinCampaign::create([
        'name' => 'Active Outreach',
        'status' => LinkedinCampaign::STATUS_ACTIVE,
        'daily_connect_limit' => 15,
        'daily_dm_limit' => 30,
        'last_worker_heartbeat_at' => now()->subMinutes(2),
        'total_recipients' => 10,
        'connects_sent_count' => 8,
        'accepted_count' => 4,
        'dms_sent_count' => 3,
        'replied_count' => 2,
    ]);

    $trippedCampaign = LinkedinCampaign::create([
        'name' => 'Tripped Outreach',
        'status' => LinkedinCampaign::STATUS_CIRCUIT_BROKEN,
        'circuit_broken_at' => now()->subMinutes(10),
        'circuit_broken_reason' => 'captcha_detected',
    ]);

    LinkedinOutreachEvent::create([
        'linkedin_campaign_id' => $activeCampaign->id,
        'action' => LinkedinOutreachEvent::ACTION_CONNECT_SENT,
        'step' => 1,
        'occurred_at' => now(),
    ]);

    LinkedinOutreachEvent::create([
        'linkedin_campaign_id' => $activeCampaign->id,
        'action' => LinkedinOutreachEvent::ACTION_DM_SENT,
        'step' => 2,
        'occurred_at' => now(),
    ]);

    $index = Livewire::test(LinkedinCampaignIndex::class)
        ->assertSee('Online')
        ->assertSee('1 / 15')
        ->assertSee('1 / 30')
        ->assertSee('Circuit Breaker Tripped')
        ->assertSee('captcha_detected')
        ->assertSee('50%')
        ->assertSee('20%');

    // Toggle Active -> Paused
    $index->call('toggleStatus', $activeCampaign->id);
    expect($activeCampaign->fresh()->status)->toBe(LinkedinCampaign::STATUS_PAUSED);

    // Toggle Paused -> Active
    $index->call('toggleStatus', $activeCampaign->id);
    expect($activeCampaign->fresh()->status)->toBe(LinkedinCampaign::STATUS_ACTIVE);

    // Reset & Resume circuit breaker
    $index->call('resetCircuitBreaker', true);
    $trippedCampaign->refresh();
    expect($trippedCampaign->status)->toBe(LinkedinCampaign::STATUS_ACTIVE)
        ->and($trippedCampaign->circuit_broken_at)->toBeNull()
        ->and($trippedCampaign->circuit_broken_reason)->toBeNull();

    // Generate Sanctum extension token
    $index->set('apiTokenName', 'my-chrome-ext')
        ->call('generateExtensionToken');

    expect($index->get('generatedPlainTextToken'))->not->toBeNull()
        ->and($index->get('generatedPlainTextToken'))->toContain('|');
});
