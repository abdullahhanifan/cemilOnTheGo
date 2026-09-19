<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.fullscreen-layout')] #[Title('Log In')] class extends Component
{
    public string $username = '';
    public string $password = '';
    public string $captcha = '';
    public bool $remember = false;

    public function login()
    {
        try {
            $this->validate([
                'username' => 'required',
                'password' => 'required',
                'captcha' => config('captcha.disable') ? [] : ['required', 'captcha'],
            ], [
                'username.required' => 'The Username field is required.',
                'password.required' => 'The Password field is required.',
                'captcha.required' => 'The captcha field is required.',
                'captcha.captcha' => 'The invalid captcha code. Please try again.',
            ]);

            // Validate credentials without establishing a session yet, so an
            // inactive account is never logged in even momentarily.
            if (! Auth::validate(['username' => $this->username, 'password' => $this->password])) {
                $this->addError('username', 'The provided credentials do not match our records.');
                return;
            }

            $user = User::where('username', $this->username)->first();

            if ($user?->status !== 'active') {
                $this->addError('username', 'This account is inactive. Please contact an administrator.');
                return;
            }

            Auth::login($user, $this->remember);
            session()->regenerate();
            return redirect()->intended(route('dashboard'));
        } finally {
            // A captcha code is single use, so the image is refreshed after every attempt.
            $this->reset('captcha');
            $this->dispatch('captcha-refresh');
        }
    }
};
?>

<div class="flex h-dvh w-dvw overflow-hidden bg-neutral-card p-6">
    <!-- Left panel (Hidden on small screens) -->
    <x-common.auth-panel />

    <!-- Right panel (Login form) -->
    <div class="flex h-full w-full flex-col items-center overflow-y-auto bg-neutral-card px-6 sm:px-12 lg:w-1/2 lg:px-20">
        <div class="flex min-h-full w-full max-w-[460px] flex-col justify-center gap-5 py-8 sm:gap-6 sm:py-12">
            <!-- Header -->
            <div class="flex flex-col gap-1.5 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-neutral-heading sm:text-3xl">
                    Log in to continue
                </h1>
                <p class="text-sm text-neutral-label sm:text-base">
                    Access your workspace and available tools
                </p>
            </div>

            @if (session('status') || session('success'))
                <div class="rounded-lg bg-success-50 p-4 text-sm text-success-800">
                    {{ session('status') ?? session('success') }}
                </div>
            @endif

            <!-- Form -->
            <form wire:submit="login" class="flex w-full flex-col gap-4">
                <!-- Username -->
                <div class="flex w-full flex-col gap-1.5">
                    <label for="username" class="text-sm font-semibold text-neutral-heading sm:text-base">
                        Username
                    </label>
                    <div class="relative w-full">
                        <input
                            type="text"
                            id="username"
                            wire:model="username"
                            placeholder="Enter your username"
                            class="w-full rounded-xl border border-neutral-border bg-neutral-card px-4 py-2.5 text-sm text-neutral-heading outline-none transition duration-200 placeholder:text-neutral-label hover:border-accent-primary focus:border-accent-primary focus:ring-2 focus:ring-accent-primary/20 sm:text-base"
                        />
                        @error('username')
                            <span class="mt-1 block text-xs font-medium text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Password -->
                <div class="flex w-full flex-col gap-1.5" x-data="{ show: false }">
                    <label for="password" class="text-sm font-semibold text-neutral-heading sm:text-base">
                        Password
                    </label>
                    <div class="relative w-full">
                        <input
                            :type="show ? 'text' : 'password'"
                            id="password"
                            wire:model="password"
                            placeholder="Enter your password"
                            class="w-full rounded-xl border border-neutral-border bg-neutral-card px-4 py-2.5 pr-12 text-sm text-neutral-heading outline-none transition duration-200 placeholder:text-neutral-label hover:border-accent-primary focus:border-accent-primary focus:ring-2 focus:ring-accent-primary/20 sm:text-base"
                        />
                        <button
                            type="button"
                            @click="show = !show"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 transition duration-150 hover:text-gray-700"
                            aria-label="Toggle password visibility"
                        >
                            <!-- Open Eye Icon -->
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <!-- Closed Eye Icon (Eye Slash) -->
                            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                        @error('password')
                            <span class="mt-1 block text-xs font-medium text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Captcha (hidden when CAPTCHA_DISABLE=true) -->
                <x-form.captcha wire:model="captcha" />

                <!-- Remember Me + Forgot password -->
                <div class="mt-0.5 flex w-full items-center justify-between" x-data="{ remember: @entangle('remember') }">
                    <label class="flex cursor-pointer select-none items-center text-sm font-normal text-neutral-label">
                        <div class="relative">
                            <input type="checkbox" id="remember" class="sr-only" x-model="remember" />
                            <div :class="remember ? 'border-accent-primary bg-accent-primary' : 'bg-transparent border-gray-300'"
                                 class="mr-2.5 flex h-4.5 w-4.5 items-center justify-center rounded-md border-[1.6px] transition duration-150">
                                <svg x-show="remember" x-cloak width="10" height="10" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>
                        Remember Me
                    </label>

                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-accent-primary hover:text-accent-primary-hover">
                        Forgot password?
                    </a>
                </div>

                <!-- Submit Button -->
                <div class="mt-2 w-full">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex w-full items-center justify-center gap-3 rounded-xl bg-accent-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent-primary/20 transition duration-200 hover:bg-accent-primary-hover disabled:cursor-not-allowed disabled:opacity-75"
                    >
                        <!-- Spinner while loading -->
                        <x-ui.spinner.spinner-four :icon-only="true" class="h-4 w-4 text-white" wire:loading wire:target="login" />
                        <span>Log In</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
