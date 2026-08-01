<?php

namespace VentureDrake\LaravelCrm\Livewire\Deliveries;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Pipeline;

class DeliveryShow extends Component
{
    use AuthorizesRequests, Toast;

    public Delivery $delivery;

    public ?Pipeline $pipeline = null;

    public $address;

    protected $listeners = [
        'refreshDelivery' => '$refresh',
    ];

    public function mount()
    {
        $this->pipeline = Pipeline::where('model', get_class(new Delivery))->first();

        $this->address = $this->delivery->getShippingAddress();
    }

    public function delete($id)
    {
        if ($delivery = Delivery::find($id)) {
            $this->authorize('delete', $delivery);

            $delivery->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.delivery_deleted')), redirectTo: route('laravel-crm.deliveries.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.deliveries.delivery-show');
    }
}
