@php
    use Illuminate\Support\Str;
@endphp

@forelse($tasks as $task)
    <div
        class="titan-nova-task-item group flex w-full flex-shrink-0 flex-grow-0 basis-auto flex-col px-5 py-2 sm:w-1/2 lg:w-1/4"
        data-task-id="{{ $task->id }}"
        :class="{ 'animate-pulse pointer-events-none': currentTasks.has('approveTask') || currentTasks.has('rejectTask') }"
        x-data='titanNovaTaskItem({ id: {{ $task->id }}, title: "{{ $task->title }}", instructions: @json($task->instructions), excerpt: "{{ Str::limit($task->instructions, 200) }}", thumbnail: "{{ $task->thumbnail ? url($task->thumbnail) : '' }}", status: "{{ $task->status }}", categories: "{{ $task->category }}", tags: "{{ $task->tag }}", scheduled_at: "{{ $task->scheduled_at }}" })'
    >
        <div class="flex grow flex-col rounded-[10px] border p-3.5 transition">
            <div class="mb-2.5 flex items-center gap-1">
                <div class="flex items-center gap-1 overflow-hidden text-2xs text-heading-foreground">
                </div>
                <span class="text-2xs">
                    <span
                        class="capitalize"
                        x-text="title"
                    >
                        {{ $task->title }}
                    </span>
                </span>
            </div>

            <div class="flex gap-4 items-center">
                <p
                    @class([
                        'mb-3.5 inline-flex items-center gap-1.5 rounded-md px-2.5 py-[3px] text-[12px] font-medium self-start',
                        'bg-blue-500/15 text-blue-700 dark:text-blue-300' =>
                            $task->status === 'draft',
                        'bg-yellow-500/15 text-yellow-700 dark:text-yellow-300' =>
                            $task->status === 'scheduled',
                        'bg-green-500/15 text-green-700 dark:text-green-300' =>
                            $task->status === 'completed',
                        'bg-red-500/15 text-red-700 dark:text-red-300' =>
                            $task->status === 'failed',
                    ])
                    :class="statusState.css"
                >
                    <span x-text="statusState.label">
                        {{ Str::headline($task->status) }}
                    </span>
                    <span x-html="statusState.icon">
                        {{-- blade-formatter-disable --}}
                        @if ($task->status === 'completed')
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"> <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -14.995 8.984l-.005 -.324l.005 -.324a10 10 0 0 1 14.995 -8.336zm-1.293 5.953a1 1 0 0 0 -1.32 -.083l-.094 .083l-3.293 3.292l-1.293 -1.292l-.094 -.083a1 1 0 0 0 -1.403 1.403l.083 .094l2 2l.094 .083a1 1 0 0 0 1.226 0l.094 -.083l4 -4l.083 -.094a1 1 0 0 0 -.083 -1.32z"/></svg>
                        @elseif($task->status === 'failed')
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>
                        @elseif($task->status === 'draft')
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/><path d="M9 15l4 0"/></svg>
                        @else
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>
                        @endif
                        {{-- blade-formatter-enable --}}
                    </span>
                </p>

                <p class="mb-4 text-3xs opacity-55">
                    <span
                        x-text="scheduled_at ? new Date(scheduled_at).toLocaleString(navigator.languages.length ? navigator.languages[0] : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(',', '') : ''"
                    >
                        {{ $task->scheduled_at }}
                    </span>
                </p>
            </div>

            <template x-if="thumbnail.length">
                <figure
                    class="relative z-1 grid aspect-video shrink-0 grid-cols-1 place-items-center overflow-hidden rounded shadow-sm shadow-black/5 mb-3"
                >
                    <img
                        class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover"
                        x-cloak
                        x-transition.opacity
                        :src="thumbnail"
						alt="{{ $task->title }}"
                    >
                </figure>
            </template>

            @if (filled($task->instructions))
                <div
                    class="mb-3 text-2xs/[1.4em] opacity-65"
                    x-html="excerpt"
                >
                    {!! Str::limit(strip_tags($task->instructions), 200) !!}
                </div>
            @endif

            <hr>

            <div class="mb-5 grid grid-cols-1">
               <p class="opacity-55 mb-0 text-3xs relative z-1">
                    @lang('Categories:')
                    @if ($task->categories)
                        <span class="text-heading-foreground">{{ implode(', ', $task->categories) }}</span>
                    @endif
                </p>
               <p class="opacity-55 mb-0 text-3xs relative z-1">
                    @lang('Tags:')
                    @if ($task->tags)
                        <span class="text-heading-foreground">{{ implode(', ', $task->tags) }}</span>
                    @endif
                </p>
            </div>

            <div class="mb-5 flex items-center gap-1">
                @if ($task->status === 'draft')
                    <x-button
                        class="size-6 bg-transparent text-heading-foreground hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                        size="none"
                        title="{{ __('Approve') }}"
                        @click.prevent="editingTaskInitialStatus = 'draft'; approveTask({{ $task->id }})"
                        ::disabled="currentTasks.has('approveTask')"
                        x-show="status !== 'approved' && status !== 'scheduled'"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M11.425 5.7625C11.5128 5.85039 11.5621 5.96953 11.5621 6.09375C11.5621 6.21797 11.5128 6.33711 11.425 6.425L7.05 10.8C6.96211 10.8878 6.84297 10.9371 6.71875 10.9371C6.59453 10.9371 6.47539 10.8878 6.3875 10.8L4.5125 8.925C4.4297 8.83614 4.38463 8.71861 4.38677 8.59717C4.38891 8.47574 4.43811 8.35987 4.52399 8.27399C4.60987 8.1881 4.72574 8.13891 4.84718 8.13677C4.96862 8.13462 5.08614 8.1797 5.175 8.2625L6.71875 9.80547L10.7625 5.7625C10.8504 5.67472 10.9695 5.62541 11.0938 5.62541C11.218 5.62541 11.3371 5.67472 11.425 5.7625ZM15.9375 7.96875C15.9375 9.54482 15.4701 11.0855 14.5945 12.396C13.7189 13.7064 12.4744 14.7278 11.0183 15.3309C9.56216 15.9341 7.95991 16.0919 6.41413 15.7844C4.86834 15.4769 3.44845 14.718 2.334 13.6035C1.21955 12.4891 0.460597 11.0692 0.153121 9.52338C-0.154355 7.97759 0.0034526 6.37534 0.606588 4.91924C1.20972 3.46314 2.2311 2.21859 3.54155 1.34298C4.85201 0.467359 6.39268 0 7.96875 0C10.0814 0.00248113 12.1069 0.84284 13.6008 2.33673C15.0947 3.83063 15.935 5.85607 15.9375 7.96875ZM15 7.96875C15 6.5781 14.5876 5.21868 13.815 4.0624C13.0424 2.90611 11.9443 2.0049 10.6595 1.47272C9.3747 0.940543 7.96095 0.801301 6.59702 1.0726C5.2331 1.34391 3.98025 2.01357 2.99691 2.99691C2.01357 3.98024 1.34391 5.23309 1.07261 6.59702C0.801305 7.96095 0.940547 9.3747 1.47273 10.6595C2.0049 11.9443 2.90612 13.0424 4.0624 13.815C5.21868 14.5876 6.5781 15 7.96875 15C9.83292 14.9979 11.6201 14.2565 12.9383 12.9383C14.2565 11.6201 14.9979 9.83292 15 7.96875Z"
                            />
                        </svg>
                    </x-button>
                @endif
            </div>

            <div class="flex gap-3 mt-auto pb-4">
                <x-button
                    class="w-full"
                    href="{{ route('dashboard.user.titan-nova.agent.tasks.edit', $task->id) }}"
                    target="_blank"
                    variant="outline"
                    hover-variant="primary"
                >
                    @lang('Edit')
                </x-button>
            </div>
        </div>
    </div>
@empty
    <h4 class="titan-nova-task-item group flex w-full flex-shrink-0 flex-grow-0 basis-auto px-5 py-2 text-lg">
        @lang('No tasks were found.')
    </h4>
@endforelse

@if ($tasks->hasMorePages())
    <span
        class="hidden"
        data-next-page-url="{{ $tasks->nextPageUrl() }}"
        aria-hidden="true"
    ></span>
@endif

@pushOnce('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('titanNovaTaskItem', ({
                    id,
                    title,
                    content,
                    excerpt,
                    thumbnail,
                    status,
                    categories,
                    tags,
                    scheduled_at,
                }) => ({
                    id,
                    title,
                    content,
                    excerpt,
                    thumbnail,
                    status,
                    categories,
                    tags,
                    scheduled_at,
                    statusStates: {
                        'draft': {
                            label: '{{ __('Draft') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/><path d="M9 15l4 0"/></svg>',
                            css: 'bg-blue-500/15 text-blue-700 dark:text-blue-400'
                        },
                        'scheduled': {
                            label: '{{ __('Scheduled') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>',
                            css: 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400'
                        },
                        'completed': {
                            label: '{{ __('Completed') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -14.995 8.984l-.005 -.324l.005 -.324a10 10 0 0 1 14.995 -8.336zm-1.293 5.953a1 1 0 0 0 -1.32 -.083l-.094 .083l-3.293 3.292l-1.293 -1.292l-.094 -.083a1 1 0 0 0 -1.403 1.403l.083 .094l2 2l.094 .083a1 1 0 0 0 1.226 0l.094 -.083l4 -4l.083 -.094a1 1 0 0 0 -.083 -1.32z" /></svg>',
                            css: 'bg-green-500/15 text-green-700 dark:text-green-400'
                        },
                        'failed': {
                            label: '{{ __('Failed') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>',
                            css: 'bg-red-500/15 text-red-700 dark:text-red-400'
                        },
                    },

                    get statusState() {
                        const userLocale = navigator.languages && navigator.languages.length ? navigator.languages[0] : navigator.language || 'en-US';
                        const status =  this.status == 0 ? 'draft' : this.status == 1 && this.scheduled_at && this.scheduled_at < new Date().toLocaleTimeString(userLocale, { hour: '2-digit', minute: '2-digit', hour12: false }) ? 'completed' : 'scheduled';
                        return this.statusStates[status];
                    },
                }))
            });
        })
        ();
    </script>
@endPushOnce
