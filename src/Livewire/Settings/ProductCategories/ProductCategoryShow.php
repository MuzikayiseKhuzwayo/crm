<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\ProductCategories;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\ProductCategory;

class ProductCategoryShow extends Component
{
    use AuthorizesRequests;
    use Toast;

    public ProductCategory $productCategory;

    public function delete($id)
    {
        if ($productCategory = ProductCategory::find($id)) {
            $this->authorize('delete', $productCategory);

            $productCategory->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.product_category_deleted')), redirectTo: route('laravel-crm.product-categories.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.product-categories.product-category-show');
    }
}
