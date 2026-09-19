@extends('layouts.fullscreen-layout')

@section('content')
    <div class="flex h-dvh w-dvw overflow-hidden bg-neutral-card p-6">
        <x-common.auth-panel />

        <div class="flex h-full w-full flex-col items-center overflow-y-auto bg-neutral-card px-6 sm:px-12 lg:w-1/2 lg:px-20">
            <div class="flex min-h-full w-full max-w-[460px] flex-col justify-center py-8 sm:py-12">
                <div class="mb-5 sm:mb-8">
                    <h1 class="mb-2 text-title-sm font-semibold text-neutral-heading sm:text-title-md">
                        Sign Up
                    </h1>
                    <p class="text-sm text-neutral-label">
                        Create your account to get started.
                    </p>
                </div>

                <form action="{{ route('signup.store') }}" method="POST">
                    @csrf
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <!-- First Name -->
                            <div class="sm:col-span-1">
                                <x-form.input label="First Name" type="text" name="fname" id="fname"
                                    placeholder="Enter your first name" value="{{ old('fname') }}" required />
                            </div>
                            <!-- Last Name -->
                            <div class="sm:col-span-1">
                                <x-form.input label="Last Name" type="text" name="lname" id="lname"
                                    placeholder="Enter your last name" value="{{ old('lname') }}" required />
                            </div>
                        </div>
                        <!-- Username -->
                        <div>
                            <x-form.input label="Username" type="text" name="username" id="username"
                                placeholder="Choose a username" value="{{ old('username') }}" required />
                        </div>
                        <!-- Email -->
                        <div>
                            <x-form.input label="Email" type="email" name="email" id="email"
                                placeholder="Enter your email" value="{{ old('email') }}" required />
                        </div>
                        <!-- Password -->
                        <div>
                            <x-form.input label="Password" type="password" name="password"
                                placeholder="Enter your password" required />
                        </div>
                        <!-- Password Confirmation -->
                        <div>
                            <x-form.input label="Confirm Password" type="password" name="password_confirmation"
                                placeholder="Confirm your password" required />
                        </div>
                        <!-- CAPTCHA (hidden when CAPTCHA_DISABLE=true) -->
                        <x-form.captcha />
                        <!-- Button -->
                        <div>
                            <button type="submit"
                                class="flex w-full items-center justify-center rounded-lg bg-accent-primary px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-accent-primary-hover">
                                Sign Up
                            </button>
                        </div>
                    </div>
                </form>
                <div class="mt-5">
                    <p class="text-center text-sm font-normal text-neutral-label sm:text-start">
                        Already have an account?
                        <a href="{{ route('signin') }}" class="text-accent-primary hover:text-accent-primary-hover">
                            Sign In
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
