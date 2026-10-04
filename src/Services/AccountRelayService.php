<?php

namespace VentureDrake\LaravelCrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Task;

class AccountRelayService
{
    /**
     * Initialize or sync an account relay basket for an organization.
     */
    public function initializeOrganizationBasket(Organization $organization): Collection
    {
        $leads = $organization->leads()
            ->with(['person', 'pipelineStage', 'ownerUser', 'tasks'])
            ->get();

        if ($leads->isEmpty()) {
            return collect();
        }

        // Sort leads by seniority / job role priority
        $sorted = $leads->sortBy(function (Lead $lead) {
            $title = strtolower($lead->title.' '.($lead->person?->description ?? ''));

            if ($this->containsAny($title, ['chief', 'cxo', 'ceo', 'cfo', 'cto', 'coo', 'cro', 'cmo', 'vp', 'vice president', 'founder', 'owner', 'president', 'partner'])) {
                return 10;
            }
            if ($this->containsAny($title, ['director', 'head of', 'head', 'general manager'])) {
                return 20;
            }
            if ($this->containsAny($title, ['manager', 'lead', 'supervisor'])) {
                return 30;
            }

            return 40;
        })->values();

        $hasActiveOrEngaged = $sorted->contains(function (Lead $lead) {
            return in_array($lead->relay_status, ['active', 'engaged']);
        });

        foreach ($sorted as $index => $lead) {
            $order = $index + 1;
            $updates = ['relay_order' => $order];

            if (! $hasActiveOrEngaged) {
                if ($index === 0) {
                    $updates['relay_status'] = 'active';
                    $updates['relay_activated_at'] = Carbon::now();
                } else {
                    $updates['relay_status'] = 'standby';
                }
            } elseif (empty($lead->relay_status)) {
                $updates['relay_status'] = 'standby';
            }

            $lead->update($updates);
        }

        return $organization->leads()->orderBy('relay_order', 'asc')->get();
    }

    /**
     * Advance to the next lead in the company basket when current lead falls off.
     */
    public function rotateToNext(Lead $currentLead, string $reason = 'unresponsive'): ?Lead
    {
        $org = $currentLead->organization;

        // Mark current lead as fallen off
        $currentLead->update([
            'relay_status' => 'fallen_off',
            'relay_fallen_off_at' => Carbon::now(),
        ]);

        if (! $org || $org->isDisqualified()) {
            return null;
        }

        // Look for next lead in the company queue
        $nextLead = $org->leads()
            ->where('id', '!=', $currentLead->id)
            ->where(function ($q) {
                $q->where('relay_status', 'standby')
                    ->orWhereNull('relay_status');
            })
            ->orderBy('relay_order', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if (! $nextLead) {
            // Account basket exhausted - all contacts engaged or fallen off
            return null;
        }

        $nextLead->update([
            'relay_status' => 'active',
            'relay_activated_at' => Carbon::now(),
        ]);

        $prevName = $currentLead->person?->name ?: $currentLead->title;
        $nextName = $nextLead->person?->name ?: $nextLead->title;

        // Seed initial outreach task for this new colleague
        $task = Task::create([
            'external_id' => Uuid::uuid4()->toString(),
            'name' => "Reach out to {$nextName} (Account Relay: {$prevName} was {$reason})",
            'description' => "Previous outreach to {$prevName} at {$org->name} fell off ({$reason}). Now working on {$nextName} as next in the account queue.",
            'taskable_type' => get_class($nextLead),
            'taskable_id' => $nextLead->id,
            'due_at' => Carbon::now()->addDay(),
            'user_owner_id' => auth()->id() ?: ($nextLead->user_owner_id ?: 1),
            'user_assigned_id' => auth()->id() ?: ($nextLead->user_assigned_id ?: 1),
        ]);

        // Record activity on new lead timeline
        $nextLead->activities()->create([
            'causeable_type' => auth()->user() ? auth()->user()->getMorphClass() : null,
            'causeable_id' => auth()->id() ?: ($nextLead->user_owner_id ?: 1),
            'timelineable_type' => $nextLead->getMorphClass(),
            'timelineable_id' => $nextLead->id,
            'recordable_type' => $task->getMorphClass(),
            'recordable_id' => $task->id,
        ]);

        return $nextLead->fresh();
    }

    /**
     * Freeze the company basket when a lead engages or books a call.
     */
    public function freezeBasket(Lead $lead): void
    {
        $lead->update([
            'relay_status' => 'engaged',
        ]);
    }

    /**
     * Disqualify the entire company basket and all its leads.
     */
    public function disqualifyBasket(Organization $org, string $reason = 'Do Not Contact'): void
    {
        $org->markDisqualified($reason);

        $org->leads()->update([
            'relay_status' => 'disqualified',
        ]);
    }

    /**
     * Auto-rotate active leads that have exceeded the inactivity threshold without response.
     *
     * @return array<int, array{old_lead_id: int, new_lead_id: ?int, company: string}>
     */
    public function autoRotateDueLeads(int $daysThreshold = 14): array
    {
        $cutoff = Carbon::now()->subDays($daysThreshold);

        $dueLeads = Lead::where('relay_status', 'active')
            ->whereNotNull('organization_id')
            ->where('relay_activated_at', '<=', $cutoff)
            ->whereDoesntHave('pipelineStage', function ($q) {
                $q->where('order', '>=', 4); // Don't rotate if Call Scheduled or Won
            })
            ->get();

        $results = [];

        foreach ($dueLeads as $lead) {
            $newLead = $this->rotateToNext($lead, "unresponsive after {$daysThreshold} days");
            $results[] = [
                'old_lead_id' => $lead->id,
                'new_lead_id' => $newLead?->id,
                'company' => $lead->organization?->name ?? 'Unknown',
            ];
        }

        return $results;
    }

    /**
     * Helper to check if string contains any keyword.
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
