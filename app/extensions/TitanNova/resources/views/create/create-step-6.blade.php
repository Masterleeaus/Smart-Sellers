<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 6"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <div class="mb-9 flex items-center justify-center text-center">
        <x-tabler-loader-2
            class="size-32 animate-spin"
            stroke-width="1.5"
        />
    </div>

    <h2 class="mb-0 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('I’m creating the task schedule.')
        </span>
        @lang('Sit back and relax...')
    </h2>
</div>
