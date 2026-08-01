<?php

namespace VentureDrake\LaravelCrm\Livewire\People;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Person;

class PersonShow extends Component
{
    use AuthorizesRequests, Toast;

    public Person $person;

    public function delete($id)
    {
        if ($person = Person::find($id)) {
            $this->authorize('delete', $person);

            $person->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.person_deleted')), redirectTo: route('laravel-crm.people.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.people.person-show');
    }
}
