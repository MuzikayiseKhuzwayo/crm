<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits;

use DateTimeZone;
use VentureDrake\LaravelCrm\Models\LinkedinCampaign;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;

trait HasSequenceAndSafetyForm
{
    public string $name = '';

    public string $status = LinkedinCampaign::STATUS_DRAFT;

    /** @var array<int, array<string, mixed>> */
    public array $sequence_steps = [];

    public int $daily_connect_limit = 15;

    public int $daily_dm_limit = 30;

    public int $weekly_connect_limit = 80;

    public int $min_delay_seconds = 180;

    public int $max_delay_seconds = 420;

    /** @var array<int, int|string> */
    public array $active_days = [1, 2, 3, 4, 5];

    public int $active_start_hour = 8;

    public int $active_end_hour = 18;

    public string $timezone = 'Africa/Johannesburg';

    protected function initDefaultSequenceSteps(): void
    {
        $this->sequence_steps = [
            [
                'step' => 1,
                'action' => 'connect',
                'delay_hours' => 0,
                'template_id' => null,
                'body' => 'Hi {{first_name}}, {{personalized_opener}} Would love to connect!',
            ],
            [
                'step' => 2,
                'action' => 'dm',
                'delay_hours' => 4,
                'template_id' => null,
                'body' => 'Thanks for connecting, {{first_name}}! Curious how you are handling outreach at {{company}} right now?',
            ],
            [
                'step' => 3,
                'action' => 'follow_up',
                'delay_hours' => 72,
                'template_id' => null,
                'body' => 'Just bumping this to the top of your inbox, {{first_name}} — happy to share a quick 2-minute walkthrough if helpful.',
            ],
        ];
    }

    protected function fillFormFromCampaign(LinkedinCampaign $campaign): void
    {
        $this->name = (string) $campaign->name;
        $this->status = (string) $campaign->status;
        $this->daily_connect_limit = (int) $campaign->daily_connect_limit;
        $this->daily_dm_limit = (int) $campaign->daily_dm_limit;
        $this->weekly_connect_limit = (int) $campaign->weekly_connect_limit;
        $this->min_delay_seconds = (int) $campaign->min_delay_seconds;
        $this->max_delay_seconds = (int) $campaign->max_delay_seconds;
        $this->active_days = array_values(array_map('intval', is_array($campaign->active_days) ? $campaign->active_days : [1, 2, 3, 4, 5]));
        $this->active_start_hour = (int) $campaign->active_start_hour;
        $this->active_end_hour = (int) $campaign->active_end_hour;
        $this->timezone = (string) ($campaign->timezone ?: 'Africa/Johannesburg');

        $steps = is_array($campaign->sequence_steps) ? $campaign->sequence_steps : [];
        if (empty($steps)) {
            $this->sequence_steps = [];
        } else {
            usort($steps, static fn (array $a, array $b): int => ((int) ($a['step'] ?? 0)) <=> ((int) ($b['step'] ?? 0)));
            $this->sequence_steps = array_values(array_map(static function (array $step, int $idx): array {
                return [
                    'step' => $idx + 1,
                    'action' => (string) ($step['action'] ?? ($idx === 0 ? 'connect' : 'dm')),
                    'delay_hours' => (int) ($step['delay_hours'] ?? ($idx === 0 ? 0 : 24)),
                    'template_id' => ! empty($step['template_id']) ? (int) $step['template_id'] : null,
                    'body' => (string) ($step['body'] ?? ''),
                ];
            }, $steps, array_keys($steps)));
        }
    }

    public function addStep(string $action = 'follow_up'): void
    {
        $nextNumber = count($this->sequence_steps) + 1;

        if ($nextNumber === 1) {
            $action = 'connect';
        }

        $this->sequence_steps[] = [
            'step' => $nextNumber,
            'action' => $action,
            'delay_hours' => $action === 'connect' ? 0 : ($action === 'dm' ? 4 : 72),
            'template_id' => null,
            'body' => '',
        ];
    }

    public function removeStep(int $index): void
    {
        if (! isset($this->sequence_steps[$index])) {
            return;
        }

        unset($this->sequence_steps[$index]);
        $this->reindexSequenceSteps();
    }

    public function moveStepUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->sequence_steps[$index])) {
            return;
        }

        [$this->sequence_steps[$index - 1], $this->sequence_steps[$index]] = [
            $this->sequence_steps[$index],
            $this->sequence_steps[$index - 1],
        ];

        $this->reindexSequenceSteps();
    }

    public function moveStepDown(int $index): void
    {
        if (! isset($this->sequence_steps[$index]) || ! isset($this->sequence_steps[$index + 1])) {
            return;
        }

        [$this->sequence_steps[$index], $this->sequence_steps[$index + 1]] = [
            $this->sequence_steps[$index + 1],
            $this->sequence_steps[$index],
        ];

        $this->reindexSequenceSteps();
    }

    protected function reindexSequenceSteps(): void
    {
        $this->sequence_steps = array_values($this->sequence_steps);

        foreach ($this->sequence_steps as $i => &$step) {
            $step['step'] = $i + 1;
        }
        unset($step);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizedSequenceSteps(): array
    {
        $normalized = [];

        foreach (array_values($this->sequence_steps) as $i => $step) {
            $templateId = isset($step['template_id']) && $step['template_id'] !== '' && $step['template_id'] !== null
                ? (int) $step['template_id']
                : null;

            $item = [
                'step' => $i + 1,
                'action' => (string) ($step['action'] ?? 'connect'),
                'delay_hours' => max(0, (int) ($step['delay_hours'] ?? 0)),
            ];

            if ($templateId !== null) {
                $item['template_id'] = $templateId;
            }

            $body = trim((string) ($step['body'] ?? ''));
            if ($body !== '') {
                $item['body'] = $body;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sequenceValidationRules(): array
    {
        return [
            'sequence_steps' => 'required|array|min:1',
            'sequence_steps.*.action' => 'required|in:connect,dm,follow_up',
            'sequence_steps.*.delay_hours' => 'required|integer|min:0|max:2160',
            'sequence_steps.*.template_id' => 'nullable|integer',
            'sequence_steps.*.body' => 'nullable|string|max:8000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function safetyValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'daily_connect_limit' => 'required|integer|min:1|max:100',
            'daily_dm_limit' => 'required|integer|min:1|max:200',
            'weekly_connect_limit' => 'required|integer|min:1|max:500',
            'min_delay_seconds' => 'required|integer|min:10|max:3600',
            'max_delay_seconds' => 'required|integer|gte:min_delay_seconds|max:7200',
            'active_days' => 'required|array|min:1',
            'active_days.*' => 'integer|between:1,7',
            'active_start_hour' => 'required|integer|between:0,23',
            'active_end_hour' => 'required|integer|gt:active_start_hour|max:24',
            'timezone' => 'required|timezone',
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function stepActionOptions(): array
    {
        return [
            ['id' => 'connect', 'name' => 'Send Connection Request'],
            ['id' => 'dm', 'name' => 'Send Direct Message (DM)'],
            ['id' => 'follow_up', 'name' => 'Send Follow-up Message'],
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function weekdayOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Mon'],
            ['id' => 2, 'label' => 'Tue'],
            ['id' => 3, 'label' => 'Wed'],
            ['id' => 4, 'label' => 'Thu'],
            ['id' => 5, 'label' => 'Fri'],
            ['id' => 6, 'label' => 'Sat'],
            ['id' => 7, 'label' => 'Sun'],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function timezoneOptions(): array
    {
        $common = [
            'Africa/Johannesburg',
            'UTC',
            'Europe/London',
            'Europe/Berlin',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'Asia/Dubai',
            'Asia/Singapore',
            'Australia/Sydney',
        ];

        if ($this->timezone && ! in_array($this->timezone, $common, true) && in_array($this->timezone, DateTimeZone::listIdentifiers(), true)) {
            array_unshift($common, $this->timezone);
        }

        return array_map(static fn (string $tz): array => ['id' => $tz, 'name' => $tz], $common);
    }

    public function templates()
    {
        return LinkedinTemplate::orderBy('type')->orderBy('name')->get();
    }
}
