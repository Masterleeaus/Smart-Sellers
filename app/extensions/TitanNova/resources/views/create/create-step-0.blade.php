<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full text-center transition duration-300"
    x-show="currentStep === 0"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm brightness-110 saturate-150"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0 brightness-100 saturate-100"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0 brightness-100 saturate-100"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm brightness-110 saturate-150"
>
    <div class="relative">
        <figure>
            <img
                class="mx-auto max-w-[300px]"
                src="{{ asset('vendor/titan-nova/images/img-1.png') }}"
                width="571"
                height="572"
            >
        </figure>
        <figure class="absolute start-10 top-16">
            <img
                class="mx-auto max-w-[85px]"
                src="{{ asset('vendor/titan-nova/images/img-2.png') }}"
                width="162"
                height="209"
            >
        </figure>
    </div>

    <h2 class="mb-7 text-[21px] font-medium leading-[1.2em]">
        @lang('<span class="opacity-50">Hey 👋. I’m your Titan Nova Agent. I create, schedule and coordinate</span> recurring business tasks with controlled execution and performance analytics.')
    </h2>

    <x-button
        class="mb-3 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground"
        @click.prevent="nextStep()"
    >
        @lang('Let\'s Get Started')
        <x-tabler-arrow-right class="size-4" />
    </x-button>

    <p class="text-[12px]">
        <span class="opacity-50">
            @lang('Tasks remain editable until execution.')
        </span>
        <a
            class="underline"
            href="#"
        >
            @lang('Learn more about Titan Nova')
        </a>
    </p>
</div>
