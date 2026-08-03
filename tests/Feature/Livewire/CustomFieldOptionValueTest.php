<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Deals\DealEdit;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Field;
use VentureDrake\LaravelCrm\Models\FieldModel;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Setting;

/**
 * Render-stub subclass -- the deal edit blade reaches for tables the minimal
 * TestSchema does not ship. mount()/save() (and the custom field hydration and
 * validation inside them) are left completely intact.
 */
class CustomFieldDealEdit extends DealEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Setting::updateOrCreate(['name' => 'currency'], ['value' => 'USD']);

    $pipeline = Pipeline::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Deal Pipeline',
        'model' => Deal::class,
    ]);
    $pipeline->pipelineStages()->create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Pending',
        'order' => 0,
    ]);
});

/**
 * Create an option-backed custom field attached to deals and return it with its options.
 */
function makeDealOptionField(string $type, array $options): Field
{
    $field = Field::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Deal Priority '.Str::random(4),
        'key' => 'deal_priority_'.Str::random(4),
        'type' => $type,
    ]);

    foreach ($options as $order => $option) {
        $field->fieldOptions()->create([
            'external_id' => Str::uuid()->toString(),
            'value' => $option['value'],
            'label' => $option['label'],
            'order' => $order + 1,
        ]);
    }

    FieldModel::create([
        'external_id' => Str::uuid()->toString(),
        'field_id' => $field->id,
        'model' => Deal::class,
    ]);

    return $field->fresh('fieldOptions');
}

/**
 * A deal needs a linked person or organization to pass the shared deal validation rules.
 */
function makeDealWithPerson(string $title): Deal
{
    $person = Person::create(['first_name' => 'Custom', 'last_name' => 'Fields']);

    return Deal::create([
        'title' => $title,
        'person_id' => $person->id,
        'currency' => 'USD',
    ]);
}

it('saves a deal whose select custom field is stored as the option id', function () {
    $this->actingAsUserWithPermissions(['view crm deals', 'edit crm deals']);

    $field = makeDealOptionField('select', [
        ['value' => 'low', 'label' => 'Low'],
        ['value' => 'high', 'label' => 'High'],
    ]);
    $high = $field->fieldOptions->firstWhere('value', 'high');

    $deal = makeDealWithPerson('Original title');
    $deal->fields()->updateOrCreate(['field_id' => $field->id], ['value' => (string) $high->id]);

    Livewire::test(CustomFieldDealEdit::class, ['deal' => $deal])
        ->set('title', 'Updated title')
        ->call('save')
        ->assertHasNoErrors();

    expect($deal->fresh()->title)->toBe('Updated title');
});

it('saves a deal whose select custom field was stored as the legacy option value', function () {
    $this->actingAsUserWithPermissions(['view crm deals', 'edit crm deals']);

    $field = makeDealOptionField('select', [
        ['value' => 'low', 'label' => 'Low'],
        ['value' => 'high', 'label' => 'High'],
    ]);
    $high = $field->fieldOptions->firstWhere('value', 'high');

    $deal = makeDealWithPerson('Original title');
    $deal->fields()->updateOrCreate(['field_id' => $field->id], ['value' => 'high']);

    Livewire::test(CustomFieldDealEdit::class, ['deal' => $deal])
        ->set('title', 'Updated title')
        ->call('save')
        ->assertHasNoErrors();

    expect($deal->fresh()->title)->toBe('Updated title');

    // The legacy value is normalised to the option id on save.
    expect($deal->fields()->where('field_id', $field->id)->first()->value)
        ->toBe((string) $high->id);
});

it('saves a deal whose checkbox_multiple custom field was stored as legacy option values', function () {
    $this->actingAsUserWithPermissions(['view crm deals', 'edit crm deals']);

    $field = makeDealOptionField('checkbox_multiple', [
        ['value' => 'software', 'label' => 'Software'],
        ['value' => 'hardware', 'label' => 'Hardware'],
        ['value' => 'services', 'label' => 'Professional Services'],
    ]);
    $expected = $field->fieldOptions
        ->whereIn('value', ['software', 'services'])
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();

    $deal = makeDealWithPerson('Original title');
    $deal->fields()->updateOrCreate(
        ['field_id' => $field->id],
        ['value' => json_encode(['software', 'services'])]
    );

    Livewire::test(CustomFieldDealEdit::class, ['deal' => $deal])
        ->set('title', 'Updated title')
        ->call('save')
        ->assertHasNoErrors();

    expect($deal->fresh()->title)->toBe('Updated title');
    expect(json_decode($deal->fields()->where('field_id', $field->id)->first()->value, true))
        ->toBe($expected);
});

it('still rejects a select custom field value that matches no option', function () {
    $this->actingAsUserWithPermissions(['view crm deals', 'edit crm deals']);

    $field = makeDealOptionField('select', [
        ['value' => 'low', 'label' => 'Low'],
        ['value' => 'high', 'label' => 'High'],
    ]);

    $deal = makeDealWithPerson('Original title');

    Livewire::test(CustomFieldDealEdit::class, ['deal' => $deal])
        ->set('title', 'Updated title')
        ->set('fields.'.$field->id, 'not-an-option')
        ->call('save')
        ->assertHasErrors('fields.'.$field->id);

    expect($deal->fresh()->title)->toBe('Original title');
});
