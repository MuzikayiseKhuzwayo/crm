<?php

use VentureDrake\LaravelCrm\Livewire\Meetings\MeetingItem;
use VentureDrake\LaravelCrm\Livewire\Meetings\MeetingRelated;
use VentureDrake\LaravelCrm\Models\Meeting;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the sub-item / related blades reach for
 * tables the minimal TestSchema does not ship. Overriding only render() leaves the real
 * action methods -- and the $this->authorize() guards inside them -- completely intact,
 * so these tests still exercise the production authorization path against the real
 * policies.
 */
class AuthzMeetingItem extends MeetingItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzMeetingRelated extends MeetingRelated
{
    public function render()
    {
        return '<div></div>';
    }
}

itGuardsAnActivityItem(
    model: Meeting::class,
    itemComponent: AuthzMeetingItem::class,
    relatedComponent: AuthzMeetingRelated::class,
    property: 'meeting',
    relation: 'meetings',
    morph: 'meetingable',
    permissionSuffix: 'crm meetings',
    label: 'meeting',
);
