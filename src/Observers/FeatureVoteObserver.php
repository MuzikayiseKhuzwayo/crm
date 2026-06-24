<?php

namespace VentureDrake\LaravelCrm\Observers;

use VentureDrake\LaravelCrm\Models\Feature;
use VentureDrake\LaravelCrm\Models\FeatureVote;

class FeatureVoteObserver
{
    public function creating(FeatureVote $vote)
    {
        if ($vote->team_id === null
            && config('laravel-crm.teams')
            && ($user = auth()->user())
            && ($team = $user->currentTeam ?? null)
        ) {
            $vote->team_id = $team->id;
        }

        if ($vote->team_id === null) {
            $feature = Feature::withoutGlobalScopes()->find($vote->feature_id);
            if ($feature) {
                $vote->team_id = $feature->team_id;
            }
        }
    }

    /**
     * Handle the feature vote "created" event.
     *
     * @return void
     */
    public function created(FeatureVote $vote)
    {
        Feature::whereKey($vote->feature_id)->increment('votes_count');
    }

    /**
     * Handle the feature vote "deleted" event.
     *
     * @return void
     */
    public function deleted(FeatureVote $vote)
    {
        Feature::whereKey($vote->feature_id)->decrement('votes_count');
    }
}
