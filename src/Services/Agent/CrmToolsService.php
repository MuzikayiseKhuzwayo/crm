<?php

namespace VentureDrake\LaravelCrm\Services\Agent;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Scopes\BelongsToTeamsScope;
use VentureDrake\LaravelCrm\Services\LeadStatusAutomationService;

class CrmToolsService
{
    public const NOTIFICATION_STATE_KEY = 'crm_agent_notification_state';

    public const PROACTIVITY_MODES = [
        'high' => [
            'key' => 'high',
            'label' => '🔥 High (Relentless)',
            'short_label' => '🔥 High',
            'cooldown_minutes' => 60,
            'delta_only' => false,
            'description' => 'Every 1h • Full proactive monitoring, all alert vectors & next-step recommendations',
            'directive' => 'PROACTIVITY MODE: HIGH (Most Proactive / Relentless Chief of Staff). Proactively flag overdue tasks, stale leads, and ready-to-advance leads, and recommend concrete next actions to drive pipeline velocity.',
        ],
        'balanced' => [
            'key' => 'balanced',
            'label' => '⚡ Balanced (Smart Delta)',
            'short_label' => '⚡ Balanced',
            'cooldown_minutes' => 240,
            'delta_only' => true,
            'description' => 'Every 4h + Delta-only • Pings only when critical alerts change (zero duplicate spam)',
            'directive' => 'PROACTIVITY MODE: BALANCED (Smart Delta). Be concise, executive, and focused strictly on new or changed critical items without repeating routine boilerplate.',
        ],
        'low' => [
            'key' => 'low',
            'label' => '☕ Daily Digest',
            'short_label' => '☕ Digest',
            'cooldown_minutes' => 1440,
            'delta_only' => false,
            'description' => 'Once per 24h • Consolidated daily executive summary with zero mid-day interruptions',
            'directive' => 'PROACTIVITY MODE: DAILY DIGEST (Low Proactivity). Keep responses ultra-compact, calm, and high-level (max 2-3 bullets). Avoid unsolicited follow-up suggestions unless explicitly asked.',
        ],
        'silent' => [
            'key' => 'silent',
            'label' => '🔕 Silent (On-Demand)',
            'short_label' => '🔕 Silent',
            'cooldown_minutes' => null,
            'delta_only' => false,
            'description' => 'Autonomous pings OFF • Only responds when you message or tap a button in Telegram',
            'directive' => 'PROACTIVITY MODE: SILENT (Least Proactive / On-Demand Only). Autonomous background alerts are muted. Answer user queries directly and concisely with zero unsolicited alerts.',
        ],
    ];

    protected LeadStatusAutomationService $leadAutomation;

    public function __construct(LeadStatusAutomationService $leadAutomation)
    {
        $this->leadAutomation = $leadAutomation;
    }

    /**
     * Resolve a User instance or user record from name, email, or numeric ID.
     */
    public function resolveUser(string|int|null $userRef): ?object
    {
        if (! $userRef) {
            return null;
        }

        $userModel = config('auth.providers.users.model') ?? 'App\Models\User';
        if (class_exists($userModel)) {
            if (is_numeric($userRef)) {
                $user = $userModel::find($userRef);
                if ($user) {
                    return $user;
                }
            }
            $user = $userModel::where('name', 'like', "%{$userRef}%")
                ->orWhere('email', 'like', "%{$userRef}%")
                ->first();
            if ($user) {
                return $user;
            }
        }

        // Direct DB fallback
        if (is_numeric($userRef)) {
            $user = DB::table('users')->where('id', $userRef)->first();
            if ($user) {
                return $user;
            }
        }

        return DB::table('users')
            ->where('name', 'like', "%{$userRef}%")
            ->orWhere('email', 'like', "%{$userRef}%")
            ->first();
    }

    /**
     * Resolve a Lead instance flexibly from lead_id (e.g. LD-TFA-L1542, TFA-L1542, 1542), ID, or external_id.
     */
    public function resolveLead(string|int|null $leadRef): ?Lead
    {
        if (! $leadRef) {
            return null;
        }

        if (is_numeric($leadRef)) {
            $lead = Lead::find($leadRef);
            if ($lead) {
                return $lead;
            }
        }

        // Direct match on lead_id or external_id
        $lead = Lead::where('lead_id', $leadRef)->orWhere('external_id', $leadRef)->first();
        if ($lead) {
            return $lead;
        }

        // Match with stripped LD- prefix (e.g. LD-TFA-L1542 -> TFA-L1542)
        $stripped = preg_replace('/^LD-/i', '', (string) $leadRef);
        $lead = Lead::where('lead_id', $stripped)->first();
        if ($lead) {
            return $lead;
        }

        // Match on numeric portion (e.g. 1542)
        if (preg_match('/(\d+)/', (string) $leadRef, $matches)) {
            $number = (int) $matches[1];
            $lead = Lead::where('number', $number)->first();
            if ($lead) {
                return $lead;
            }
        }

        return null;
    }

    /**
     * Generate the direct web URL for a lead in the CRM.
     */
    public function getLeadUrl(int|string $leadId): string
    {
        $baseUrl = config('app.url') ?: 'https://crm.techfusion-alchemy.xyz';
        $baseUrl = rtrim($baseUrl, '/');
        $prefix = trim(config('laravel-crm.route_prefix', 'crm'), '/');
        $path = $prefix ? "{$prefix}/leads/{$leadId}" : "leads/{$leadId}";

        return "{$baseUrl}/{$path}";
    }

    /**
     * Generate the direct web URL for a contact/person in the CRM.
     */
    public function getContactUrl(int|string $contactId): string
    {
        $baseUrl = config('app.url') ?: 'https://crm.techfusion-alchemy.xyz';
        $baseUrl = rtrim($baseUrl, '/');
        $prefix = trim(config('laravel-crm.route_prefix', 'crm'), '/');
        $path = $prefix ? "{$prefix}/people/{$contactId}" : "people/{$contactId}";

        return "{$baseUrl}/{$path}";
    }

    /**
     * Generate the direct web URL for an organization in the CRM.
     */
    public function getOrganizationUrl(int|string $orgId): string
    {
        $baseUrl = config('app.url') ?: 'https://crm.techfusion-alchemy.xyz';
        $baseUrl = rtrim($baseUrl, '/');
        $prefix = trim(config('laravel-crm.route_prefix', 'crm'), '/');
        $path = $prefix ? "{$prefix}/organizations/{$orgId}" : "organizations/{$orgId}";

        return "{$baseUrl}/{$path}";
    }

    /**
     * Get CRM overview metrics.
     */
    public function getCrmOverview(): array
    {
        $totalLeads = Lead::count();
        $totalPeople = Person::count();
        $totalOrganizations = Organization::count();
        $totalTasks = Task::count();
        $pendingTasks = Task::whereNull('completed_at')->count();
        $overdueTasks = Task::whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->count();

        $leadsByStage = Lead::select('pipeline_stage_id', DB::raw('count(*) as total'))
            ->groupBy('pipeline_stage_id')
            ->get()
            ->mapWithKeys(function ($item) {
                $stage = PipelineStage::find($item->pipeline_stage_id);
                $name = $stage ? $stage->name : "Stage {$item->pipeline_stage_id}";

                return [$name => $item->total];
            })->toArray();

        $leadsByRelay = Lead::select('relay_status', DB::raw('count(*) as total'))
            ->whereNotNull('relay_status')
            ->groupBy('relay_status')
            ->pluck('total', 'relay_status')
            ->toArray();

        $usersBreakdown = DB::table('users')->get(['id', 'name', 'email'])->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'leads_owned' => Lead::where('user_owner_id', $u->id)->count(),
                'leads_assigned' => Lead::where('user_assigned_id', $u->id)->count(),
                'people_owned' => Person::where('user_owner_id', $u->id)->count(),
                'organizations_owned' => Organization::where('user_owner_id', $u->id)->count(),
            ];
        })->toArray();

        return [
            'total_leads' => $totalLeads,
            'total_contacts' => $totalPeople,
            'total_organizations' => $totalOrganizations,
            'total_tasks' => $totalTasks,
            'pending_tasks' => $pendingTasks,
            'overdue_tasks' => $overdueTasks,
            'leads_by_pipeline_stage' => $leadsByStage,
            'leads_by_relay_status' => $leadsByRelay,
            'users_ownership_breakdown' => $usersBreakdown,
        ];
    }

    /**
     * Query leads by keyword, owner, assigned user, stage, relay status, organization, person, year, date range, or tasks.
     */
    public function queryLeads(array $args): array
    {
        $query = Lead::with(['person', 'organization', 'pipelineStage', 'ownerUser', 'assignedToUser']);

        if (! empty($args['search'])) {
            $search = $args['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('lead_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('linkedin', 'like', "%{$search}%")
                    ->orWhereHas('person', function ($pq) use ($search) {
                        $pq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('organization', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($args['owner'])) {
            $user = $this->resolveUser($args['owner']);
            if ($user) {
                $query->where('user_owner_id', $user->id);
            } elseif (is_numeric($args['owner'])) {
                $query->where('user_owner_id', (int) $args['owner']);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($args['assigned_to'])) {
            $user = $this->resolveUser($args['assigned_to']);
            if ($user) {
                $query->where('user_assigned_id', $user->id);
            } elseif (is_numeric($args['assigned_to'])) {
                $query->where('user_assigned_id', (int) $args['assigned_to']);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($args['organization'])) {
            $orgTerm = $args['organization'];
            $query->whereHas('organization', function ($oq) use ($orgTerm) {
                $oq->where('name', 'like', "%{$orgTerm}%");
            });
        }

        if (! empty($args['person'])) {
            $personTerm = $args['person'];
            $query->whereHas('person', function ($pq) use ($personTerm) {
                $pq->where('first_name', 'like', "%{$personTerm}%")
                    ->orWhere('last_name', 'like', "%{$personTerm}%");
            });
        }

        if (! empty($args['stage'])) {
            $stageTerm = $args['stage'];
            if (is_numeric($stageTerm)) {
                $query->where('pipeline_stage_id', $stageTerm);
            } else {
                $stage = PipelineStage::where('name', 'like', "%{$stageTerm}%")->first();
                if ($stage) {
                    $query->where('pipeline_stage_id', $stage->id);
                }
            }
        }

        if (! empty($args['relay_status'])) {
            $query->where('relay_status', $args['relay_status']);
        }

        if (! empty($args['year'])) {
            $query->whereYear('created_at', (int) $args['year']);
        }

        if (! empty($args['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($args['created_before'])->endOfDay());
        }

        if (! empty($args['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($args['created_after'])->startOfDay());
        }

        if (! empty($args['has_tasks'])) {
            if ($args['has_tasks'] === 'yes') {
                $query->has('tasks');
            } elseif ($args['has_tasks'] === 'no') {
                $query->doesntHave('tasks');
            } elseif ($args['has_tasks'] === 'overdue') {
                $query->whereHas('tasks', function ($tq) {
                    $tq->whereNull('completed_at')->whereNotNull('due_at')->where('due_at', '<', Carbon::now());
                });
            }
        }

        if (! empty($args['has_linkedin'])) {
            if ($args['has_linkedin'] === 'yes') {
                $query->where(function ($q) {
                    $q->where(function ($lq) {
                        $lq->whereNotNull('linkedin')->where('linkedin', '!=', '');
                    })->orWhereHas('person', function ($pq) {
                        $pq->whereNotNull('linkedin')->where('linkedin', '!=', '');
                    });
                });
            } elseif ($args['has_linkedin'] === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('linkedin')->orWhere('linkedin', '=', '');
                })->where(function ($q) {
                    $q->whereDoesntHave('person')
                        ->orWhereHas('person', function ($pq) {
                            $pq->whereNull('linkedin')->orWhere('linkedin', '=', '');
                        });
                });
            }
        }

        $totalMatching = (clone $query)->count();
        $limit = min((int) ($args['limit'] ?? 10), 100);
        $results = $query->latest()->limit($limit)->get();

        $leads = $results->map(function ($lead) {
            return [
                'id' => $lead->id,
                'lead_id' => $lead->lead_id,
                'url' => $this->getLeadUrl($lead->id),
                'title' => $lead->title,
                'person' => $lead->person ? $lead->person->name : null,
                'organization' => $lead->organization ? $lead->organization->name : null,
                'stage' => $lead->pipelineStage ? $lead->pipelineStage->name : null,
                'relay_status' => $lead->relay_status,
                'owner' => $lead->ownerUser?->name,
                'assigned_to' => $lead->assignedToUser?->name,
                'linkedin' => $lead->linkedin,
                'created_at' => $lead->created_at?->toIso8601String(),
            ];
        })->toArray();

        return [
            'total_matching' => $totalMatching,
            'returned_count' => count($leads),
            'leads' => $leads,
        ];
    }

    /**
     * Query contacts (people) by keyword, owner, organization, year, date range, or lead association.
     */
    public function queryContacts(array $args): array
    {
        $query = Person::with(['organization', 'ownerUser', 'emails', 'phones']);

        if (! empty($args['search'])) {
            $search = $args['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('linkedin', 'like', "%{$search}%")
                    ->orWhereHas('organization', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('emails', function ($eq) use ($search) {
                        $eq->where('address', 'like', "%{$search}%");
                    })
                    ->orWhereHas('phones', function ($pq) use ($search) {
                        $pq->where('number', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($args['owner'])) {
            $user = $this->resolveUser($args['owner']);
            if ($user) {
                $query->where('user_owner_id', $user->id);
            } elseif (is_numeric($args['owner'])) {
                $query->where('user_owner_id', (int) $args['owner']);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($args['organization'])) {
            $orgTerm = $args['organization'];
            $query->whereHas('organization', function ($oq) use ($orgTerm) {
                $oq->where('name', 'like', "%{$orgTerm}%");
            });
        }

        if (! empty($args['year'])) {
            $query->whereYear('created_at', (int) $args['year']);
        }

        if (! empty($args['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($args['created_before'])->endOfDay());
        }

        if (! empty($args['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($args['created_after'])->startOfDay());
        }

        if (! empty($args['has_leads'])) {
            if ($args['has_leads'] === 'yes') {
                $query->has('leads');
            } elseif ($args['has_leads'] === 'no') {
                $query->doesntHave('leads');
            }
        }

        if (! empty($args['has_linkedin'])) {
            if ($args['has_linkedin'] === 'yes') {
                $query->whereNotNull('linkedin')->where('linkedin', '!=', '');
            } elseif ($args['has_linkedin'] === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('linkedin')->orWhere('linkedin', '=', '');
                });
            }
        }

        $totalMatching = (clone $query)->count();
        $limit = min((int) ($args['limit'] ?? 10), 100);
        $results = $query->latest()->limit($limit)->get();

        $contacts = $results->map(function ($person) {
            return [
                'id' => $person->id,
                'url' => $this->getContactUrl($person->id),
                'name' => $person->name,
                'job_title' => $person->title,
                'organization' => $person->organization?->name,
                'owner' => $person->ownerUser?->name,
                'email' => $person->getPrimaryEmail()?->address,
                'phone' => $person->getPrimaryPhone()?->number,
                'linkedin' => $person->linkedin,
                'leads_count' => $person->leads()->count(),
                'created_at' => $person->created_at?->toIso8601String(),
            ];
        })->toArray();

        return [
            'total_matching' => $totalMatching,
            'returned_count' => count($contacts),
            'contacts' => $contacts,
        ];
    }

    /**
     * Retrieve full details of a specific contact/person.
     */
    public function getContactDetails(array $args): array
    {
        $ref = $args['contact_id'] ?? $args['name'] ?? null;
        if (! $ref) {
            return ['error' => 'contact_id or name is required'];
        }

        $person = null;
        if (is_numeric($ref)) {
            $person = Person::find($ref);
        }

        if (! $person) {
            $person = Person::where('first_name', 'like', "%{$ref}%")
                ->orWhere('last_name', 'like', "%{$ref}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$ref}%"])
                ->first();
        }

        if (! $person) {
            return ['error' => "Contact {$ref} not found."];
        }

        $person->load(['organization', 'ownerUser', 'emails', 'phones', 'addresses', 'leads.pipelineStage']);

        return [
            'id' => $person->id,
            'url' => $this->getContactUrl($person->id),
            'name' => $person->name,
            'job_title' => $person->title,
            'organization' => $person->organization?->name,
            'owner' => $person->ownerUser?->name,
            'emails' => $person->emails->pluck('address')->toArray(),
            'phones' => $person->phones->pluck('number')->toArray(),
            'addresses' => $person->addresses->map(fn ($a) => $a->address)->toArray(),
            'linkedin' => $person->linkedin,
            'leads' => $person->leads->map(function ($l) {
                return [
                    'id' => $l->id,
                    'lead_id' => $l->lead_id,
                    'url' => $this->getLeadUrl($l->id),
                    'title' => $l->title,
                    'stage' => $l->pipelineStage?->name,
                    'relay_status' => $l->relay_status,
                ];
            })->toArray(),
            'created_at' => $person->created_at?->toIso8601String(),
        ];
    }

    /**
     * Query organizations by keyword, owner, year, date range.
     */
    public function queryOrganizations(array $args): array
    {
        $query = Organization::with(['ownerUser']);

        if (! empty($args['search'])) {
            $search = $args['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('website_url', 'like', "%{$search}%")
                    ->orWhere('linkedin', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($args['owner'])) {
            $user = $this->resolveUser($args['owner']);
            if ($user) {
                $query->where('user_owner_id', $user->id);
            } elseif (is_numeric($args['owner'])) {
                $query->where('user_owner_id', (int) $args['owner']);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($args['year'])) {
            $query->whereYear('created_at', (int) $args['year']);
        }

        if (! empty($args['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($args['created_before'])->endOfDay());
        }

        if (! empty($args['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($args['created_after'])->startOfDay());
        }

        if (! empty($args['has_linkedin'])) {
            if ($args['has_linkedin'] === 'yes') {
                $query->whereNotNull('linkedin')->where('linkedin', '!=', '');
            } elseif ($args['has_linkedin'] === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('linkedin')->orWhere('linkedin', '=', '');
                });
            }
        }

        $totalMatching = (clone $query)->count();
        $limit = min((int) ($args['limit'] ?? 10), 100);
        $results = $query->latest()->limit($limit)->get();

        $orgs = $results->map(function ($org) {
            return [
                'id' => $org->id,
                'url' => $this->getOrganizationUrl($org->id),
                'name' => $org->name,
                'owner' => $org->ownerUser?->name,
                'domain' => $org->domain,
                'website' => $org->website_url,
                'linkedin' => $org->linkedin,
                'people_count' => $org->people()->count(),
                'leads_count' => Lead::where('organization_id', $org->id)->count(),
                'created_at' => $org->created_at?->toIso8601String(),
            ];
        })->toArray();

        return [
            'total_matching' => $totalMatching,
            'returned_count' => count($orgs),
            'organizations' => $orgs,
        ];
    }

    /**
     * Retrieve full details of a specific lead.
     */
    public function getLeadDetails(array $args): array
    {
        $idOrRef = $args['lead_id'] ?? null;
        if (! $idOrRef) {
            return ['error' => 'lead_id is required'];
        }

        $lead = $this->resolveLead($idOrRef);

        if (! $lead) {
            return ['error' => "Lead {$idOrRef} not found"];
        }

        $lead->load(['person.emails', 'person.phones', 'organization', 'pipelineStage', 'ownerUser', 'assignedToUser']);

        $tasks = Task::where(function ($q) use ($lead) {
            $q->where('taskable_type', Lead::class)->where('taskable_id', $lead->id);
        })->orderBy('due_at', 'asc')->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'due_at' => $t->due_at?->toIso8601String(),
                'completed' => (bool) $t->completed_at,
                'completed_at' => $t->completed_at?->toIso8601String(),
            ];
        });

        return [
            'id' => $lead->id,
            'lead_id' => $lead->lead_id,
            'url' => $this->getLeadUrl($lead->id),
            'title' => $lead->title,
            'description' => $lead->description,
            'stage' => $lead->pipelineStage ? $lead->pipelineStage->name : null,
            'relay_status' => $lead->relay_status,
            'owner' => $lead->ownerUser?->name,
            'assigned_to' => $lead->assignedToUser?->name,
            'linkedin' => $lead->linkedin,
            'person' => $lead->person ? [
                'name' => $lead->person->name,
                'emails' => $lead->person->emails->pluck('address')->toArray(),
                'phones' => $lead->person->phones->pluck('number')->toArray(),
            ] : null,
            'organization' => $lead->organization ? $lead->organization->name : null,
            'tasks' => $tasks->toArray(),
        ];
    }

    /**
     * Query tasks by filter (overdue, due_today, pending, completed, all), owner, assigned user, year, or date range.
     */
    public function queryTasks(array $args): array
    {
        $query = Task::with(['ownerUser', 'assignedToUser']);

        $filter = $args['filter'] ?? null;

        if ($filter === 'overdue') {
            $query->whereNull('completed_at')
                ->whereNotNull('due_at')
                ->where('due_at', '<', Carbon::now());
        } elseif ($filter === 'due_today') {
            $query->whereNull('completed_at')
                ->whereDate('due_at', Carbon::today());
        } elseif ($filter === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($filter === 'completed') {
            $query->whereNotNull('completed_at');
        }

        if (! empty($args['search'])) {
            $search = $args['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($args['owner'])) {
            $user = $this->resolveUser($args['owner']);
            if ($user) {
                $query->where('user_owner_id', $user->id);
            } elseif (is_numeric($args['owner'])) {
                $query->where('user_owner_id', (int) $args['owner']);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($args['assigned_to'])) {
            $user = $this->resolveUser($args['assigned_to']);
            if ($user) {
                $query->where('user_assigned_id', $user->id);
            } elseif (is_numeric($args['assigned_to'])) {
                $query->where('user_assigned_id', (int) $args['assigned_to']);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (! empty($args['year'])) {
            $year = (int) $args['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('created_at', $year)
                    ->orWhereYear('completed_at', $year);
            });
        }

        if (! empty($args['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($args['created_before'])->endOfDay());
        }

        if (! empty($args['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($args['created_after'])->startOfDay());
        }

        if (! empty($args['lead_id'])) {
            $lead = $this->resolveLead($args['lead_id']);
            if ($lead) {
                $query->where('taskable_type', Lead::class)->where('taskable_id', $lead->id);
            }
        }

        $totalMatching = (clone $query)->count();
        $limit = min((int) ($args['limit'] ?? 10), 100);
        $tasks = $query->orderBy('due_at', 'asc')->limit($limit)->get();

        $taskList = $tasks->map(function ($task) {
            $linkedLead = null;
            if ($task->taskable instanceof Lead) {
                $linkedLead = [
                    'id' => $task->taskable->id,
                    'lead_id' => $task->taskable->lead_id,
                    'url' => $this->getLeadUrl($task->taskable->id),
                    'title' => $task->taskable->title,
                ];
            }

            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'due_at' => $task->due_at?->toIso8601String(),
                'completed' => (bool) $task->completed_at,
                'completed_at' => $task->completed_at?->toIso8601String(),
                'created_at' => $task->created_at?->toIso8601String(),
                'owner' => $task->ownerUser?->name,
                'assigned_to' => $task->assignedToUser?->name,
                'linked_lead' => $linkedLead,
            ];
        })->toArray();

        return [
            'total_matching' => $totalMatching,
            'returned_count' => count($taskList),
            'tasks' => $taskList,
        ];
    }

    /**
     * Create a task and attach to a lead.
     */
    public function createTask(array $args): array
    {
        $name = $args['name'] ?? null;
        if (! $name) {
            return ['error' => 'Task name is required'];
        }

        $taskableType = null;
        $taskableId = null;

        if (! empty($args['lead_id'])) {
            $lead = $this->resolveLead($args['lead_id']);

            if ($lead) {
                $taskableType = Lead::class;
                $taskableId = $lead->id;
            }
        }

        $dueAt = ! empty($args['due_at']) ? Carbon::parse($args['due_at']) : Carbon::now()->addDay();

        $task = Task::create([
            'name' => $name,
            'description' => $args['description'] ?? null,
            'due_at' => $dueAt,
            'taskable_type' => $taskableType,
            'taskable_id' => $taskableId,
            'user_assigned_id' => $args['assigned_user_id'] ?? 1,
            'user_owner_id' => 1,
        ]);

        return [
            'success' => true,
            'task_id' => $task->id,
            'name' => $task->name,
            'due_at' => $task->due_at?->toIso8601String(),
            'linked_lead_id' => $lead->lead_id ?? null,
        ];
    }

    /**
     * Mark a task as completed and trigger lead stage automation if applicable.
     */
    public function completeTask(array $args): array
    {
        $taskId = $args['task_id'] ?? null;
        if (! $taskId) {
            return ['error' => 'task_id is required'];
        }

        $task = Task::find($taskId);
        if (! $task) {
            return ['error' => "Task #{$taskId} not found"];
        }

        $task->update([
            'completed_at' => Carbon::now(),
        ]);

        // Attempt automated lead stage progression
        $advancedLead = $this->leadAutomation->handleTaskCompleted($task);

        return [
            'success' => true,
            'task_id' => $task->id,
            'name' => $task->name,
            'completed_at' => $task->completed_at->toIso8601String(),
            'lead_advanced' => $advancedLead ? [
                'lead_id' => $advancedLead->lead_id,
                'title' => $advancedLead->title,
                'new_stage' => $advancedLead->pipelineStage ? $advancedLead->pipelineStage->name : null,
            ] : null,
        ];
    }

    /**
     * Update lead pipeline stage or relay status.
     */
    public function updateLeadStage(array $args): array
    {
        $leadRef = $args['lead_id'] ?? null;
        if (! $leadRef) {
            return ['error' => 'lead_id is required'];
        }

        $lead = $this->resolveLead($leadRef);

        if (! $lead) {
            return ['error' => "Lead {$leadRef} not found"];
        }

        $updates = [];

        if (! empty($args['stage'])) {
            $stageTerm = $args['stage'];
            $stage = is_numeric($stageTerm)
                ? PipelineStage::find($stageTerm)
                : PipelineStage::where('name', 'like', "%{$stageTerm}%")->first();

            if ($stage) {
                $updates['pipeline_stage_id'] = $stage->id;
            }
        }

        if (! empty($args['relay_status'])) {
            $updates['relay_status'] = $args['relay_status'];
        }

        if (! empty($updates)) {
            $lead->update($updates);
        }

        $lead->refresh();

        return [
            'success' => true,
            'lead_id' => $lead->lead_id,
            'title' => $lead->title,
            'stage' => $lead->pipelineStage ? $lead->pipelineStage->name : null,
            'relay_status' => $lead->relay_status,
        ];
    }

    /**
     * Delete or soft-delete a lead and clean up its associated pending tasks.
     */
    public function deleteLead(array $args): array
    {
        $leadRef = $args['lead_id'] ?? null;
        if (! $leadRef) {
            return ['error' => 'lead_id is required'];
        }

        $lead = $this->resolveLead($leadRef);

        if (! $lead) {
            return ['error' => "Lead {$leadRef} not found"];
        }

        $leadId = $lead->lead_id;
        $title = $lead->title;

        // Clean up pending tasks attached to this lead
        Task::where(function ($q) {
            $q->where('taskable_type', Lead::class)
                ->orWhere('taskable_type', (new Lead)->getMorphClass());
        })->where('taskable_id', $lead->id)->delete();

        $lead->delete();

        return [
            'success' => true,
            'lead_id' => $leadId,
            'title' => $title,
            'status' => 'deleted',
        ];
    }

    /**
     * Bulk delete tasks by specific task IDs, year (e.g. 2023), before_date, or filter.
     */
    public function deleteTasks(array $args): array
    {
        $query = Task::query();

        if (! empty($args['task_ids'])) {
            $ids = is_array($args['task_ids']) ? $args['task_ids'] : [$args['task_ids']];
            $query->whereIn('id', $ids);
        } elseif (! empty($args['year'])) {
            $year = (int) $args['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('created_at', $year)
                    ->orWhereYear('completed_at', $year);
            });

            if (! empty($args['filter']) && $args['filter'] === 'completed') {
                $query->whereNotNull('completed_at');
            } elseif (! empty($args['filter']) && $args['filter'] === 'pending') {
                $query->whereNull('completed_at');
            }
        } elseif (! empty($args['before_date'])) {
            $query->where('created_at', '<=', Carbon::parse($args['before_date'])->endOfDay());
        } else {
            return ['error' => 'Must provide task_ids, year, or before_date to delete tasks.'];
        }

        $count = $query->count();
        if ($count === 0) {
            return [
                'success' => true,
                'deleted_count' => 0,
                'message' => 'No matching tasks found to delete.',
            ];
        }

        $query->delete();

        return [
            'success' => true,
            'deleted_count' => $count,
            'message' => "Successfully deleted {$count} task(s).",
        ];
    }

    /**
     * Bulk delete leads by specific lead IDs, year (e.g. 2023), or before_date.
     * Automatically deletes all tasks attached to those leads.
     */
    public function deleteLeads(array $args): array
    {
        $query = Lead::query();

        if (! empty($args['lead_ids'])) {
            $resolvedIds = [];
            $leadRefs = is_array($args['lead_ids']) ? $args['lead_ids'] : [$args['lead_ids']];
            foreach ($leadRefs as $ref) {
                $lead = $this->resolveLead($ref);
                if ($lead) {
                    $resolvedIds[] = $lead->id;
                }
            }
            if (empty($resolvedIds)) {
                return ['error' => 'None of the provided lead IDs could be resolved in the CRM.'];
            }
            $query->whereIn('id', $resolvedIds);
        } else {
            $hasFilter = false;

            if (! empty($args['year'])) {
                $query->whereYear('created_at', (int) $args['year']);
                $hasFilter = true;
            }

            if (! empty($args['before_date'])) {
                $query->where('created_at', '<=', Carbon::parse($args['before_date'])->endOfDay());
                $hasFilter = true;
            }

            if (! empty($args['owner'])) {
                $user = $this->resolveUser($args['owner']);
                if ($user) {
                    $query->where('user_owner_id', $user->id);
                } elseif (is_numeric($args['owner'])) {
                    $query->where('user_owner_id', (int) $args['owner']);
                } else {
                    $query->whereRaw('0 = 1');
                }
                $hasFilter = true;
            }

            if (! empty($args['has_tasks'])) {
                if ($args['has_tasks'] === 'yes') {
                    $query->has('tasks');
                } elseif ($args['has_tasks'] === 'no') {
                    $query->doesntHave('tasks');
                } elseif ($args['has_tasks'] === 'overdue') {
                    $query->whereHas('tasks', function ($tq) {
                        $tq->whereNull('completed_at')->whereNotNull('due_at')->where('due_at', '<', Carbon::now());
                    });
                }
                $hasFilter = true;
            }

            if (! empty($args['has_linkedin'])) {
                if ($args['has_linkedin'] === 'yes') {
                    $query->where(function ($q) {
                        $q->where(function ($lq) {
                            $lq->whereNotNull('linkedin')->where('linkedin', '!=', '');
                        })->orWhereHas('person', function ($pq) {
                            $pq->whereNotNull('linkedin')->where('linkedin', '!=', '');
                        });
                    });
                } elseif ($args['has_linkedin'] === 'no') {
                    $query->where(function ($q) {
                        $q->whereNull('linkedin')->orWhere('linkedin', '=', '');
                    })->where(function ($q) {
                        $q->whereDoesntHave('person')
                            ->orWhereHas('person', function ($pq) {
                                $pq->whereNull('linkedin')->orWhere('linkedin', '=', '');
                            });
                    });
                }
                $hasFilter = true;
            }

            if (! $hasFilter) {
                return ['error' => 'Must provide lead_ids, or filter criteria (year, before_date, has_tasks, has_linkedin, owner) to delete leads.'];
            }
        }

        if (! empty($args['limit'])) {
            $leads = $query->limit((int) $args['limit'])->get();
        } else {
            $leads = $query->get();
        }
        $count = $leads->count();

        if ($count === 0) {
            return [
                'success' => true,
                'deleted_count' => 0,
                'message' => 'No matching leads found to delete.',
            ];
        }

        $deletedLeadIds = $leads->pluck('lead_id')->filter()->values()->toArray();
        $internalIds = $leads->pluck('id')->toArray();

        // Clean up tasks attached to these leads
        Task::where(function ($q) {
            $q->where('taskable_type', Lead::class)
                ->orWhere('taskable_type', (new Lead)->getMorphClass());
        })->whereIn('taskable_id', $internalIds)->delete();

        // Delete leads
        Lead::whereIn('id', $internalIds)->delete();

        return [
            'success' => true,
            'deleted_count' => $count,
            'deleted_lead_ids' => array_slice($deletedLeadIds, 0, 20),
            'message' => "Successfully deleted {$count} lead(s) and their attached tasks.",
        ];
    }

    /**
     * Scan and synthesize lead & task alerts for the Heartbeat.
     */
    public function getLeadAlerts(): array
    {
        $now = Carbon::now();
        $staleDays = config('laravel-crm.agent.stale_lead_days', 3);
        $staleThreshold = $now->copy()->subDays($staleDays);

        // 1. Overdue tasks linked to leads
        $overdueTasks = Task::whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->where(function ($q) {
                $q->where('taskable_type', Lead::class)
                    ->orWhere('taskable_type', (new Lead)->getMorphClass());
            })
            ->with('taskable')
            ->orderBy('due_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'task_id' => $t->id,
                    'name' => $t->name,
                    'due_at' => $t->due_at?->diffForHumans(),
                    'lead_id' => $t->taskable instanceof Lead ? $t->taskable->lead_id : null,
                    'lead_url' => $t->taskable instanceof Lead ? $this->getLeadUrl($t->taskable->id) : null,
                    'lead_title' => $t->taskable instanceof Lead ? $t->taskable->title : null,
                ];
            })->toArray();

        // 2. Active relay leads with no task or activity in > 3 days
        $staleRelayLeads = Lead::where('relay_status', 'active')
            ->where('updated_at', '<', $staleThreshold)
            ->limit(5)
            ->get(['id', 'lead_id', 'title', 'relay_status', 'updated_at'])
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'lead_id' => $l->lead_id,
                    'url' => $this->getLeadUrl($l->id),
                    'title' => $l->title,
                    'last_updated' => $l->updated_at?->diffForHumans(),
                ];
            })->toArray();

        // 3. Leads with all tasks completed (ready for next stage advancement)
        $readyToAdvanceLeads = Lead::whereHas('tasks', function ($q) {
            $q->whereNotNull('completed_at');
        })
            ->whereDoesntHave('tasks', function ($q) {
                $q->whereNull('completed_at');
            })
            ->where('pipeline_stage_id', '<', 4) // not already closed/advanced
            ->with('pipelineStage')
            ->limit(5)
            ->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'lead_id' => $l->lead_id,
                    'url' => $this->getLeadUrl($l->id),
                    'title' => $l->title,
                    'current_stage' => $l->pipelineStage?->name,
                ];
            })->toArray();

        return [
            'overdue_lead_tasks' => $overdueTasks,
            'stale_active_leads' => $staleRelayLeads,
            'ready_to_advance_leads' => $readyToAdvanceLeads,
            'has_urgent_alerts' => count($overdueTasks) > 0 || count($staleRelayLeads) > 0,
        ];
    }

    /**
     * Normalize free-text or alias mode input into one of: high, balanced, low, silent.
     */
    public function normalizeProactivityMode(?string $input): string
    {
        if (! $input) {
            return config('laravel-crm.agent.default_proactivity_mode', 'balanced');
        }

        $clean = strtolower(trim($input));

        return match (true) {
            in_array($clean, ['high', 'most', 'most_proactive', 'most proactive', 'proactive', 'relentless', 'war_room', 'war room', 'aggressive', 'max', '1h'], true) => 'high',
            in_array($clean, ['balanced', 'normal', 'default', 'smart', 'delta', 'smart_delta', 'medium', 'moderate', '4h'], true) => 'balanced',
            in_array($clean, ['low', 'digest', 'daily', 'daily_digest', 'daily digest', 'least', 'minimal', 'quiet', '24h', 'once_a_day'], true) => 'low',
            in_array($clean, ['silent', 'off', 'mute', 'muted', 'none', 'least_proactive', 'least proactive', 'on_demand', 'on-demand', 'ondemand', 'disable', 'stop'], true) => 'silent',
            isset(self::PROACTIVITY_MODES[$clean]) => $clean,
            default => 'balanced',
        };
    }

    /**
     * Retrieve the persisted notification & proactivity state (DB + Cache).
     */
    public function getNotificationState(): array
    {
        $defaultMode = $this->normalizeProactivityMode(config('laravel-crm.agent.default_proactivity_mode', 'balanced'));
        $defaults = [
            'mode' => $defaultMode,
            'snoozed_until' => null,
            'last_notified_at' => null,
            'last_alert_hash' => null,
            'updated_at' => null,
        ];

        $cached = Cache::get(self::NOTIFICATION_STATE_KEY);
        if (is_array($cached)) {
            $state = array_merge($defaults, $cached);
            $state['mode'] = $this->normalizeProactivityMode($state['mode'] ?? $defaultMode);

            return $state;
        }

        try {
            $table = (new Setting)->getTable();
            if (Schema::hasTable($table)) {
                $row = Setting::query()
                    ->withoutGlobalScope(BelongsToTeamsScope::class)
                    ->where('name', self::NOTIFICATION_STATE_KEY)
                    ->first();

                if ($row && ! empty($row->value)) {
                    $decoded = is_array($row->value) ? $row->value : json_decode((string) $row->value, true);
                    if (is_array($decoded)) {
                        $state = array_merge($defaults, $decoded);
                        $state['mode'] = $this->normalizeProactivityMode($state['mode'] ?? $defaultMode);
                        Cache::forever(self::NOTIFICATION_STATE_KEY, $state);

                        return $state;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback to default state if DB not ready
        }

        return $defaults;
    }

    /**
     * Persist notification & proactivity state to both Cache and crm_settings table.
     */
    public function saveNotificationState(array $updates): array
    {
        $state = array_merge($this->getNotificationState(), $updates);
        $state['mode'] = $this->normalizeProactivityMode($state['mode'] ?? 'balanced');
        $state['updated_at'] = Carbon::now()->toIso8601String();

        Cache::forever(self::NOTIFICATION_STATE_KEY, $state);

        try {
            $table = (new Setting)->getTable();
            if (Schema::hasTable($table)) {
                $encoded = json_encode($state);
                $rows = Setting::query()
                    ->withoutGlobalScope(BelongsToTeamsScope::class)
                    ->where('name', self::NOTIFICATION_STATE_KEY)
                    ->get();

                if ($rows->isEmpty()) {
                    Setting::withoutEvents(function () use ($encoded) {
                        Setting::query()
                            ->withoutGlobalScope(BelongsToTeamsScope::class)
                            ->create([
                                'name' => self::NOTIFICATION_STATE_KEY,
                                'value' => $encoded,
                                'global' => 1,
                            ]);
                    });
                } else {
                    foreach ($rows as $row) {
                        Setting::withoutEvents(function () use ($row, $encoded) {
                            $row->value = $encoded;
                            $row->save();
                        });
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB errors when running in lightweight environments
        }

        return $state;
    }

    /**
     * Record that an autonomous heartbeat notification was just dispatched.
     */
    public function recordHeartbeatSent(string $alertHash): array
    {
        return $this->saveNotificationState([
            'last_notified_at' => Carbon::now()->toIso8601String(),
            'last_alert_hash' => $alertHash,
        ]);
    }

    /**
     * Tool handler: Get current notification frequency & proactivity mode settings.
     */
    public function getNotificationSettings(): array
    {
        $state = $this->getNotificationState();
        $modeKey = $state['mode'];
        $modeMeta = self::PROACTIVITY_MODES[$modeKey] ?? self::PROACTIVITY_MODES['balanced'];
        $now = Carbon::now();

        $snoozedUntil = ! empty($state['snoozed_until']) ? Carbon::parse($state['snoozed_until']) : null;
        $isSnoozed = $snoozedUntil && $snoozedUntil->isFuture();

        $lastNotified = ! empty($state['last_notified_at']) ? Carbon::parse($state['last_notified_at']) : null;

        return [
            'current_mode' => $modeKey,
            'mode_label' => $modeMeta['label'],
            'cooldown_minutes' => $modeMeta['cooldown_minutes'],
            'delta_only' => $modeMeta['delta_only'],
            'description' => $modeMeta['description'],
            'is_snoozed' => $isSnoozed,
            'snoozed_until' => $isSnoozed ? $snoozedUntil->toDateTimeString() : null,
            'snoozed_until_human' => $isSnoozed ? $snoozedUntil->diffForHumans() : null,
            'last_notified_at' => $lastNotified ? $lastNotified->toDateTimeString() : null,
            'last_notified_human' => $lastNotified ? $lastNotified->diffForHumans() : 'Never',
            'available_modes' => array_map(fn ($m) => [
                'mode' => $m['key'],
                'label' => $m['label'],
                'cooldown_minutes' => $m['cooldown_minutes'],
                'description' => $m['description'],
            ], self::PROACTIVITY_MODES),
        ];
    }

    /**
     * Tool handler: Update proactivity mode and/or snooze timer.
     */
    public function updateNotificationSettings(array $args): array
    {
        $updates = [];
        $changes = [];

        if (isset($args['mode']) && $args['mode'] !== '') {
            $normalized = $this->normalizeProactivityMode((string) $args['mode']);
            $updates['mode'] = $normalized;
            $meta = self::PROACTIVITY_MODES[$normalized];
            $changes[] = "Proactivity mode set to {$meta['label']} ({$meta['description']})";
        }

        if (array_key_exists('snooze_hours', $args) && $args['snooze_hours'] !== null) {
            $hours = (float) $args['snooze_hours'];
            if ($hours <= 0) {
                $updates['snoozed_until'] = null;
                $changes[] = 'Notifications unsnoozed (active immediately)';
            } else {
                $until = Carbon::now()->addMinutes((int) round($hours * 60));
                $updates['snoozed_until'] = $until->toIso8601String();
                $changes[] = "Autonomous alerts snoozed for {$hours}h (until {$until->format('H:i')})";
            }
        }

        if (! empty($args['clear_snooze'])) {
            $updates['snoozed_until'] = null;
            $changes[] = 'Snooze cleared';
        }

        if (! empty($updates)) {
            $this->saveNotificationState($updates);
        }

        $settings = $this->getNotificationSettings();
        $settings['success'] = true;
        $settings['message'] = ! empty($changes)
            ? implode('. ', $changes).'.'
            : "Current mode remains {$settings['mode_label']}.";

        return $settings;
    }

    /**
     * Build the inline keyboard attached to every autonomous heartbeat briefing.
     */
    public function buildAlertInlineKeyboard(): array
    {
        $state = $this->getNotificationState();
        $modeKey = $state['mode'];
        $modeMeta = self::PROACTIVITY_MODES[$modeKey] ?? self::PROACTIVITY_MODES['balanced'];

        return [
            'inline_keyboard' => [
                [
                    ['text' => '📋 Tasks List', 'callback_data' => 'view_tasks'],
                    ['text' => '🎯 Lead Alerts', 'callback_data' => 'view_alerts'],
                ],
                [
                    ['text' => '💤 Snooze 3h', 'callback_data' => 'agent_snooze:3'],
                    ['text' => "⚙️ Mode: {$modeMeta['short_label']}", 'callback_data' => 'agent_mode_menu'],
                ],
            ],
        ];
    }

    /**
     * Build the interactive Telegram Proactivity & Notification Control Deck message + inline keyboard.
     */
    public function buildModeControlDeck(?string $flashNotice = null): array
    {
        $settings = $this->getNotificationSettings();
        $currentMode = $settings['current_mode'];
        $isSnoozed = $settings['is_snoozed'];

        $lines = [];
        if ($flashNotice) {
            $lines[] = "✅ *{$flashNotice}*\n";
        }

        $lines[] = '🎛️ *AI Proactivity & Notification Control Deck*';
        $lines[] = "Current Mode: *{$settings['mode_label']}*";
        $lines[] = "Behavior: _{$settings['description']}_";

        if ($isSnoozed) {
            $lines[] = "⏳ Snooze Status: *Snoozed* (resumes {$settings['snoozed_until_human']} at `{$settings['snoozed_until']}`)";
        } else {
            $lines[] = '🔔 Snooze Status: *Active* (not snoozed)';
        }

        $lines[] = "🕒 Last Alert Sent: _{$settings['last_notified_human']}_";
        $lines[] = '';
        $lines[] = '*Select Proactivity Mode:*';
        $lines[] = '• 🔥 *High (1h)* — Most proactive, all vectors & next-step pushes';
        $lines[] = '• ⚡ *Balanced (4h Δ)* — Smart delta only when alerts change *(Recommended)*';
        $lines[] = '• ☕ *Daily Digest (24h)* — 1x daily summary, zero mid-day noise';
        $lines[] = '• 🔕 *Silent (Off)* — Least proactive, responds only when you ask';

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => ($currentMode === 'high' ? '🔥 High (1h) ✓' : '🔥 High (1h)'),
                        'callback_data' => 'agent_mode:high',
                    ],
                    [
                        'text' => ($currentMode === 'balanced' ? '⚡ Balanced (4h Δ) ✓' : '⚡ Balanced (4h Δ)'),
                        'callback_data' => 'agent_mode:balanced',
                    ],
                ],
                [
                    [
                        'text' => ($currentMode === 'low' ? '☕ Daily Digest ✓' : '☕ Daily Digest'),
                        'callback_data' => 'agent_mode:low',
                    ],
                    [
                        'text' => ($currentMode === 'silent' ? '🔕 Silent (Off) ✓' : '🔕 Silent (Off)'),
                        'callback_data' => 'agent_mode:silent',
                    ],
                ],
                [
                    ['text' => '💤 Snooze 2h', 'callback_data' => 'agent_snooze:2'],
                    ['text' => '🌙 Snooze 8h', 'callback_data' => 'agent_snooze:8'],
                    $isSnoozed
                        ? ['text' => '🔔 Resume Now', 'callback_data' => 'agent_snooze:0']
                        : ['text' => '📋 Tasks', 'callback_data' => 'view_tasks'],
                ],
            ],
        ];

        return [
            'text' => implode("\n", $lines),
            'reply_markup' => $keyboard,
        ];
    }
}
