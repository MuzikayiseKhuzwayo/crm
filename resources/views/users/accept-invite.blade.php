@extends('laravel-crm::layouts.portal', ['title' => ucfirst(__('laravel-crm::lang.user_invitation_action'))])

@section('content')
    <div class="container mx-auto px-4 py-16 max-w-xl">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                {{-- Placeholder: real accept-invitation form lands in US-005/US-006 --}}
                <h1 class="text-2xl font-semibold mb-4">
                    {{ ucfirst(__('laravel-crm::lang.user_invitation_action')) }}
                </h1>

                <p class="text-base-content/70">
                    {{ __('laravel-crm::lang.user_invitation_greeting') }}
                </p>
            </div>
        </div>
    </div>
@endsection
