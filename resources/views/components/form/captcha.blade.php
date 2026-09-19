@props(['name' => 'captcha', 'label' => 'Enter Code'])

{{--
  Renders nothing when CAPTCHA_DISABLE=true. Extra attributes (e.g. wire:model) go to the input.

  The server-rendered HTML must NOT contain the captcha image URL. Livewire parses every re-render
  into a detached element and the browser starts loading any <img src> it finds there, so a URL in
  the HTML fires a second image request next to the JS refresh. Each request issues a new code and
  overwrites the one in the session, so the code shown no longer matches the code stored and a
  correct answer is rejected. The src is therefore only set from JS: once on init, and once per
  `captcha-refresh` event (dispatched by ⚡signin after every attempt, because a code is single use).
--}}
@unless (config('captcha.disable'))
    <div class="space-y-3"
        x-data="{
            busy: false,
            refresh() {
                if (this.busy) { return; }
                this.busy = true;
                this.$refs.image.src = '{{ url('captcha/flat') }}?' + Math.random();
            },
        }"
        x-init="refresh()" x-on:captcha-refresh.window="refresh()">
        <div class="flex items-center gap-4" wire:ignore>
            <div class="overflow-hidden rounded-lg border border-neutral-border">
                <img x-ref="image" alt="CAPTCHA" width="180" height="50" class="block cursor-pointer"
                    src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
                    x-on:load="busy = false" x-on:error="busy = false" x-on:click="refresh()">
            </div>
            <button type="button" x-on:click="refresh()"
                class="inline-flex items-center text-sm text-accent-primary hover:text-accent-primary-hover">
                <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2v6h-6"></path>
                    <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                    <path d="M3 22v-6h6"></path>
                    <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                </svg>
                Refresh
            </button>
        </div>
        <div>
            <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-neutral-label">{{ $label }}</label>
            <input type="text" id="{{ $name }}" name="{{ $name }}" autocomplete="off"
                placeholder="Enter the code in the image above"
                {{ $attributes->merge(['class' => 'h-11 w-full rounded-lg border border-neutral-border bg-transparent px-4 py-2.5 text-sm text-neutral-heading placeholder:text-neutral-label focus:border-accent-primary focus:outline-hidden focus:ring-3 focus:ring-accent-primary/20']) }} />
            @error($name)
                <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
            @enderror
        </div>
    </div>
@endunless
