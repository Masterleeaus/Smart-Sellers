<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 1"
    x-cloak
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-data="{
        showBrandDescription: false
    }"
    x-on:titan-nova-force-description.window="
        showBrandDescription = true;
        $nextTick(() => { $refs.brandDescription?.focus(); });
    "
>
    <h2 class="mb-0 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Describe the work this agent should manage')
        </span>
        @lang('Which task types should this agent create?')
    </h2>

    <div
        x-show="!showBrandDescription"
        x-collapse
    >
        <div class="relative mt-4">
            <x-forms.input
                class="min-h-[70px] rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all focus-visible:border-foreground/5"
                type="text"
                autocomplete="false"
                placeholder="{{ __('e.g. source leads, train staff, follow up jobs, collect payments') }}"
                x-model="formData.topic"
                @keydown.enter="nextStep()"
            />
            <span
                class="absolute end-5 top-1/2 inline-flex -translate-y-1/2"
                x-cloak
                x-show="topic_generating"
            >
                <x-tabler-loader-2 class="size-6 animate-spin" />
            </span>
            <button
                class="absolute end-5 top-1/2 inline-grid size-9 -translate-y-1/2 place-items-center hover:scale-110"
                type="button"
                x-cloak
                x-show="formData.topic.trim() && !topic_generating"
                @click.prevent="if ( formData.topic && !topic_generating ) { generateTaskTypes() }"
            >
                <x-tabler-zoom-scan class="size-6" />
            </button>
        </div>
       <div
            class="flex flex-wrap gap-2 mt-6 rounded-[20px] border px-5 py-7 text-center max-h-[300px] overflow-y-auto overflow-x-hidden"
            x-show="formData.task_type_options.length > 0"
            x-cloak
        >
            <template x-for="topic in formData.task_type_options" :key="topic">
                <label
                    class="group relative flex min-h-[42px] items-center gap-1.5 rounded-full bg-foreground/5 px-4 py-2.5 text-xs text-heading-foreground has-[input:checked]:bg-primary/5 has-[input:checked]:text-primary"
                >
                    <input
                        type="checkbox"
                        class="peer absolute inset-0 z-1 cursor-pointer opacity-0"
                        :value="topic"
                        x-model="formData.selected_task_types"
                    >

                    <span x-text="topic"></span>

                    <span
                        class="inline-grid size-[22px] place-items-center rounded-full border border-foreground/15 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground"
                    >
                        <x-tabler-check class="hidden size-4 group-has-[input:checked]:flex" />
                    </span>
                </label>
            </template>
        </div>
    </div>

    <div class="mt-2">
        @include('titan-nova::create.step-error', ['step' => 2])
    </div>

    <x-button
        class="mt-7 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
        type="button"
        @click.prevent="nextStep()"
        ::disabled="topic_generating"
    >
        @lang('Continue')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
