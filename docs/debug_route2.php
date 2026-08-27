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
config(['app.debug' => true]);

// Test 3: Unauthenticated GET /crm/login
$request3 = Illuminate\Http\Request::create('https://crm.techfusion-alchemy.xyz/crm/login', 'GET');
$response3 = $kernel->handle($request3);
echo "UNAUTH /crm/login -> STATUS: " . $response3->getStatusCode() . " LOCATION: " . $response3->headers->get('Location') . "\n";
if (isset($response3->exception) && $response3->exception) {
    echo "EX CLASS: " . get_class($response3->exception) . "\n";
    echo "EX: " . $response3->exception->getMessage() . "\n";
} else {
    echo "CONTENT SNIPPET: " . substr($response3->getContent(), 0, 500) . "\n";
}
