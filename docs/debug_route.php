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

$app = require_once __DIR__ . '/../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$app->register(\VentureDrake\LaravelCrm\LaravelCrmServiceProvider::class);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

config(['laravel-crm.db_table_prefix' => 'crm_']);
config(['app.debug' => true]);

$user = App\Models\User::first();
auth()->login($user);

$uri = $argv[1] ?? '/crm/dashboard';
echo "Testing URI: " . $uri . "\n";

try {
    $request = Illuminate\Http\Request::create('https://crm.techfusion-alchemy.xyz' . $uri, 'GET');
    $response = $kernel->handle($request);
    echo "STATUS: " . $response->getStatusCode() . "\n";
    if (isset($response->exception) && $response->exception) {
        echo "EXCEPTION CLASS: " . get_class($response->exception) . "\n";
        echo "EXCEPTION MESSAGE: " . $response->exception->getMessage() . "\n";
        echo "TRACE:\n" . $response->exception->getTraceAsString() . "\n";
    } else {
        echo "CONTENT SNIPPET:\n" . substr($response->getContent(), 0, 2000) . "\n";
    }
} catch (\Throwable $e) {
    echo "TOP EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
