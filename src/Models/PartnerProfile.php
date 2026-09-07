<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class PartnerProfile extends Model
{
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'recruited_at' => 'datetime',
        'first_sale_at' => 'datetime',
        'last_deal_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'partner_profiles';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'partner_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'user_updated_id');
    }
}
