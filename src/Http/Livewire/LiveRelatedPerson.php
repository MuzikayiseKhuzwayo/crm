<?php

namespace VentureDrake\LaravelCrm\Http\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Person;

class LiveRelatedPerson extends Component
{
    use AuthorizesRequests;

    public $model;

    public $people;

    public $person_id;

    public $person_name;

    public $actions;

    public function mount($model, $actions = true)
    {
        $this->model = $model;
        $this->actions = $actions;
        $this->getPeople();
    }

    public function link()
    {
        $this->authorize('update', $this->model);

        $data = $this->validate([
            'person_name' => 'required',
        ]);

        if ($this->person_id) {
            // The id arrives straight from the browser, so it names an arbitrary
            // record. Unlike the contact linkers -- which write a join row owned by
            // both sides -- this re-points the person's own organization_id, so the
            // person is authorized in its own right and not merely via the org.
            if (! $person = Person::find($this->person_id)) {
                return;
            }

            $this->authorize('update', $person);

            $person->update([
                'organization_id' => $this->model->id,
            ]);
        } else {
            $this->authorize('create', Person::class);

            $name = \VentureDrake\LaravelCrm\Http\Helpers\PersonName\firstLastFromName($data['person_name']);

            $person = Person::create([
                'external_id' => Uuid::uuid4()->toString(),
                'first_name' => $name['first_name'],
                'last_name' => $name['last_name'] ?? null,
                'user_owner_id' => auth()->user()->id,
                'organization_id' => $this->model->id,
            ]);
        }

        $this->resetFields();

        $this->getPeople();

        $this->dispatchBrowserEvent('linkedPerson');
    }

    public function remove($id)
    {
        // Scoped through the bound organization: the panel only ever unlinks its own
        // people, and resolving the id globally would let a caller detach a person
        // belonging to some other organization entirely.
        if ($person = $this->model->people()->whereKey($id)->first()) {
            $this->authorize('update', $this->model);
            $this->authorize('update', $person);

            $person->update([
                'organization_id' => null,
            ]);
        }

        $this->getPeople();

        $this->dispatchBrowserEvent('linkedPerson');
    }

    public function updatedPersonName($value)
    {
        $this->dispatchBrowserEvent('updatedNameFieldAutocomplete');
    }

    private function getPeople()
    {
        $this->people = $this->model->people()->get();
    }

    private function resetFields()
    {
        $this->reset('person_id', 'person_name');
    }

    public function render()
    {
        return view('laravel-crm::livewire.related-people');
    }
}
