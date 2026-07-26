@extends('laravel-crm::layouts.portal', ['title' => ucfirst(__('laravel-crm::lang.user_invitation_invalid_title'))])

@section('content')
    <div class="container mx-auto px-4 py-16 max-w-xl">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body text-center">
                <h1 class="text-2xl font-semibold mb-4">
                    {{ ucfirst(__('laravel-crm::lang.user_invitation_invalid_title')) }}
                </h1>

                <p class="text-base-content/70 mb-6">
                    {{ $message ?? __('laravel-crm::lang.user_invitation_invalid_message') }}
                </p>

                <div class="card-actions justify-center">
                    <a href="{{ url('/') }}" class="btn btn-primary">
                        {{ ucfirst(__('laravel-crm::lang.user_invitation_back_to_login')) }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
