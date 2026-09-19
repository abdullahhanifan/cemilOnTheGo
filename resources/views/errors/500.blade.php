@extends('layouts.fullscreen-layout')

@php
    $title = 'Error 500';
@endphp

@section('content')
    @php
        $currentYear = date('Y');
    @endphp
    <div class="relative flex flex-col items-center justify-center min-h-screen p-6 overflow-hidden z-1 font-sans">

        <div class="mx-auto w-full max-w-[242px] text-center sm:max-w-[562px]">
            <h1 class="mb-8 font-bold text-gray-800 text-title-md dark:text-white/90 xl:text-title-2xl">
                ERROR 500
            </h1>

            <img src="/images/error/500.svg" alt="500" class="dark:hidden mx-auto" />
            <img src="/images/error/500-dark.svg" alt="500" class="hidden dark:block mx-auto" />

            <p class="mt-10 mb-6 text-base text-gray-700 dark:text-gray-400 sm:text-lg">
                Internal Server Error. Something went wrong on our end!
            </p>

            <a href="/dashboard"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                Back to Home Page
            </a>
        </div>

        {{-- Footer --}}
        <p class="absolute text-sm text-center text-gray-500 -translate-x-1/2 bottom-6 left-1/2 dark:text-gray-400">
            {{ config('app.name') }} &copy; {{ $currentYear }} - All rights reserved.
        </p>
    </div>
@endsection
