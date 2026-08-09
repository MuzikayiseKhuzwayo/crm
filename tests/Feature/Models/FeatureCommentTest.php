<?php

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\Feature;
use VentureDrake\LaravelCrm\Models\FeatureComment;

test('feature comment uses prefixed table', function () {
    expect((new FeatureComment)->getTable())->toBe('crm_feature_comments');
});

/**
 * `$guarded = ['id']` is the convention across all five `Feature*` models. An
 * explicit `$fillable` here would silently drop any column it forgot —
 * `external_id` first among them, since a host that mass-assigns its own is
 * entitled to have it kept.
 */
test('feature comment guards only the primary key', function () {
    expect((new FeatureComment)->getGuarded())->toBe(['id'])
        ->and((new FeatureComment)->getFillable())->toBe([]);
});

test('an explicitly mass-assigned external_id is kept, and id is not assignable', function () {
    $feature = Feature::create(['title' => 'A feature']);
    $externalId = (string) Str::uuid();

    $comment = FeatureComment::create([
        'id' => 999999,
        'external_id' => $externalId,
        'feature_id' => $feature->id,
        'body' => 'Mass-assigned',
    ]);

    expect($comment->external_id)->toBe($externalId)
        ->and($comment->id)->not->toBe(999999);
});
