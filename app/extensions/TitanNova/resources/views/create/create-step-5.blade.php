<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 5"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <h2 class="mb-8 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Choose a name for your agent')
        </span>
        @lang('What should we call your agent?')
    </h2>

    <div class="relative flex select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all">
        <label
            class="mb-0 text-2xs font-medium text-foreground/50"
            for="titan-nova-name"
        >
            @lang('Name of the Agent')
        </label>

        <input
            class="mb-0 border-none bg-transparent bg-none text-xs font-medium text-heading-foreground"
            id="titan-nova-name"
            type="text"
            x-model="formData.name"
            @keydown.enter.prevent="submitForm"
            required
            placeholder="{{ __('Jetsy') }}"
        >
    </div>

    <div class="mt-2">
        @include('titan-nova::create.step-error', ['step' => 7])
    </div>

    <x-button
        class="mt-5 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
        type="submit"
    >
        @lang('Create Agent and Tasks')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
