<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\CustomFields;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Field;

class CustomFieldShow extends Component
{
    use AuthorizesRequests;
    use Toast;

    public Field $field;

    public function delete($id)
    {
        if ($field = Field::find($id)) {
            $this->authorize('delete', $field);

            $field->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.custom_field_deleted')), redirectTo: route('laravel-crm.fieldss.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.custom-fields.custom-field-show');
    }
}
