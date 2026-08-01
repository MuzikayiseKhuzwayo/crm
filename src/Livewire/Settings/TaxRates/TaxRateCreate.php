<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\TaxRates;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use VentureDrake\LaravelCrm\Livewire\Settings\TaxRates\Traits\HasTaxRateCommon;
use VentureDrake\LaravelCrm\Models\TaxRate;

class TaxRateCreate extends Component
{
    use AuthorizesRequests;
    use HasTaxRateCommon;

    public function save()
    {
        $this->authorize('create', TaxRate::class);

        $this->validate();

        $taxRate = TaxRate::create([
            'name' => $this->name,
            'rate' => $this->rate,
            'description' => $this->description,
            'default' => $this->default,
            'tax_type' => $this->tax_type,
        ]);

        if ($this->default) {
            TaxRate::where('id', '!=', $taxRate->id)->update(['default' => 0]);
        }

        $this->success(
            ucfirst(trans('laravel-crm::lang.tax_rate_created')),
            redirectTo: route('laravel-crm.tax-rates.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.tax-rates.tax-rate-create');
    }
}
