@extends('laravel-crm::layouts.portal', ['title' => ucfirst(__('laravel-crm::lang.user_invitation_action'))])

@section('content')
    <div class="container mx-auto px-4 py-16 max-w-xl">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h1 class="text-2xl font-semibold mb-2">
                    {{ ucfirst(__('laravel-crm::lang.user_invitation_action')) }}
                </h1>

                <p class="text-base-content/70 mb-6">
                    {{ __('laravel-crm::lang.user_invitation_new_user_intro') }}
                </p>

                @if ($errors->any())
                    <div class="alert alert-error mb-4">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('laravel-crm.users.invitations.accept.store', $invitation->code) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="label">
                            <span class="label-text">{{ ucfirst(__('laravel-crm::lang.email')) }}</span>
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ $invitation->email }}"
                            class="input input-bordered w-full"
                            readonly
                            disabled
                        />
                    </div>

                    <div>
                        <label for="name" class="label">
                            <span class="label-text">{{ ucfirst(__('laravel-crm::lang.name')) }}</span>
                        </label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            class="input input-bordered w-full @error('name') input-error @enderror"
                            required
                            autofocus
                        />
                    </div>

                    <div>
                        <label for="password" class="label">
                            <span class="label-text">{{ ucfirst(__('laravel-crm::lang.password')) }}</span>
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="input input-bordered w-full @error('password') input-error @enderror"
                            required
                        />
                    </div>

                    <div>
                        <label for="password_confirmation" class="label">
                            <span class="label-text">{{ ucfirst(__('laravel-crm::lang.confirm_password')) }}</span>
                        </label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="input input-bordered w-full"
                            required
                        />
                    </div>

                    <div class="card-actions justify-end pt-2">
                        <button type="submit" class="btn btn-primary">
                            {{ ucfirst(__('laravel-crm::lang.user_invitation_action')) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
