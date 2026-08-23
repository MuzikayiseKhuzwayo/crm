<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use VentureDrake\LaravelCrm\Models\Setting;

$settings = [
    'organization_name' => 'Techfusion Automata',
    'app_name' => 'Alchemy CRM',
    'address_line1' => '14 Bedford Street',
    'address_line2' => 'Observatory',
    'city' => 'Cape Town',
    'state' => 'Western Cape',
    'postcode' => '7925',
    'country' => 'South Africa',
    'tax_name' => 'Techfusion Automata',
    'tax_rate' => '14',
    'vat_number' => '9257049297',
    'organization_registration_number' => '2026/399837/07',
    'bank_name' => 'Firstrand National Bank (FNB)',
    'bank_account_name' => 'Techfusion Ventures',
    'bank_account_number' => '63123144890',
    'bank_swift' => 'FIRNZAJJ',
    'bank_bic' => '250655',
    'lead_prefix' => 'TFA-L',
    'deal_prefix' => 'TFA-D',
    'quote_prefix' => 'TFA-Q',
    'order_prefix' => 'TFA-O',
    'invoice_prefix' => 'TFA-INV',
    'delivery_prefix' => 'TFA-DEL',
    'purchase_order_prefix' => 'TFA-PO',
    'currency' => 'ZAR',
    'language' => 'english',
    'timezone' => 'Africa/Johannesburg',
];

foreach ($settings as $name => $value) {
    Setting::updateOrCreate(
        ['name' => $name],
        ['value' => $value]
    );
}

echo "Successfully updated Techfusion Automata / Alchemy CRM settings!\n";
