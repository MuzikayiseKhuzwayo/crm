<?php

use VentureDrake\LaravelCrm\Livewire\Calls\CallItem;
use VentureDrake\LaravelCrm\Livewire\Calls\CallRelated;
use VentureDrake\LaravelCrm\Models\Call;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the sub-item / related blades reach for
 * tables the minimal TestSchema does not ship. Overriding only render() leaves the real
 * action methods -- and the $this->authorize() guards inside them -- completely intact,
 * so these tests still exercise the production authorization path against the real
 * policies.
 */
class AuthzCallItem extends CallItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzCallRelated extends CallRelated
{
    public function render()
    {
        return '<div></div>';
    }
}

itGuardsAnActivityItem(
    model: Call::class,
    itemComponent: AuthzCallItem::class,
    relatedComponent: AuthzCallRelated::class,
    property: 'call',
    relation: 'calls',
    morph: 'callable',
    permissionSuffix: 'crm calls',
    label: 'call',
);
