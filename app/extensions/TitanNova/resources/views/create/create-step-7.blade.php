<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 7"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <figure class="mb-5">
        <img
            class="mx-auto max-w-[260px]"
            src="{{ asset('vendor/titan-nova/images/img-3.png') }}"
            width="527"
            height="509"
        >
    </figure>

    <h2 class="mb-12 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('All done!')
        </span>
        @lang('You can review, approve and monitor your tasks.')
    </h2>

    <x-button
        class="w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
		href="{{ route('dashboard.user.titan-nova.agent.index') }}"
    >
        @lang('View Scheduled Tasks')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
