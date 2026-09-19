@extends('layouts.fullscreen-layout')

@section('content')
    <div class="flex h-dvh w-dvw overflow-hidden bg-neutral-card p-6">
        <x-common.auth-panel />

        <div class="flex h-full w-full flex-col items-center overflow-y-auto bg-neutral-card px-6 sm:px-12 lg:w-1/2 lg:px-20">
            <div class="flex min-h-full w-full max-w-[460px] flex-col justify-center py-8 sm:py-12">
                <div class="mb-5 sm:mb-8">
                    <h1 class="mb-2 text-title-sm font-semibold text-neutral-heading sm:text-title-md">
                        Forgot Your Password?
                    </h1>
                    <p class="text-sm text-neutral-label">
                        Enter the email address linked to your account, and we’ll send
                        you a link to reset your password.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-success-50 p-4 text-sm text-success-800">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="space-y-5">
                        <!-- Email -->
                        <div>
                            <x-form.input label="Email" type="email" name="email" id="email"
                                placeholder="Enter your email" value="{{ old('email') }}" required autofocus />
                        </div>

                        <!-- CAPTCHA (hidden when CAPTCHA_DISABLE=true) -->
                        <x-form.captcha />

                        <!-- Button -->
                        <div>
                            <button
                                class="flex w-full items-center justify-center rounded-lg bg-accent-primary px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-accent-primary-hover">
                                Send Reset Link
                            </button>
                        </div>
                    </div>
                </form>
                <div class="mt-5">
                    <p class="text-center text-sm font-normal text-neutral-label sm:text-start">
                        Wait, I remember my password...
                        <a href="{{ route('signin') }}" class="text-accent-primary hover:text-accent-primary-hover">Click here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
