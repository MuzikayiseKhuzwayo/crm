<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use VentureDrake\LaravelCrm\Tests\Stubs\User;
use VentureDrake\LaravelCrm\Models\Role;

$user = User::updateOrCreate(
    ['email' => 'admin@laravelcrm.com'],
    [
        'name' => 'Admin Owner',
        'password' => bcrypt('password'),
        'crm_access' => 1
    ]
);

$role = Role::where('name', 'Owner')->first();
if ($role) {
    $user->assignRole($role);
}

echo "ADMIN_USER_CREATED_SUCCESSFULLY\n";
