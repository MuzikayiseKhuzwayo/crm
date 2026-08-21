<?php

namespace VentureDrake\LaravelCrm\View\Composers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use VentureDrake\LaravelCrm\Models\Setting;

class SettingsComposer
{
    public static ?array $cachedParameters = null;

    public function compose(View $view)
    {
        static::$cachedParameters ??= Cache::remember(
            self::class,
            now()->addHour(),
            function () {
                $defaults = [
                    'crmDateFormat' => 'Y-m-d',
                    'crmTimeFormat' => 'H:i',
                    'crmTimezone' => 'UTC',
                    'crmTaxName' => 'Tax',
                    'crmDynamicProducts' => 'true',
                ];

                if (! Schema::hasTable(config('laravel-crm.db_table_prefix').'settings')) {
                    return $defaults;
                }

                if ($dynamicProductsSetting = Setting::where('name', 'dynamic_products')->first()) {
                    if ($dynamicProductsSetting->value == 1) {
                        $dynamicProducts = 'true';
                    } else {
                        $dynamicProducts = 'false';
                    }
                } else {
                    $dynamicProducts = $defaults['crmDynamicProducts'];
                }

                return [
                    'crmDateFormat' => Setting::where('name', 'date_format')->first()?->value ?? $defaults['crmDateFormat'],
                    'crmTimeFormat' => Setting::where('name', 'time_format')->first()?->value ?? $defaults['crmTimeFormat'],
                    'crmTimezone' => Setting::where('name', 'timezone')->first()?->value ?? $defaults['crmTimezone'],
                    'crmTaxName' => Setting::where('name', 'tax_name')->first()?->value ?? $defaults['crmTaxName'],
                    'crmDynamicProducts' => $dynamicProducts,
                ];
            }
        );

        $view->with(static::$cachedParameters);
    }
}
