<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\Labels;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Label;

class LabelShow extends Component
{
    use AuthorizesRequests;
    use Toast;

    public Label $label;

    public function delete($id)
    {
        if ($label = Label::find($id)) {
            $this->authorize('delete', $label);

            $label->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.label_deleted')), redirectTo: route('laravel-crm.labels.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.labels.label-show');
    }
}
