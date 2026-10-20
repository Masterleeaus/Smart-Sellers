@php
    $languagesConfig = config('openai_languages', []);
    $fallbackLanguages = [
		'en-GB' => 'English (UK)',
        'ar-AE' => 'Arabic',
        'az-AZ' => 'Azerbaijani (Azerbaijan)',
        'cmn-CN' => 'Chinese (Mandarin)',
        'hr-HR' => 'Croatian (Croatia)',
        'cs-CZ' => 'Czech (Czech Republic)',
        'da-DK' => 'Danish (Denmark)',
        'nl-NL' => 'Dutch (Netherlands)',
        'en-US' => 'English (USA)',
        'et-EE' => 'Estonian (Estonia)',
        'fi-FI' => 'Finnish (Finland)',
        'fr-FR' => 'French (France)',
        'de-DE' => 'German (Germany)',
        'el-GR' => 'Greek (Greece)',
        'he-IL' => 'Hebrew (Israel)',
        'hi-IN' => 'Hindi (India)',
        'hu-HU' => 'Hungarian (Hungary)',
        'is-IS' => 'Icelandic (Iceland)',
        'id-ID' => 'Indonesian (Indonesia)',
        'it-IT' => 'Italian (Italy)',
        'ja-JP' => 'Japanese (Japan)',
        'kk-KZ' => 'Kazakh (Kazakhstan)',
        'ko-KR' => 'Korean (South Korea)',
        'lt-LT' => 'Lithuanian (Lithuania)',
        'ms-MY' => 'Malay (Malaysia)',
        'mk-MK' => 'Macedonian (North Macedonia)',
        'nb-NO' => 'Norwegian (Norway)',
        'fa_IR' => 'Persian',
        'pl-PL' => 'Polish (Poland)',
        'pt-BR' => 'Portuguese (Brazil)',
        'pt-PT' => 'Portuguese (Portugal)',
        'ro-RO' => 'Romanian (Romania)',
        'ru-RU' => 'Russian (Russia)',
        'sk-SK' => 'Slovak (Slovakia)',
        'sl-SI' => 'Slovenian (Slovenia)',
        'es-ES' => 'Spanish (Spain)',
        'es-MX' => 'Spanish (Mexico)',
        'sw-KE' => 'Swahili (Kenya)',
        'sv-SE' => 'Swedish (Sweden)',
        'tr-TR' => 'Turkish (Turkey)',
        'th-TH' => 'Thai (Thailand)',
        'vi-VN' => 'Vietnamese (Vietnam)',
        'uk-UA' => 'Ukrainian (Ukraine)',
    ];

    $languages = collect(! empty($languagesConfig) ? $languagesConfig : $fallbackLanguages)
        ->mapWithKeys(fn ($label, $code) => [$code => __($label)])
        ->toArray();
    $lengths = [
        '15' => __('15 minutes'),
        '30' => __('30 minutes'),
        '60' => __('1 hour'),
        '120' => __('2 hours'),
        '240' => __('Half day'),
        '480' => __('Full day'),
    ];
    $prioritys = [
        'low' => __('Low priority'),
        'normal' => __('Normal priority'),
        'high' => __('High priority'),
        'urgent' => __('Urgent'),
    ];
@endphp

<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 3"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <h2 class="mb-9 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Configure the task engine')
        </span>
        @lang('Set task evidence, research, duration and priority.')
    </h2>

    <div class="flex flex-col gap-5 rounded-[20px] border px-5 py-7">
        <x-forms.input
            class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none"
            type="checkbox"
            label="{{ __('Require or create a visual attachment') }}"
            size="sm"
            switcher
            switcherFill
            x-model="formData.has_image"
        />

        <x-forms.input
            class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none"
            type="checkbox"
            label="{{ __('Send task status updates') }}"
            size="sm"
            switcher
            switcherFill
            x-model="formData.has_emoji"
        />

        <x-forms.input
            class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none"
            type="checkbox"
            label="{{ __('Research before creating tasks') }}"
            size="sm"
            switcher
            switcherFill
            x-model="formData.has_web_search"
        />

        <x-forms.input
            class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none"
            type="checkbox"
            label="{{ __('Use existing records and context') }}"
            size="sm"
            switcher
            switcherFill
            x-model="formData.has_keyword_search"
        />

        <div
            class="relative"
            @click.outside="dropdownOpen = false"
            x-data='{
                languages: @json($languages),
                dropdownOpen: false,
                searchStr: ""
            }'
        >
            <div
                class="relative flex cursor-pointer select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all"
                :class="{ 'rounded-b-none border-foreground/5': dropdownOpen }"
                @click.prevent="dropdownOpen = !dropdownOpen"
            >
                <p class="mb-0 text-2xs font-medium text-foreground/50">
                    @lang('Languages')
                </p>
                <p
                    class="mb-0 text-xs font-medium text-heading-foreground"
                    x-text="languages[formData.language]"
                ></p>
                <x-tabler-chevron-down class="absolute end-4 top-1/2 size-4 -translate-y-1/2" />
            </div>

            <div
                class="absolute inset-x-0 top-full z-5 flex origin-top max-h-80 flex-col gap-1 overflow-y-auto rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                x-show="dropdownOpen"
                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                x-transition:enter-end="opacity-100 scale-100 blur-0"
                x-transition:leave-start="opacity-100 scale-100 blur-0"
                x-transition:leave-end="opacity-0 scale-95 blur-sm"
                x-trap="dropdownOpen"
                style="overscroll-behavior: contain;"
            >
                <x-forms.input
                    class:container="mb-2 w-full"
                    type="search"
                    x-model="searchStr"
                    placeholder="{{ __('Search for languages') }}"
                />
                @foreach ($languages as $key => $label)
                    <button
                        class="w-full rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 focus-visible:z-1 focus-visible:scale-[1.02] focus-visible:shadow-lg focus-visible:shadow-black/5"
                        data-key="{{ $key }}"
                        data-label="{{ $label }}"
                        type="button"
                        x-show="searchStr === '' || $el.dataset.key.toLowerCase().includes(searchStr.toLowerCase()) || $el.dataset.label.toLowerCase().includes(searchStr.toLowerCase())"
                        @click.prevent="formData.language = '{{ $key }}'; dropdownOpen = false;"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div
            class="relative"
            @click.outside="dropdownOpen = false"
            x-data='{
                lenghts: @json($lengths),
                dropdownOpen: false,
                searchStr: ""
            }'
        >
            <div
                class="relative flex cursor-pointer select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all"
                :class="{ 'rounded-b-none border-foreground/5': dropdownOpen }"
                @click.prevent="dropdownOpen = !dropdownOpen"
            >
                <p class="mb-0 text-2xs font-medium text-foreground/50">
                    @lang('Default Task Duration')
                </p>
                <p
                    class="mb-0 text-xs font-medium text-heading-foreground"
                    x-text="lenghts[formData.default_task_duration_minutes]"
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
                <x-forms.input
                    class:container="mb-2 w-full"
                    type="search"
                    x-model="searchStr"
                    placeholder="{{ __('Search durations') }}"
                />
                @foreach ($lengths as $key => $label)
                    <button
                        class="w-full rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 focus-visible:z-1 focus-visible:scale-[1.02] focus-visible:shadow-lg focus-visible:shadow-black/5"
                        data-key="{{ $key }}"
                        data-label="{{ $label }}"
                        type="button"
                        x-show="searchStr === '' || $el.dataset.key.toLowerCase().includes(searchStr.toLowerCase()) || $el.dataset.label.toLowerCase().includes(searchStr.toLowerCase())"
                        @click.prevent="formData.default_task_duration_minutes = '{{ $key }}'; dropdownOpen = false;"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div
            class="relative"
            @click.outside="dropdownOpen = false"
            x-data='{
                prioritys: @json($prioritys),
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
                    @lang('Priority')
                </p>
                <p
                    class="mb-0 text-xs font-medium text-heading-foreground"
                    x-text="prioritys[formData.priority]"
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
                    placeholder="{{ __('Search priorities') }}"
                />
                @foreach ($prioritys as $key => $label)
                    <button
                        class="w-full rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 focus-visible:z-1 focus-visible:scale-[1.02] focus-visible:shadow-lg focus-visible:shadow-black/5"
                        data-key="{{ $key }}"
                        data-label="{{ $label }}"
                        type="button"
                        x-show="searchStr === '' || $el.dataset.key.toLowerCase().includes(searchStr.toLowerCase()) || $el.dataset.label.toLowerCase().includes(searchStr.toLowerCase())"
                        @click.prevent="formData.priority = '{{ $key }}'; dropdownOpen = false;"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-2">
        @include('titan-nova::create.step-error', ['step' => 5])
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
