<?php

namespace VentureDrake\LaravelCrm\Observers;

use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\ChatConversation;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Services\NumberGeneratorService;
use VentureDrake\LaravelCrm\Services\SettingService;

class LeadObserver
{
    /**
     * @var SettingService
     */
    private $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Handle the lead "creating" event.
     *
     * @return void
     */
    public function creating(Lead $lead)
    {
        $lead->external_id = Uuid::uuid4()->toString();

        if (! app()->runningInConsole()) {
            $lead->user_created_id = auth()->user()->id ?? null;
        }

        $lead->number = NumberGeneratorService::next(Lead::class, 1000);

        $lead->prefix = $this->settingService->get('lead_prefix');
        $lead->lead_id = $lead->prefix.$lead->number;
    }

    /**
     * Handle the lead "created" event.
     *
     * @return void
     */
    public function created(Lead $lead)
    {
        //
    }

    /**
     * Handle the lead "updating" event.
     *
     * @return void
     */
    public function updating(Lead $lead)
    {
        if (! app()->runningInConsole()) {
            $lead->user_updated_id = auth()->user()->id ?? null;
        }
    }

    /**
     * Handle the lead "updated" event.
     *
     * @return void
     */
    public function updated(Lead $lead)
    {
        if ($lead->wasChanged('relay_status')) {
            $status = $lead->relay_status;

            if ($status === 'fallen_off' || $status === 'disqualified') {
                // Operational cog: When a lead falls off or is disqualified, remove/soft-delete all its incomplete tasks ("unless completed")
                $lead->tasks()->whereNull('completed_at')->each(function ($task) use ($status) {
                    $note = "[Account Relay: Inactive due to lead {$status}]";
                    $task->description = trim(($task->description ?? '')."\n".$note);
                    $task->saveQuietly();
                    $task->delete();
                });
            } elseif ($status === 'active') {
                // Operational cog: When a lead rotates to active from standby, restore any soft-deleted tasks
                // and advance overdue dates that expired while waiting on standby
                $lead->tasks()->onlyTrashed()->each(function ($task) {
                    $task->restore();
                });

                $lead->tasks()->whereNull('completed_at')->each(function ($task) {
                    if ($task->due_at && $task->due_at->isPast()) {
                        $task->update(['due_at' => now()->addDay()]);
                    }
                });
            }
        }
    }

    /**
     * Handle the lead "deleting" event.
     *
     * @param  \VentureDrake\LaravelCrm\Lead  $lead
     * @return void
     */
    public function deleting(Lead $lead)
    {
        if (! app()->runningInConsole()) {
            $lead->user_deleted_id = auth()->user()->id ?? null;
            $lead->saveQuietly();
        }

        // Cascade soft delete related tasks
        $lead->tasks()->each(function ($task) {
            $task->delete();
        });

        // Cascade soft delete related activities (calls, meetings, lunches, notes, files)
        $lead->calls()->each(function ($call) {
            $call->delete();
        });
        $lead->meetings()->each(function ($meeting) {
            $meeting->delete();
        });
        $lead->lunches()->each(function ($lunch) {
            $lunch->delete();
        });
        $lead->notes()->each(function ($note) {
            $note->delete();
        });
        $lead->files()->each(function ($file) {
            $file->delete();
        });

        // Cascade delete timeline activity records for this lead
        $lead->activities()->each(function ($activity) {
            $activity->delete();
        });
        Activity::where('recordable_type', $lead->getMorphClass())
            ->where('recordable_id', $lead->id)
            ->each(function ($activity) {
                $activity->delete();
            });

        // Delete direct contact details attached directly to lead
        $lead->emails()->each(function ($email) {
            $email->delete();
        });
        $lead->phones()->each(function ($phone) {
            $phone->delete();
        });
        $lead->addresses()->each(function ($address) {
            $address->delete();
        });
        $lead->customFieldValues()->each(function ($fieldValue) {
            $fieldValue->delete();
        });

        // Detach labels
        $lead->labels()->detach();

        // Nullify foreign references on chat conversations, deals, quotes, orders
        if (Schema::hasTable(config('laravel-crm.db_table_prefix').'chat_conversations')) {
            ChatConversation::where('lead_id', $lead->id)->update(['lead_id' => null]);
        }
        Deal::where('lead_id', $lead->id)->update(['lead_id' => null]);
        Quote::where('lead_id', $lead->id)->update(['lead_id' => null]);
        Order::where('lead_id', $lead->id)->update(['lead_id' => null]);
    }

    /**
     * Handle the lead "deleted" event.
     *
     * @return void
     */
    public function deleted(Lead $lead)
    {
        //
    }

    /**
     * Handle the lead "restored" event.
     *
     * @return void
     */
    public function restored(Lead $lead)
    {
        if (! app()->runningInConsole()) {
            $lead->user_deleted_id = null;
            $lead->saveQuietly();
        }

        $lead->tasks()->onlyTrashed()->each(function ($task) {
            $task->restore();
        });
        $lead->calls()->onlyTrashed()->each(function ($call) {
            $call->restore();
        });
        $lead->meetings()->onlyTrashed()->each(function ($meeting) {
            $meeting->restore();
        });
        $lead->lunches()->onlyTrashed()->each(function ($lunch) {
            $lunch->restore();
        });
        $lead->notes()->onlyTrashed()->each(function ($note) {
            $note->restore();
        });
        $lead->files()->onlyTrashed()->each(function ($file) {
            $file->restore();
        });
        $lead->activities()->onlyTrashed()->each(function ($activity) {
            $activity->restore();
        });
        Activity::onlyTrashed()
            ->where('recordable_type', $lead->getMorphClass())
            ->where('recordable_id', $lead->id)
            ->each(function ($activity) {
                $activity->restore();
            });
        $lead->emails()->onlyTrashed()->each(function ($email) {
            $email->restore();
        });
        $lead->phones()->onlyTrashed()->each(function ($phone) {
            $phone->restore();
        });
        $lead->addresses()->onlyTrashed()->each(function ($address) {
            $address->restore();
        });
        $lead->customFieldValues()->onlyTrashed()->each(function ($fieldValue) {
            $fieldValue->restore();
        });
    }

    /**
     * Handle the lead "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Lead $lead)
    {
        $lead->tasks()->withTrashed()->each(function ($task) {
            $task->forceDelete();
        });
        $lead->calls()->withTrashed()->each(function ($call) {
            $call->forceDelete();
        });
        $lead->meetings()->withTrashed()->each(function ($meeting) {
            $meeting->forceDelete();
        });
        $lead->lunches()->withTrashed()->each(function ($lunch) {
            $lunch->forceDelete();
        });
        $lead->notes()->withTrashed()->each(function ($note) {
            $note->forceDelete();
        });
        $lead->files()->withTrashed()->each(function ($file) {
            $file->forceDelete();
        });
        $lead->activities()->withTrashed()->each(function ($activity) {
            $activity->forceDelete();
        });
        Activity::withTrashed()
            ->where('recordable_type', $lead->getMorphClass())
            ->where('recordable_id', $lead->id)
            ->each(function ($activity) {
                $activity->forceDelete();
            });
        $lead->emails()->withTrashed()->each(function ($email) {
            $email->forceDelete();
        });
        $lead->phones()->withTrashed()->each(function ($phone) {
            $phone->forceDelete();
        });
        $lead->addresses()->withTrashed()->each(function ($address) {
            $address->forceDelete();
        });
        $lead->customFieldValues()->withTrashed()->each(function ($fieldValue) {
            $fieldValue->forceDelete();
        });
    }
}
