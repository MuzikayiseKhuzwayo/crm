<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the Templates settings page — the admin-facing per-doc-type
 * template picker. Mounts the `crm-template-settings` Livewire component
 * inside the standard settings blade wrapper. Persistence lives in the
 * Livewire component; this controller is a thin view shell so route
 * middleware can gate access to the settings permission uniformly with
 * the sibling settings routes.
 */
class TemplateSettingsController extends Controller
{
    /**
     * Show the templates settings page.
     *
     * @return Response
     */
    public function edit()
    {
        return view('laravel-crm::settings.templates.edit');
    }
}
