@php
    $frequencies = [
        // 'daily' => __('Daily'),
        'weekly' => __('Weekly'),
        'monthly' => __('Monthly'),
    ];
@endphp

<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 4"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <h2 class="mb-8 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Define the agent schedule')
        </span>
        @lang('When should the agent create and run tasks?')
    </h2>

    <div class="flex flex-col gap-5 rounded-[20px] border px-5 py-7">
        <div class="flex justify-between gap-3">
            @foreach ($days_of_week as $day_index => $day)
                <label class="group relative flex flex-col items-center gap-3 text-center text-xs/none text-label">
                    {{ $day['shorthand'] }}
                    <input
                        class="peer absolute inset-0 z-1 cursor-pointer opacity-0"
                        type="checkbox"
                        x-model="formData.schedule_days"
                        value="{{ $day['number'] }}"
                    >
                    <span class="inline-grid size-8 place-items-center rounded-full bg-foreground/5 transition peer-checked:bg-foreground peer-checked:text-background">
                        <x-tabler-check class="size-5 scale-50 opacity-0 transition group-has-[input:checked]:scale-100 group-has-[input:checked]:opacity-100" />
                    </span>
                </label>
            @endforeach
        </div>

        <div
            class="relative"
            @click.outside="dropdownOpen = false"
            x-data="{
                dropdownOpen: false
            }"
        >
            <div
                class="relative flex cursor-pointer select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all"
                :class="{ 'rounded-b-none border-foreground/5': dropdownOpen }"
                @click.prevent="dropdownOpen = !dropdownOpen"
            >
                <p class="mb-0 text-2xs font-medium text-foreground/50">
                    @lang('Time of the day')
                </p>
                <p
                    class="mb-0 text-xs font-medium text-heading-foreground"
                    x-text="formData.schedule_times.map(t => `${t.label} (${t.start}-${t.end})`).join(' - ')"
                ></p>
                <x-tabler-chevron-down class="absolute end-4 top-1/2 size-4 -translate-y-1/2" />
            </div>

            <div
                class="absolute inset-x-0 top-full z-5 flex origin-top flex-wrap gap-px rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                x-show="dropdownOpen"
                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                x-transition:enter-end="opacity-100 scale-100 blur-0"
                x-transition:leave-start="opacity-100 scale-100 blur-0"
                x-transition:leave-end="opacity-0 scale-95 blur-sm"
                x-trap="dropdownOpen"
            >
                @foreach ($time_slots as $time_slot)
                    <button
                        class="group flex w-full items-center gap-2 rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 focus-visible:z-1 focus-visible:scale-[1.02] focus-visible:shadow-lg focus-visible:shadow-black/5"
                        type="button"
                        @click.prevent="toggleTimeSlot('{{ $time_slot['key'] }}')"
                        :class="{ 'active': hasTimeSlot('{{ $time_slot['key'] }}') }"
                    >
                        <span
                            class="inline-grid size-6 place-items-center rounded-full border group-[&.active]:border-primary group-[&.active]:bg-primary group-[&.active]:text-primary-foreground"
                        >
                            <x-tabler-check class="size-4 scale-50 opacity-0 transition group-[&.active]:scale-100 group-[&.active]:opacity-100" />
                        </span>
                        {{ $time_slot['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="relative">
            <div class="relative flex select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all">
                <label
                    class="mb-0 text-2xs font-medium text-foreground/50"
                    for="schedule-task-count"
                >
                    @lang('Number of Tasks Per Day')
                </label>
                <input
                    class="lqd-input-stepper border-none bg-transparent bg-none text-xs font-medium text-heading-foreground"
                    id="schedule-task-count"
                    type="number"
                    x-model.number="formData.daily_task_count"
                    @change="handleDailyTaskCountChange"
                    min="1"
                    max="10"
                />
            </div>
        </div>

        <div
            class="relative"
            @click.outside="dropdownOpen = false"
            x-data='{
                frequencies: @json($frequencies),
                dropdownOpen: false,
                searchStr: ""
            }'
        >
            <div
                class="relative flex cursor-pointer select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all"
                :class="{ 'rounded-t-none border-foreground/5': dropdownOpen }"
                @click.prevent="dropdownOpen = !dropdownOpen"
            >
                <p class="mb-0 text-2xs font-medium text-foreground/50">
                    @lang('Plan Tasks Every')
                </p>
                <p
                    class="mb-0 text-xs font-medium text-heading-foreground"
                    x-text="frequencies[formData.frequency]"
                ></p>
                <x-tabler-chevron-down class="absolute end-4 top-1/2 size-4 -translate-y-1/2" />
            </div>

            <div
                class="absolute inset-x-0 bottom-full z-5 flex origin-bottom flex-wrap gap-px rounded-t-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                x-show="dropdownOpen"
                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                x-transition:enter-end="opacity-100 scale-100 blur-0"
                x-transition:leave-start="opacity-100 scale-100 blur-0"
                x-transition:leave-end="opacity-0 scale-95 blur-sm"
                x-trap="dropdownOpen"
            >
                <x-forms.input
                    class:container="mb-2 w-full"
                    type="search"
                    x-model="searchStr"
                    placeholder="{{ __('Search for frequencies') }}"
                />
                @foreach ($frequencies as $key => $label)
                    <button
                        class="w-full rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 focus-visible:z-1 focus-visible:scale-[1.02] focus-visible:shadow-lg focus-visible:shadow-black/5"
                        data-key="{{ $key }}"
                        data-label="{{ $label }}"
                        type="button"
                        x-show="searchStr === '' || $el.dataset.key.toLowerCase().includes(searchStr.toLowerCase()) || $el.dataset.label.toLowerCase().includes(searchStr.toLowerCase())"
                        @click.prevent="formData.frequency = '{{ $key }}'; dropdownOpen = false;"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-2">
        @include('titan-nova::create.step-error', ['step' => 6])
    </div>

    <x-button
        class="mt-5 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
        type="button"
        @click.prevent="nextStep()"
    >
        @lang('Continue')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
