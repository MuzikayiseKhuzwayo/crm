<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Settings\Labels\LabelCreate;
use VentureDrake\LaravelCrm\Livewire\Settings\Labels\LabelEdit;
use VentureDrake\LaravelCrm\Livewire\Settings\Labels\LabelIndex;
use VentureDrake\LaravelCrm\Livewire\Settings\SettingEdit;
use VentureDrake\LaravelCrm\Livewire\Settings\TemplateSettings;
use VentureDrake\LaravelCrm\Models\Label;

/**
 * Render-stub subclasses -- see ChatAuthorizationTest for the rationale. Only render()
 * is replaced; every guarded action method runs for real against the real policies.
 */
class AuthzSettingEdit extends SettingEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzTemplateSettings extends TemplateSettings
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLabelCreate extends LabelCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLabelEdit extends LabelEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzLabelIndex extends LabelIndex
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzLabel(): Label
{
    return Label::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Original label',
        'hex' => '000000',
    ]);
}

/*
 * SettingPolicy::update(User $user) takes no model, so these guards use the
 * class-string form -- authorize('update', Setting::class).
 */

it('forbids saving general settings without the edit settings permission', function () {
    $this->actingAsUserWithPermissions(['view crm settings']);

    Livewire::test(AuthzSettingEdit::class)
        ->call('save')
        ->assertForbidden();
});

it('allows saving general settings with the edit settings permission', function () {
    $this->actingAsUserWithPermissions(['view crm settings', 'edit crm settings']);

    Livewire::test(AuthzSettingEdit::class)
        ->call('save')
        ->assertOk();
});

it('forbids saving template settings without the edit settings permission and persists nothing', function () {
    $this->actingAsUserWithPermissions(['view crm settings']);

    Livewire::test(AuthzTemplateSettings::class)
        ->call('save')
        ->assertForbidden();

    expect(app('laravel-crm.settings')->get('pdf_template_invoice'))->toBeNull();
});

it('allows saving template settings with the edit settings permission', function () {
    $this->actingAsUserWithPermissions(['view crm settings', 'edit crm settings']);

    Livewire::test(AuthzTemplateSettings::class)
        ->call('save')
        ->assertOk();

    expect(app('laravel-crm.settings')->get('pdf_template_invoice'))->not->toBeNull();
});

it('forbids creating a label without the create permission and creates no record', function () {
    $this->actingAsUserWithPermissions(['view crm labels']);
    $before = Label::count();

    Livewire::test(AuthzLabelCreate::class)
        ->set('name', 'Tampered label')
        ->call('save')
        ->assertForbidden();

    expect(Label::count())->toBe($before);
});

it('allows creating a label with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm labels', 'create crm labels']);

    Livewire::test(AuthzLabelCreate::class)
        ->set('name', 'Brand new label')
        ->set('hex', '#ff0000')
        ->call('save')
        ->assertOk();

    expect(Label::where('name', 'Brand new label')->exists())->toBeTrue();
});

it('forbids updating a label without the edit permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm labels']);
    $label = authzLabel();

    Livewire::test(AuthzLabelEdit::class, ['label' => $label])
        ->set('name', 'Tampered')
        ->call('save')
        ->assertForbidden();

    expect($label->fresh()->name)->toBe('Original label');
});

it('allows updating a label with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm labels', 'edit crm labels']);
    $label = authzLabel();

    Livewire::test(AuthzLabelEdit::class, ['label' => $label])
        ->set('name', 'Renamed label')
        ->set('hex', '#00ff00')
        ->call('save')
        ->assertOk();

    expect($label->fresh()->name)->toBe('Renamed label');
});

it('forbids deleting a label without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm labels']);
    $label = authzLabel();

    Livewire::test(AuthzLabelIndex::class)
        ->call('delete', $label->id)
        ->assertForbidden();

    expect(Label::find($label->id))->not->toBeNull();
});

it('allows deleting a label with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm labels', 'delete crm labels']);
    $label = authzLabel();

    Livewire::test(AuthzLabelIndex::class)
        ->call('delete', $label->id)
        ->assertOk();

    expect(Label::find($label->id))->toBeNull();
});
