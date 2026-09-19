{{-- Brand panel shared by the auth pages: accent gradient + app name, no images. --}}
<div
    class="relative hidden flex-col items-center justify-center overflow-hidden rounded-[30px] bg-gradient-to-b from-accent-primary to-accent-secondary p-12 lg:flex lg:w-1/2">
    <h2 class="relative z-10 text-center text-4xl font-bold tracking-tight text-white">
        {{ config('app.name') }}
    </h2>
</div>
