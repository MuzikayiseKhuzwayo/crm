<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\TaxRates;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\TaxRate;

class TaxRateShow extends Component
{
    use AuthorizesRequests;
    use Toast;

    public TaxRate $taxRate;

    public function delete($id)
    {
        if ($taxRate = TaxRate::find($id)) {
            $this->authorize('delete', $taxRate);

            $taxRate->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.tax_rate_deleted')), redirectTo: route('laravel-crm.tax-rates.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.tax-rates.tax-rate-show');
    }
}
