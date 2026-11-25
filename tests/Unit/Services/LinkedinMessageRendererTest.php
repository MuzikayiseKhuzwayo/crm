<?php

use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Services\LinkedinMessageRenderer;

test('message renderer substitutes all recipient variables from person, organization, lead, and opener', function () {
    $renderer = new LinkedinMessageRenderer;

    $organization = Organization::create(['name' => 'Acme Robotics']);
    $person = Person::create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'title' => 'VP of Engineering',
        'organization_id' => $organization->id,
    ]);
    $lead = Lead::create([
        'title' => 'Acme Robotics - VP of Engineering',
        'person_id' => $person->id,
        'organization_id' => $organization->id,
    ]);
    $campaign = LinkedinCampaign::create(['name' => 'Renderer Test']);
    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'lead_id' => $lead->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/jane-doe',
        'personalized_opener' => 'Loved your keynote on autonomous pipelines.',
    ]);

    $template = LinkedinTemplate::create([
        'name' => 'Full Variable Note',
        'type' => LinkedinTemplate::TYPE_CONNECT_NOTE,
        'body' => 'Hi {{first_name}} ({{full_name}}, {{last_name}}), {{personalized_opener}} Great work as {{title}} at {{company}}.',
    ]);

    $rendered = $renderer->render($template, $recipient);

    expect($rendered)->toBe('Hi Jane (Jane Doe, Doe), Loved your keynote on autonomous pipelines. Great work as VP of Engineering at Acme Robotics.');
});

test('message renderer applies fallback syntax when variables are missing or empty', function () {
    $renderer = new LinkedinMessageRenderer;

    $person = Person::create([
        'first_name' => 'Alex',
        'last_name' => '',
    ]);
    $campaign = LinkedinCampaign::create(['name' => 'Fallback Test']);
    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/alex-smith',
        'personalized_opener' => null,
    ]);

    $template = LinkedinTemplate::create([
        'name' => 'Fallback Note',
        'type' => LinkedinTemplate::TYPE_DM,
        'body' => 'Hi {{first_name|there}}, saw what {{company|your team}} is building! {{personalized_opener|Would love to connect.}}',
    ]);

    $rendered = $renderer->render($template, $recipient);

    expect($rendered)->toBe('Hi Alex, saw what your team is building! Would love to connect.');
});

test('message renderer enforces 300-character limit on connect_note templates', function () {
    $renderer = new LinkedinMessageRenderer;

    $person = Person::create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
    $campaign = LinkedinCampaign::create(['name' => 'Length Limit Test']);
    $recipient = LinkedinCampaignRecipient::create([
        'linkedin_campaign_id' => $campaign->id,
        'person_id' => $person->id,
        'linkedin_url' => 'https://www.linkedin.com/in/jordan-lee',
        'personalized_opener' => str_repeat('Extremely detailed personalized opener sentence. ', 8),
    ]);

    $connectTemplate = LinkedinTemplate::create([
        'name' => 'Long Connect Note',
        'type' => LinkedinTemplate::TYPE_CONNECT_NOTE,
        'body' => 'Hi {{first_name}}, {{personalized_opener}}',
    ]);

    $meta = $renderer->renderWithMetadata($connectTemplate, $recipient);

    expect($meta['truncated'])->toBeTrue()
        ->and($meta['max_length'])->toBe(300)
        ->and($meta['original_length'])->toBeGreaterThan(300)
        ->and(mb_strlen($meta['body'], 'UTF-8'))->toBeLessThanOrEqual(300)
        ->and($meta['body'])->toEndWith('...');

    // DM template with the same long text is not truncated under 8000 chars
    $dmTemplate = LinkedinTemplate::create([
        'name' => 'Long DM',
        'type' => LinkedinTemplate::TYPE_DM,
        'body' => 'Hi {{first_name}}, {{personalized_opener}}',
    ]);

    $dmMeta = $renderer->renderWithMetadata($dmTemplate, $recipient);

    expect($dmMeta['truncated'])->toBeFalse()
        ->and($dmMeta['max_length'])->toBe(8000)
        ->and(mb_strlen($dmMeta['body'], 'UTF-8'))->toBeGreaterThan(300);
});
