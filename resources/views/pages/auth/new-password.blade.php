@extends('layouts.fullscreen-layout')

@section('content')
    <div class="flex h-dvh w-dvw overflow-hidden bg-neutral-card p-6">
        <x-common.auth-panel />

        <div class="flex h-full w-full flex-col items-center overflow-y-auto bg-neutral-card px-6 sm:px-12 lg:w-1/2 lg:px-20">
            <div class="flex min-h-full w-full max-w-[460px] flex-col justify-center py-8 sm:py-12">
                <div class="mb-5 sm:mb-8">
                    <h1 class="mb-2 text-title-sm font-semibold text-neutral-heading sm:text-title-md">
                        Reset Password
                    </h1>
                    <p class="text-sm text-neutral-label">
                        Enter your new password below.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-success-50 p-4 text-sm text-success-800">
                        {{ session('status') }}
                    </div>
                @endif

                <!-- Error Handling -->
                @if ($errors->has('email') || $errors->has('token'))
                    <div class="mb-5">
                        @if ($errors->has('email'))
                            <x-ui.alert variant="error" title="Error" message="{{ $errors->first('email') }}" />
                        @endif
                        @if ($errors->has('token'))
                            <x-ui.alert variant="error" title="Error" message="{{ $errors->first('token') }}" />
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">

                    <div class="space-y-5">
                        <!-- Password -->
                        <div>
                            <x-form.input label="Password" type="password" name="password"
                                placeholder="Enter your new password" required autofocus />
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <x-form.input label="Confirm Password" type="password" name="password_confirmation"
                                placeholder="Confirm your new password" required />
                        </div>

                        <!-- Button -->
                        <div>
                            <button
                                class="flex w-full items-center justify-center rounded-lg bg-accent-primary px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-accent-primary-hover">
                                Reset Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
