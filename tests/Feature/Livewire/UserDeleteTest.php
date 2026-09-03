<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Users\UserIndex;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('detaches team membership and deletes target user safely without foreign key violations', function () {
    $admin = User::create(['name' => 'Admin User', 'email' => 'admin@example.com']);
    $userToDelete = User::create(['name' => 'Fake User', 'email' => 'fake@example.com']);
    $this->actingAs($admin);

    if (Schema::hasTable('crm_team_user')) {
        DB::table('crm_team_user')->insert([
            'team_id' => 1,
            'user_id' => $userToDelete->id,
            'role' => 'member',
        ]);
    }

    Livewire::test(UserIndex::class)
        ->call('delete', $userToDelete->id);

    expect(User::find($userToDelete->id))->toBeNull();
});
