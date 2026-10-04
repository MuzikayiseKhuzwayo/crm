<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Support\Money;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use VentureDrake\LaravelCrm\Traits\HasCrmActivities;
use VentureDrake\LaravelCrm\Traits\HasCrmFields;
use VentureDrake\LaravelCrm\Traits\HasCrmUserRelations;
use VentureDrake\LaravelCrm\Traits\HasEncryptableFields;
use VentureDrake\LaravelCrm\Traits\SearchFilters;

class Organization extends Model
{
    use BelongsToTeams;
    use HasCrmActivities;
    use HasCrmFields;
    use HasCrmUserRelations;
    use HasEncryptableFields;
    use SearchFilters;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $encryptable = [
        'name',
    ];

    protected $searchable = [
        'name',
    ];

    protected $filterable = [
        'user_owner_id',
        'labels.id',
    ];

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'organizations';
    }

    public function setAnnualRevenueAttribute($value)
    {
        $this->attributes['annual_revenue'] = Money::toInteger($value);
    }

    public function setTotalMoneyRaisedAttribute($value)
    {
        $this->attributes['total_money_raised'] = Money::toInteger($value);
    }

    public function people()
    {
        return $this->hasMany(Person::class);
    }

    /**
     * Get all of the organization emails.
     */
    public function emails()
    {
        return $this->morphMany(Email::class, 'emailable');
    }

    public function getPrimaryEmail()
    {
        return $this->emails()->where('primary', 1)->first();
    }

    /**
     * Get all of the organization phone numbers.
     */
    public function phones()
    {
        return $this->morphMany(Phone::class, 'phoneable');
    }

    public function getPrimaryPhone()
    {
        return $this->phones()->where('primary', 1)->first();
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function getPrimaryAddress()
    {
        return $this->addresses()->where('primary', 1)->first();
    }

    public function getBillingAddress()
    {
        return $this->addresses()->where('address_type_id', 5)->first();
    }

    public function getShippingAddress()
    {
        return $this->addresses()->where('address_type_id', 6)->first();
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get all of the labels for the lead.
     */
    public function labels()
    {
        return $this->morphToMany(Label::class, config('laravel-crm.db_table_prefix').'labelable');
    }

    public function organizationType()
    {
        return $this->belongsTo(OrganizationType::class);
    }

    public function contacts()
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    /**
     * Get the xero contact associated with the organization.
     */
    public function xeroContact()
    {
        return $this->hasOne(XeroContact::class);
    }

    public function client()
    {
        return $this->morphOne(Customer::class, 'clientable');
    }

    public function timezone()
    {
        return $this->belongsTo(Timezone::class);
    }

    /**
     * Determine if this company is flagged as Do Not Contact / Disqualified.
     */
    public function isDisqualified(): bool
    {
        return $this->labels->contains(function ($label) {
            $name = strtolower(trim($label->name));

            return in_array($name, ['do not contact', 'disqualified', 'lost', 'not a fit', 'blacklisted']);
        });
    }

    /**
     * Mark this company as Do Not Contact / Disqualified.
     */
    public function markDisqualified(?string $reason = 'Do Not Contact', $userId = null): void
    {
        $label = Label::firstOrCreate(
            ['name' => 'Do Not Contact'],
            [
                'external_id' => Uuid::uuid4()->toString(),
                'hex' => 'ef4444',
                'description' => 'Flagged as Do Not Contact / Disqualified to avoid wasted outreach.',
            ]
        );

        if (! $this->labels->contains($label->id)) {
            $this->labels()->attach($label->id);
        }
    }

    /**
     * Remove the Do Not Contact / Disqualified status from this company.
     */
    public function clearDisqualified(): void
    {
        $dncLabels = $this->labels->filter(function ($label) {
            $name = strtolower(trim($label->name));

            return in_array($name, ['do not contact', 'disqualified', 'lost', 'not a fit', 'blacklisted']);
        });

        foreach ($dncLabels as $label) {
            $this->labels()->detach($label->id);
        }
    }

    /**
     * Generate an Account Outreach Intelligence Summary for this organization.
     */
    public function outreachSummary(?int $excludeLeadId = null): array
    {
        $allLeads = $this->leads()
            ->with(['person', 'pipelineStage', 'ownerUser', 'tasks', 'labels'])
            ->get();

        $otherLeads = $excludeLeadId
            ? $allLeads->filter(fn ($l) => $l->id != $excludeLeadId)
            : $allLeads;

        // 1. Check if Organization itself is flagged as Do Not Contact
        $orgDncLabel = $this->labels->first(function ($label) {
            $name = strtolower(trim($label->name));

            return in_array($name, ['do not contact', 'disqualified', 'lost', 'not a fit', 'blacklisted']);
        });

        // 2. Check if any lead at this company is marked as Disqualified / Lost
        $disqualifiedLead = $allLeads->first(function ($lead) {
            $hasDncLabel = $lead->labels->contains(function ($label) {
                $name = strtolower(trim($label->name));

                return in_array($name, ['do not contact', 'disqualified', 'lost', 'not a fit', 'blacklisted']);
            });
            $isLostStage = $lead->pipelineStage && Str::contains(strtolower($lead->pipelineStage->name), ['lost', 'disqualified', 'dead', 'rejected']);

            return $hasDncLabel || $isLostStage;
        });

        if ($orgDncLabel || $disqualifiedLead) {
            $reason = $orgDncLabel ? $orgDncLabel->name : ($disqualifiedLead->pipelineStage?->name ?? 'Disqualified');
            $culprit = $disqualifiedLead ? ($disqualifiedLead->person?->name ?: $disqualifiedLead->title) : $this->name;

            return [
                'status' => 'disqualified',
                'badge_class' => 'badge-error',
                'badge_label' => 'Do Not Contact',
                'banner_class' => 'border-error/40 bg-error/10 text-error-content',
                'icon' => 'o-no-symbol',
                'headline' => 'Company Flagged as Do Not Contact / Disqualified',
                'description' => "Previous outreach at {$this->name} was marked as {$reason} ({$culprit}). Do not waste time pitching contacts at this company.",
                'total_leads_count' => $allLeads->count(),
                'other_leads' => $otherLeads->values(),
                'has_previous_contact' => true,
            ];
        }

        // 3. Check for active outreach or leads in progress
        $activeOtherLeads = $otherLeads->filter(function ($lead) {
            $stageOrder = $lead->pipelineStage?->order ?? 1;
            $hasTasks = $lead->tasks->isNotEmpty();

            return $stageOrder > 1 || $hasTasks;
        });

        if ($activeOtherLeads->isNotEmpty()) {
            $leadNames = $activeOtherLeads->map(function ($l) {
                $name = $l->person?->name ?: $l->title;
                $stage = $l->pipelineStage?->name ?? 'Active';
                $owner = $l->ownerUser?->name ? "(@{$l->ownerUser->name})" : '';

                return "{$name} [{$stage} {$owner}]";
            })->join(', ');

            return [
                'status' => 'active',
                'badge_class' => 'badge-warning',
                'badge_label' => 'Active Outreach',
                'banner_class' => 'border-warning/40 bg-warning/10 text-base-content',
                'icon' => 'o-exclamation-triangle',
                'headline' => 'Active Outreach in Progress at this Company',
                'description' => "Other contacts at {$this->name} are currently being worked: {$leadNames}. Please coordinate internally to avoid conflicting outreach.",
                'total_leads_count' => $allLeads->count(),
                'other_leads' => $otherLeads->values(),
                'has_previous_contact' => true,
            ];
        }

        // 4. Multiple uncontacted leads at this company (Colleague Cluster)
        if ($otherLeads->isNotEmpty()) {
            return [
                'status' => 'uncontacted',
                'badge_class' => 'badge-info',
                'badge_label' => "{$allLeads->count()} at Company",
                'banner_class' => 'border-info/30 bg-info/5 text-base-content',
                'icon' => 'o-building-office-2',
                'headline' => "Account Roster: {$otherLeads->count()} other lead(s) at {$this->name}",
                'description' => "No prior outreach recorded for {$this->name}. You are the first to engage this account.",
                'total_leads_count' => $allLeads->count(),
                'other_leads' => $otherLeads->values(),
                'has_previous_contact' => false,
            ];
        }

        // 5. Fresh solo account
        return [
            'status' => 'fresh',
            'badge_class' => 'badge-success',
            'badge_label' => 'Fresh Account',
            'banner_class' => 'border-success/30 bg-success/5 text-base-content',
            'icon' => 'o-check-circle',
            'headline' => "Fresh Account: First contact at {$this->name}",
            'description' => 'No other leads or previous outreach found for this company.',
            'total_leads_count' => 1,
            'other_leads' => collect([]),
            'has_previous_contact' => false,
        ];
    }
}
