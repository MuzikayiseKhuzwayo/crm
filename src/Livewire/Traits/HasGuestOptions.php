<?php

namespace VentureDrake\LaravelCrm\Livewire\Traits;

use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;

trait HasGuestOptions
{
    /**
     * Resolve candidate guest options for an activity without loading all Person records.
     * Always includes currently selected guests, entity-related people, and up to 25 recent people.
     *
     * @param  mixed  $model
     */
    protected function resolveGuestOptions($model = null, array $selectedGuestIds = []): Collection
    {
        $people = collect();

        // 1. Any currently selected guests so their tags/chips render properly
        $validGuestIds = array_values(array_filter($selectedGuestIds));
        if (! empty($validGuestIds)) {
            $selected = Person::whereIn('id', $validGuestIds)->get();
            $people = $people->merge($selected);
        }

        // 2. People directly related to the model / entity
        $resolvedModel = $model;
        if ($resolvedModel instanceof Task && $resolvedModel->taskable) {
            $resolvedModel = $resolvedModel->taskable;
        }

        if ($resolvedModel instanceof Person) {
            $people->push($resolvedModel);
        } elseif ($resolvedModel instanceof Organization) {
            $people = $people->merge($resolvedModel->people()->limit(25)->get());
        } elseif ($resolvedModel) {
            if (method_exists($resolvedModel, 'person') && $resolvedModel->person) {
                $people->push($resolvedModel->person);
            }

            if (method_exists($resolvedModel, 'organization') && $resolvedModel->organization) {
                $people = $people->merge($resolvedModel->organization->people()->limit(25)->get());
            }

            if (method_exists($resolvedModel, 'contacts')) {
                foreach ($resolvedModel->contacts as $contact) {
                    if ($contact->entityable instanceof Person) {
                        $people->push($contact->entityable);
                    }
                }
            }
        }

        // 3. If we have fewer than 25 people, add recent contacts
        if ($people->count() < 25) {
            $needed = 25 - $people->count();
            $existingIds = $people->pluck('id')->filter()->all();

            $recent = Person::query()
                ->when(! empty($existingIds), fn ($q) => $q->whereNotIn('id', $existingIds))
                ->latest()
                ->limit($needed)
                ->get();

            $people = $people->merge($recent);
        }

        return $people
            ->unique('id')
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ]);
    }
}
