<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Files\FileItem;
use VentureDrake\LaravelCrm\Livewire\Files\FileRelated;
use VentureDrake\LaravelCrm\Models\File;
use VentureDrake\LaravelCrm\Models\Person;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the sub-item / related blades reach for
 * tables the minimal TestSchema does not ship. Overriding only render() leaves the real
 * action methods -- and the $this->authorize() guards inside them -- completely intact,
 * so these tests still exercise the production authorization path against the real
 * policies.
 */
class AuthzFileItem extends FileItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzFileRelated extends FileRelated
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzFile(): File
{
    $parent = Person::create(['first_name' => 'Authz Parent']);

    return File::create([
        'file' => 'laravel-crm/person/'.$parent->id.'/files/doc.pdf',
        'name' => 'doc.pdf',
        'disk' => 'local',
        'fileable_type' => $parent->getMorphClass(),
        'fileable_id' => $parent->id,
    ]);
}

it('forbids deleting a file without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm files']);
    $record = authzFile();

    Livewire::test(AuthzFileItem::class, ['file' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(File::find($record->id))->not->toBeNull();
});

it('allows deleting a file with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm files', 'delete crm files']);
    $record = authzFile();

    Livewire::test(AuthzFileItem::class, ['file' => $record])
        ->call('delete')
        ->assertOk();

    expect(File::find($record->id))->toBeNull();
});

it('forbids uploading a related file without the create permission and creates no record', function () {
    Storage::fake('local');
    $this->actingAsUserWithPermissions(['view crm files']);
    $parent = Person::create(['first_name' => 'Authz Parent']);
    $before = File::count();

    Livewire::test(AuthzFileRelated::class, ['model' => $parent])
        ->set('uploadedFile', UploadedFile::fake()->create('doc.pdf', 8, 'application/pdf'))
        ->call('save')
        ->assertForbidden();

    expect(File::count())->toBe($before);
});

it('allows uploading a related file with the create permission', function () {
    Storage::fake('local');
    $this->actingAsUserWithPermissions(['view crm files', 'create crm files']);
    $parent = Person::create(['first_name' => 'Authz Parent']);

    Livewire::test(AuthzFileRelated::class, ['model' => $parent])
        ->set('uploadedFile', UploadedFile::fake()->create('doc.pdf', 8, 'application/pdf'))
        ->call('save')
        ->assertOk();

    expect($parent->files()->count())->toBe(1);
});
