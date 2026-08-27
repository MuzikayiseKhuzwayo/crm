<?php

require __DIR__ . '/../vendor/autoload.php';

$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'laravel_crm';
$_ENV['DB_USERNAME'] = 'crm_user';
$_ENV['DB_PASSWORD'] = 'SecurePass123!';

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=laravel_crm');
putenv('DB_USERNAME=crm_user');
putenv('DB_PASSWORD=SecurePass123!');

$app = require __DIR__ . '/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->register(\VentureDrake\LaravelCrm\LaravelCrmServiceProvider::class);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

config(['laravel-crm.db_table_prefix' => 'crm_']);

// Simulate authenticated user visiting /crm/login
$user = App\Models\User::first();
auth()->login($user);

$request = Illuminate\Http\Request::create('https://crm.techfusion-alchemy.xyz/crm/login', 'GET');
$response = $kernel->handle($request);

echo "AUTH USER VISITING /crm/login -> STATUS: " . $response->getStatusCode() . "\n";
echo "LOCATION HEADER: " . $response->headers->get('Location') . "\n";
