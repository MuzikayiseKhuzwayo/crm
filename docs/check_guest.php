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

// Test 1: GET / (root domain) unauthenticated
$request1 = Illuminate\Http\Request::create('https://crm.techfusion-alchemy.xyz/', 'GET');
$response1 = $kernel->handle($request1);
echo "ROOT / (UNAUTH) -> STATUS: " . $response1->getStatusCode() . " LOCATION: " . $response1->headers->get('Location') . "\n";

// Test 2: GET / (root domain) authenticated
$user = App\Models\User::first();
auth()->login($user);

$request2 = Illuminate\Http\Request::create('https://crm.techfusion-alchemy.xyz/', 'GET');
$response2 = $kernel->handle($request2);
echo "ROOT / (AUTH) -> STATUS: " . $response2->getStatusCode() . " LOCATION: " . $response2->headers->get('Location') . "\n";
