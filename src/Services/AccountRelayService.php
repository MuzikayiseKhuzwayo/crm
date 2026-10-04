<?php

namespace VentureDrake\LaravelCrm\Services;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Task;

class AccountRelayService
{
    /**
     * Ensure relay columns exist on the leads table (auto-healing schema check).
     */
    public function ensureRelayColumnsExist(): void
    {
        $tableName = config('laravel-crm.db_table_prefix', 'crm_').'leads';

        if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'relay_order')) {
            try {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (! Schema::hasColumn($tableName, 'relay_status')) {
                        $table->string('relay_status')->nullable()->default('standby');
                    }
                    if (! Schema::hasColumn($tableName, 'relay_order')) {
                        $table->integer('relay_order')->nullable()->default(1);
                    }
                    if (! Schema::hasColumn($tableName, 'relay_activated_at')) {
                        $table->datetime('relay_activated_at')->nullable();
                    }
                    if (! Schema::hasColumn($tableName, 'relay_fallen_off_at')) {
                        $table->datetime('relay_fallen_off_at')->nullable();
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('AccountRelayService: could not auto-create relay columns: '.$e->getMessage());
            }
        }
    }

    /**
     * Initialize or sync an account relay basket for an organization.
     */
    public function initializeOrganizationBasket(Organization $organization): Collection
    {
        $this->ensureRelayColumnsExist();

        $tableName = $organization->leads()->getModel()->getTable();
        $hasRelayOrder = Schema::hasColumn($tableName, 'relay_order');
        $hasRelayStatus = Schema::hasColumn($tableName, 'relay_status');
        $hasRelayActivatedAt = Schema::hasColumn($tableName, 'relay_activated_at');

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

        $hasActiveOrEngaged = $hasRelayStatus && $sorted->contains(function (Lead $lead) {
            return in_array($lead->relay_status, ['active', 'engaged']);
        });

        foreach ($sorted as $index => $lead) {
            $order = $index + 1;
            $updates = [];

            if ($hasRelayOrder) {
                $updates['relay_order'] = $order;
            }

            if ($hasRelayStatus) {
                if (! $hasActiveOrEngaged) {
                    if ($index === 0) {
                        $updates['relay_status'] = 'active';
                        if ($hasRelayActivatedAt) {
                            $updates['relay_activated_at'] = Carbon::now();
                        }
                    } else {
                        $updates['relay_status'] = 'standby';
                    }
                } elseif (empty($lead->relay_status)) {
                    $updates['relay_status'] = 'standby';
                }
            }

            if (! empty($updates)) {
                $lead->update($updates);
            }
        }

        $query = $organization->leads();
        if ($hasRelayOrder) {
            $query->orderBy('relay_order', 'asc');
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->get();
    }

    /**
     * Advance to the next lead in the company basket when current lead falls off.
     */
    public function rotateToNext(Lead $currentLead, string $reason = 'unresponsive'): ?Lead
    {
        $this->ensureRelayColumnsExist();

        $org = $currentLead->organization;
        $tableName = $currentLead->getTable();
        $hasRelayStatus = Schema::hasColumn($tableName, 'relay_status');
        $hasRelayOrder = Schema::hasColumn($tableName, 'relay_order');
        $hasRelayFallenOffAt = Schema::hasColumn($tableName, 'relay_fallen_off_at');
        $hasRelayActivatedAt = Schema::hasColumn($tableName, 'relay_activated_at');

        // Mark current lead as fallen off
        if ($hasRelayStatus) {
            $updates = ['relay_status' => 'fallen_off'];
            if ($hasRelayFallenOffAt) {
                $updates['relay_fallen_off_at'] = Carbon::now();
            }
            $currentLead->update($updates);
        }

        if (! $org || $org->isDisqualified()) {
            return null;
        }

        // Look for next lead in the company queue
        $leadsQuery = $org->leads()->where('id', '!=', $currentLead->id);

        if ($hasRelayStatus) {
            $leadsQuery->where(function ($q) {
                $q->where('relay_status', 'standby')
                    ->orWhereNull('relay_status');
            });
        }

        if ($hasRelayOrder) {
            $leadsQuery->orderBy('relay_order', 'asc');
        }

        $nextLead = $leadsQuery->orderBy('id', 'asc')->first();

        if (! $nextLead) {
            // Account basket exhausted - all contacts engaged or fallen off
            return null;
        }

        if ($hasRelayStatus) {
            $nextUpdates = ['relay_status' => 'active'];
            if ($hasRelayActivatedAt) {
                $nextUpdates['relay_activated_at'] = Carbon::now();
            }
            $nextLead->update($nextUpdates);
        }

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
        $this->ensureRelayColumnsExist();
        if (Schema::hasColumn($lead->getTable(), 'relay_status')) {
            $lead->update([
                'relay_status' => 'engaged',
            ]);
        }
    }

    /**
     * Disqualify the entire company basket and all its leads.
     */
    public function disqualifyBasket(Organization $org, string $reason = 'Do Not Contact'): void
    {
        $this->ensureRelayColumnsExist();
        $org->markDisqualified($reason);

        $tableName = $org->leads()->getModel()->getTable();
        if (Schema::hasColumn($tableName, 'relay_status')) {
            $org->leads()->update([
                'relay_status' => 'disqualified',
            ]);
        }
    }

    /**
     * Auto-rotate active leads that have exceeded the inactivity threshold without response.
     *
     * @return array<int, array{old_lead_id: int, new_lead_id: ?int, company: string}>
     */
    public function autoRotateDueLeads(int $daysThreshold = 14): array
    {
        $this->ensureRelayColumnsExist();
        $tableName = config('laravel-crm.db_table_prefix', 'crm_').'leads';

        if (! Schema::hasColumn($tableName, 'relay_status') || ! Schema::hasColumn($tableName, 'relay_activated_at')) {
            return [];
        }

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
