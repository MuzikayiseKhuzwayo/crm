<?php

namespace VentureDrake\LaravelCrm\Services;

use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LinkedinCampaignRecipient;
use VentureDrake\LaravelCrm\Models\LinkedinTemplate;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;

class LinkedinMessageRenderer
{
    public const CONNECT_NOTE_MAX_LENGTH = 300;

    public const DM_MAX_LENGTH = 8000;

    /**
     * Render a template for a recipient, returning the final message string
     * enforced against LinkedIn character limits.
     */
    public function render(LinkedinTemplate $template, LinkedinCampaignRecipient $recipient): string
    {
        return $this->renderWithMetadata($template, $recipient)['body'];
    }

    /**
     * Render a template for a recipient and return character-limit metadata.
     *
     * @return array{
     *     body: string,
     *     truncated: bool,
     *     original_length: int,
     *     max_length: int
     * }
     */
    public function renderWithMetadata(LinkedinTemplate $template, LinkedinCampaignRecipient $recipient): array
    {
        $variables = $this->extractVariables($recipient);

        return $this->renderBody((string) $template->body, $variables, $template->type);
    }

    /**
     * Render an arbitrary template body string against variables and optional type limit.
     *
     * @param  array<string, string|null>  $variables
     * @return array{
     *     body: string,
     *     truncated: bool,
     *     original_length: int,
     *     max_length: int
     * }
     */
    public function renderBody(string $body, array $variables, ?string $type = null): array
    {
        $rendered = (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)(?:\|([^}]*))?\s*\}\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];
                $fallback = isset($matches[2]) ? trim($matches[2]) : '';
                $value = isset($variables[$key]) ? trim((string) $variables[$key]) : '';

                return $value !== '' ? $value : $fallback;
            },
            $body
        );

        $rendered = $this->normalizeWhitespace($rendered);
        $maxLength = $this->maxLengthForType($type);
        $originalLength = mb_strlen($rendered, 'UTF-8');

        if ($originalLength > $maxLength) {
            return [
                'body' => $this->truncate($rendered, $maxLength),
                'truncated' => true,
                'original_length' => $originalLength,
                'max_length' => $maxLength,
            ];
        }

        return [
            'body' => $rendered,
            'truncated' => false,
            'original_length' => $originalLength,
            'max_length' => $maxLength,
        ];
    }

    /**
     * Extract personalization variables from the recipient's Lead, Person, Organization, and opener.
     *
     * @return array<string, string>
     */
    public function extractVariables(LinkedinCampaignRecipient $recipient): array
    {
        $recipient->loadMissing(['lead.organization', 'lead.person', 'person.organization']);

        $lead = $recipient->lead;
        $person = $recipient->person ?? $lead?->person;
        $organization = $lead?->organization ?? $person?->organization;

        $firstName = trim((string) ($person?->first_name ?? ''));
        $lastName = trim((string) ($person?->last_name ?? ''));
        $fullName = trim((string) ($person?->name ?? trim($firstName.' '.$lastName)));
        $company = trim((string) ($organization?->name ?? ''));
        $title = $this->resolveTitle($person, $lead, $organization);
        $personalizedOpener = trim((string) ($recipient->personalized_opener ?? ''));

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'company' => $company,
            'title' => $title,
            'personalized_opener' => $personalizedOpener,
        ];
    }

    public function maxLengthForType(?string $type): int
    {
        return match ($type) {
            LinkedinTemplate::TYPE_CONNECT_NOTE, 'connect' => self::CONNECT_NOTE_MAX_LENGTH,
            default => self::DM_MAX_LENGTH,
        };
    }

    protected function resolveTitle(?Person $person, ?Lead $lead, ?Organization $organization): string
    {
        $personTitle = trim((string) ($person?->title ?? ''));
        if ($personTitle !== '') {
            return $personTitle;
        }

        $leadTitle = trim((string) ($lead?->title ?? ''));
        if ($leadTitle === '') {
            return '';
        }

        $orgName = trim((string) ($organization?->name ?? ''));
        if ($orgName !== '' && str_starts_with(mb_strtolower($leadTitle, 'UTF-8'), mb_strtolower($orgName, 'UTF-8').' - ')) {
            return trim(mb_substr($leadTitle, mb_strlen($orgName, 'UTF-8') + 3, null, 'UTF-8'));
        }

        return $leadTitle;
    }

    protected function normalizeWhitespace(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [$text];
        $cleaned = array_map(static function (string $line): string {
            $line = (string) preg_replace('/[ \t]{2,}/', ' ', $line);

            return trim($line);
        }, $lines);

        return trim(implode("\n", $cleaned));
    }

    protected function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        if ($maxLength <= 3) {
            return mb_substr($text, 0, $maxLength, 'UTF-8');
        }

        $slice = mb_substr($text, 0, $maxLength - 3, 'UTF-8');
        $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');

        if ($lastSpace !== false && $lastSpace > (int) (($maxLength - 3) * 0.6)) {
            $slice = mb_substr($slice, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($slice, " \t\n\r\0\x0B.,;:").'...';
    }
}
