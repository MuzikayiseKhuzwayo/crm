<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\CustomFieldGroups;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\FieldGroup;

class CustomFieldGroupShow extends Component
{
    use AuthorizesRequests;
    use Toast;

    public FieldGroup $fieldGroup;

    public function delete($id)
    {
        if ($fieldGroup = FieldGroup::find($id)) {
            $this->authorize('delete', $fieldGroup);

            $fieldGroup->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.custom_field_group_deleted')), redirectTo: route('laravel-crm.field-groups.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.custom-field-groups.custom-field-group-show');
    }
}
