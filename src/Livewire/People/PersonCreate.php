<?php

namespace VentureDrake\LaravelCrm\Livewire\People;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use VentureDrake\LaravelCrm\Livewire\People\Traits\HasPersonCommon;
use VentureDrake\LaravelCrm\Models\Person;

class PersonCreate extends Component
{
    use AuthorizesRequests, HasPersonCommon;

    public function mount()
    {
        $this->mountCommon();

        $this->addPhone();

        $this->addEmail();

        $this->addAddress();

        $this->user_owner_id = auth()->user()->id;
    }

    public function save()
    {
        $this->authorize('create', Person::class);

        $this->validate();

        // Create a request object to pass to services
        $request = \VentureDrake\LaravelCrm\Http\Helpers\PublicProperties\asRequest($this);

        $person = $this->personService->create($request);

        $person->labels()->sync($request->labels ?? []);

        $this->saveCustomFields($person);

        $this->success(
            ucfirst(trans('laravel-crm::lang.person_created')),
            redirectTo: route('laravel-crm.people.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.people.person-create');
    }
}
