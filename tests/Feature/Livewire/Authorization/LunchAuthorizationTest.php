<?php

use VentureDrake\LaravelCrm\Livewire\Lunches\LunchItem;
use VentureDrake\LaravelCrm\Livewire\Lunches\LunchRelated;
use VentureDrake\LaravelCrm\Models\Lunch;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the sub-item / related blades reach for
 * tables the minimal TestSchema does not ship. Overriding only render() leaves the real
 * action methods -- and the $this->authorize() guards inside them -- completely intact,
 * so these tests still exercise the production authorization path against the real
 * policies.
 */
class AuthzLunchItem extends LunchItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLunchRelated extends LunchRelated
{
    public function render()
    {
        return '<div></div>';
    }
}

itGuardsAnActivityItem(
    model: Lunch::class,
    itemComponent: AuthzLunchItem::class,
    relatedComponent: AuthzLunchRelated::class,
    property: 'lunch',
    relation: 'lunches',
    morph: 'lunchable',
    permissionSuffix: 'crm lunches',
    label: 'lunch',
);
