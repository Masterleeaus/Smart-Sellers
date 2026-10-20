@php
    use Illuminate\Support\Str;

    $theme = \Theme::get();

    $task_modes = [
        'task' => [
            'label' => __('Task'),
        ],
    ];

    $sort_options = [
        'created_at' => [
            'label' => __('Date'),
        ],
        'status' => [
            'label' => __('Status'),
        ],
        'title' => [
            'label' => __('Title'),
        ],
    ];
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Tasks'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Create and edit tasks'))
@section('titlebar_actions')
    @include('titan-nova::components.titlebar-actions')
@endsection

@push('css')
    <link
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
        rel="stylesheet"
    />
@endpush

@section('content')
    <div @class([
        'pb-10',
        'pt-10' => $theme !== 'titan-nova-dashboard',
    ])>
        @if ($theme === 'titan-nova-dashboard')
            @include('titan-nova::tasks.titlebar')
        @endif

        <div x-data="titanNovaTasks">
            <div class="mb-9 flex flex-wrap justify-between gap-5 max-md:gap-y-3 xl:flex-nowrap xl:gap-10">
                <div class="flex grow items-center gap-3 transition max-md:flex-wrap lg:gap-8 lg:border-b">
                    <div
                        class="relative flex grow max-lg:border-b max-md:w-full lg:contents"
                        x-data="{ selectedPlatformLabel: '{{ __('All Platforms') }}', dropdownOpen: false }"
                        @click.outside="dropdownOpen = false"
                    >
                        <span
                            class="hidden grow items-center gap-1 max-lg:flex max-lg:w-full max-lg:justify-between max-lg:border-b max-lg:px-2 max-lg:py-4 max-md:py-2"
                            @click.prevent="dropdownOpen = !dropdownOpen"
                        >
                            <span x-text="selectedPlatformLabel">
                                @lang('All Platforms')
                            </span>

                            <x-tabler-chevron-down class="size-4" />
                        </span>
                    </div>

                    <div class="flex gap-4 md:ms-auto">
                        <div class="flex self-center">
                            @foreach ($agents as $agent)
                                <button
                                    class="relative -ms-2.5 shrink-0 transition before:pointer-events-none before:absolute before:-end-2 before:top-full before:mt-1 before:w-52 before:translate-y-0 before:rounded-md before:bg-background before:px-2 before:py-2 before:text-[12px] before:font-medium before:opacity-0 before:shadow-md before:shadow-black/5 before:transition before:content-[attr(title)] hover:z-2 hover:-translate-y-1 hover:scale-110 hover:before:translate-y-0 hover:before:opacity-100"
                                    title="{{ $agent->name }}"
                                    ::class="{'active': filters.agent_id.includes({{ $agent['id'] }})}"
                                    @click.prevent="filterTasks({agent_id: {{ $agent['id'] }}});"
                                >
                                    <figure
                                        class="inline-grid size-8 place-items-center overflow-hidden rounded-full border-2 border-background bg-[#969696] text-background dark:bg-[#414954] dark:text-foreground"
                                        :class="{ 'border-green-400 shadow-md shadow-green-400/50': filters.agent_id.includes({{ $agent['id'] }}) }"
                                    >
                                        @if ($agent->image)
                                            <img
                                                class="size-full object-cover object-center"
                                                src="{{ $agent->image }}"
                                                alt="{{ $agent->name }}"
                                            >
                                        @else
                                            <svg
                                                width="14"
                                                height="19"
                                                viewBox="0 0 17 23"
                                                fill="currentColor"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    d="M8.17757 0C11.0099 0 13.305 2.29589 13.305 5.12695C13.305 7.95869 11.0099 10.2539 8.17757 10.2539C5.34664 10.2539 3.05152 7.95869 3.05152 5.12695C3.05152 2.29589 5.34664 0 8.17757 0ZM8.17416 22.3128C5.34936 22.3128 2.76217 21.284 0.766625 19.5811C0.280506 19.1665 0 18.5585 0 17.9205C0 15.0493 2.32371 12.7513 5.19549 12.7513H11.161C14.0335 12.7513 16.3483 15.0493 16.3483 17.9205C16.3483 18.5591 16.0692 19.1658 15.5824 19.5805C13.5875 21.284 10.9996 22.3128 8.17416 22.3128Z"
                                                />
                                            </svg>
                                        @endif
                                    </figure>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex md:justify-end">
                    <x-dropdown.dropdown
                        class:dropdown-dropdown="max-lg:!start-0 max-lg:!end-auto"
                        anchor="end"
                        offsetY="15px"
                        :teleport=false
                    >
                        <x-slot:trigger
                            class="whitespace-nowrap py-2 md:py-4"
                            variant="link"
                        >
                            @lang('Sort By'):
                            <span
                                class="capitalize"
                                x-text="sortLabel"
                            >
                                {{ $sort_options['created_at']['label'] }}
                            </span>
                            <x-tabler-caret-down-filled
                                class="size-4 opacity-60"
                                ::class="{ 'rotate-180': sort.sortDirection === 'asc' }"
                            />
                        </x-slot:trigger>

                        <x-slot:dropdown
                            class="min-w-[150px] overflow-hidden rounded-lg bg-background shadow-lg"
                        >
                            @foreach ($sort_options as $key => $sort_option)
                                <x-button
                                    class="w-full justify-start rounded-none text-start hover:transform-none [&.active]:underline"
                                    @click.prevent="sort.sortBy = '{{ $key }}'; sort.sortDirection = sort.sortBy !== '{{ $key }}' || sort.sortDirection === 'asc' ? 'desc' : 'asc'; filterTasks({channel_id: filters.channel_id})"
                                    variant="ghost"
                                    ::class="{
                                        'hidden': '{{ $key }}'
                                        === 'channel_id' && filters.channel_id !== '',
                                        'active': sort.sortBy === '{{ $key }}'
                                    }"
                                >
                                    {{ $sort_option['label'] }}
                                    <x-tabler-caret-down-filled
                                        class="hidden size-4 opacity-60"
                                        ::class="{ 'rotate-180': sort.sortDirection === 'asc', 'hidden': sort.sortBy !== '{{ $key }}' }"
                                    />
                                </x-button>
                            @endforeach
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </div>
            </div>

            @include('titan-nova::components.tasks.list.tasks-container', ['tasks' => $tasks, 'show_heading_and_arrows' => false])
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>

    @include('titan-nova::components.tasks.tasks-script')
@endpush
