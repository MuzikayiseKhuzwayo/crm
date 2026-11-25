<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use VentureDrake\LaravelCrm\Models\LinkedinTemplate;

class LinkedinTemplateController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', LinkedinTemplate::class);

        return view('laravel-crm::linkedin-templates.index');
    }

    public function create()
    {
        $this->authorize('create', LinkedinTemplate::class);

        return view('laravel-crm::linkedin-templates.create');
    }

    public function edit(LinkedinTemplate $linkedinTemplate)
    {
        $this->authorize('update', $linkedinTemplate);

        return view('laravel-crm::linkedin-templates.edit', ['template' => $linkedinTemplate]);
    }
}
