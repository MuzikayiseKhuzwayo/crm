<?php

namespace VentureDrake\LaravelCrm\Livewire\LinkedinCampaigns\Traits;

trait GeneratesExtensionApiToken
{
    public string $apiTokenName = 'linkedin-chrome-extension';

    public ?string $generatedPlainTextToken = null;

    public bool $showTokenModal = false;

    public function generateExtensionToken(): void
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'createToken')) {
            $this->error('Sanctum personal access tokens are not enabled on the current user model. Use the CLI command below.');

            return;
        }

        $name = trim($this->apiTokenName) !== '' ? trim($this->apiTokenName) : 'linkedin-chrome-extension';
        $token = $user->createToken($name);
        $this->generatedPlainTextToken = $token->plainTextToken;

        $this->success('Extension API token generated. Copy it now — it will not be shown again.');
    }

    public function getApiEndpointUrlProperty(): string
    {
        return url('/api/crm/v2/linkedin-outreach');
    }

    public function getCliTokenCommandProperty(): string
    {
        $email = auth()->user()?->email ?? 'you@example.com';
        $name = trim($this->apiTokenName) !== '' ? trim($this->apiTokenName) : 'linkedin-chrome-extension';

        return sprintf('php artisan laravel-crm:api-token %s --name="%s"', $email, $name);
    }
}
