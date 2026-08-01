<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\CustomFieldGroups;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Settings\CustomFieldGroups\Traits\HasCustomFieldGroupCommon;
use VentureDrake\LaravelCrm\Models\FieldGroup;

class CustomFieldGroupCreate extends Component
{
    use AuthorizesRequests;
    use HasCustomFieldGroupCommon;

    public function save()
    {
        $this->authorize('create', FieldGroup::class);

        $this->validate();

        FieldGroup::create([
            'external_id' => Uuid::uuid4()->toString(),
            'name' => $this->name,
        ]);

        $this->success(
            ucfirst(trans('laravel-crm::lang.custom_field_group_created')),
            redirectTo: route('laravel-crm.field-groups.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.custom-field-groups.custom-field-group-create');
    }
}
