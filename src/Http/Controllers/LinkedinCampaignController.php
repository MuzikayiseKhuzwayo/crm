<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use VentureDrake\LaravelCrm\Models\LinkedinCampaign;

class LinkedinCampaignController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', LinkedinCampaign::class);

        return view('laravel-crm::linkedin-campaigns.index');
    }

    public function create()
    {
        $this->authorize('create', LinkedinCampaign::class);

        return view('laravel-crm::linkedin-campaigns.create');
    }

    public function show(LinkedinCampaign $linkedinCampaign)
    {
        $this->authorize('view', $linkedinCampaign);

        return view('laravel-crm::linkedin-campaigns.show', ['campaign' => $linkedinCampaign]);
    }

    public function edit(LinkedinCampaign $linkedinCampaign)
    {
        $this->authorize('update', $linkedinCampaign);

        return view('laravel-crm::linkedin-campaigns.edit', ['campaign' => $linkedinCampaign]);
    }
}
