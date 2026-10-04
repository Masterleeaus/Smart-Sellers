@php
    use Illuminate\Support\Str;
@endphp

@forelse($tasks as $task)
    <tr
        class="titan-nova-task-item"
        data-task-id="{{ $task->id }}"
        :class="{ 'animate-pulse pointer-events-none': currentTasks.has('approveTask') || currentTasks.has('rejectTask') }"
        x-data='titanNovaTaskItem({ taskId: {{ $task->id }}, status: "{{ $task->status }}", thumbnail: "{{ $task->thumbnail ? url($task->thumbnail) : '' }}", scheduled_at: "{{ $task->scheduled_at }}", completed_at: "{{ $task->completed_at }}", instructions: @json($task->instructions), title: "{{$task->title}}", editSidedrawerId: "#titan-nova-sidedrawer" })'
    >
        <td>
            <div class="flex min-h-[36px] w-full items-center gap-2 overflow-hidden">
                <template x-if="thumbnail.length">
                    <figure
                        class="relative z-1 grid aspect-video h-[36px] w-[38px] shrink-0 grid-cols-1 place-items-center overflow-hidden rounded shadow-sm shadow-black/5"
                    >
                        <img
                            class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover"
                            x-cloak
                            x-transition.opacity
                            :src="thumbnail"
                        >
                    </figure>
                </template>

                <p
                    class="m-0 w-full max-w-[min(35vw,350px)] truncate text-sm font-medium empty:hidden"
                    x-text="title"
                >{{ $task->title }}</p>
            </div>
        </td>

        <td class="min-w-40">
            <p
                @class([
                    'm-0 inline-flex items-center gap-1.5 self-start rounded-full border px-2 py-1 text-[12px] font-medium leading-tight',
                    'text-blue-600' => $task->status === 'draft',
                    'text-yellow-700 dark:text-yellow-600' => $task->status === 'scheduled',
                    'text-green-600' => $task->status === 'completed',
                    'text-red-600' => $task->status === 'failed',
                ])
                :class="{
                    'text-blue-600': status === 'draft',
                    'text-yellow-700 dark:text-yellow-600': status === 'scheduled',
                    'text-green-600': status === 'completed',
                    'text-red-600': status === 'failed',
                }"
            >
                <span x-html="statusState.icon">
                    {{-- blade-formatter-disable --}}
					@if ($task->status === 'completed')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"> <path d="M5 12l5 5l10 -10"></path> </svg>
					@elseif($task->status === 'failed')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>
					@elseif($task->status === 'draft')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -12" /><path d="M7 8h10" /><path d="M7 12h10" /><path d="M7 16h10" /></svg>
					@else
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>
					@endif
					{{-- blade-formatter-enable --}}
                </span>

                <span x-text="statusState.label">
                    {{ Str::headline($task->status) }}
                </span>
            </p>
        </td>

        <td class="min-w-36">
            @php
                $date_to_show = $task->status === 'completed' && $task->completed_at ? $task->completed_at : $task->scheduled_at ?? '';

                if ($date_to_show) {
                    $carbon_date = \Carbon\Carbon::parse($date_to_show);
                    $formatted_date = $carbon_date->translatedFormat('M j, Y');
                    $formatted_time = $carbon_date->format('H:i');
                } else {
                    $formatted_date = '';
                    $formatted_time = '';
                }
            @endphp
            <p class="m-0 text-2xs">
                <span
                    class="text-heading-foreground"
                    x-text="formattedDate.date"
                >
                    {{ $formatted_date }}
                </span>
                <span
                    class="opacity-50"
                    x-text="formattedDate.time"
                >
                    {{ $formatted_time }}
                </span>
            </p>
        </td>

        <td class="min-w-52">
            <div class="flex items-center justify-end gap-2">

                 <x-dropdown.dropdown
                    class="doc-share-dropdown"
                    class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
                    anchor="end"
                    offsetY="20px"
                >
                    <x-slot:trigger
                        class="size-9 p-0"
                        size="none"
                        variant="ghost-shadow"
                    >
                        <span class="sr-only">
                            @lang('Share')
                        </span>
                        <x-tabler-share class="size-4" />
                    </x-slot:trigger>
                    <x-slot:dropdown
                        class="py-1 text-2xs"
                    >
                        <x-button
                            class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                            variant="link"
                            target="_blank"
                            href="http://twitter.com/share?text={{ $task->instructions }}"
                        >
                            <x-tabler-brand-x />
                            @lang('X')
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                            variant="link"
                            target="_blank"
                            href="https://wa.me/?text={{ htmlspecialchars($task->instructions) }}"
                        >
                            <x-tabler-brand-whatsapp />
                            @lang('Whatsapp')
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                            variant="link"
                            target="_blank"
                            href="https://t.me/share/url?url={{ request()->host() }}&text={{ htmlspecialchars($task->instructions) }}"
                        >
                            <x-tabler-brand-telegram />
                            @lang('Telegram')
                        </x-button>
                    </x-slot:dropdown>
                </x-dropdown.dropdown>

                <x-button
                    class="size-9 p-0"
                    size="none"
                    variant="ghost-shadow"
                    title="{{ __('Edit') }}"
                    href="{{ route('dashboard.user.titan-nova.agent.tasks.edit', $task->id) }}"
                >
                    <x-tabler-edit class="size-4" />
                </x-button>

                @if ($task->status !== 'completed')
                    <x-dropdown.dropdown
                        anchor="end"
                        offsetY="10px"
                    >
                        <x-slot:trigger
                            class="size-9 p-0"
                            size="none"
                            variant="ghost-shadow"
                        >
                            <span class="sr-only">
                                @lang('More')
                            </span>
                            <x-tabler-dots class="size-4" />
                        </x-slot:trigger>

                        <x-slot:dropdown
                            class="min-w-[150px] rounded-lg bg-background p-2 shadow-lg"
                        >
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-foreground/5"
                                href="#"
                                @click.prevent="$dispatch('titan-nova:duplicate-task', { id: {{ $task->id }} })"
                            >
                                @lang('Duplicate')
                            </a>
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-red-500 hover:text-white"
                                href="#"
                                @click.prevent="rejectTask({{ $task->id }}); toggle('collapse');"
                            >
                                @lang('Delete')
                            </a>
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5">
            <h4 class="titan-nova-task-item group m-0 flex w-full flex-shrink-0 flex-grow-0 basis-auto text-lg">
                @lang('No tasks were found.')
            </h4>
        </td>
    </tr>
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
                    taskId,
                    status,
                    thumbnail,
                    task_mode,
                    scheduled_at,
                    completed_at,
                    content,
                    title,
                    editSidedrawerId
                }) => ({
                    taskId,
                    status,
                    thumbnail,
                    task_mode,
                    scheduled_at,
                    completed_at,
                    content,
                    title,
                    editSidedrawerId,
                    statusStates: {
                        'draft': {
                            label: '{{ __('Draft') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -12" /><path d="M7 8h10" /><path d="M7 12h10" /><path d="M7 16h10" /></svg>',
                            css: 'bg-blue-500/15 text-blue-700 dark:text-blue-400'
                        },
                        'scheduled': {
                            label: '{{ __('Scheduled') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>',
                            css: 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400'
                        },
                        'completed': {
                            label: '{{ __('Completed') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"> <path d="M5 12l5 5l10 -10"></path> </svg>',
                            css: 'bg-green-500/15 text-green-700 dark:text-green-400'
                        },
                        'failed': {
                            label: '{{ __('Failed') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>',
                            css: 'bg-red-500/15 text-red-700 dark:text-red-400'
                        },
                    },

                    get statusState() {
                        return this.statusStates[this.status]
                    },

                    get formattedDate() {
                        const locale = navigator.languages.length ? navigator.languages[0] : 'en-US';
                        const dateToShow = this.status === 'completed' && this.completed_at ? this.completed_at : this.scheduled_at ? this.scheduled_at :
                            '';

                        if (!dateToShow) return '';

                        const date = new Date(dateToShow);
                        const formattedDate = new Intl.DateTimeFormat(locale, {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        }).format(date);
                        const formattedTime = new Intl.DateTimeFormat(locale, {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        }).format(date);

                        return {
                            date: formattedDate,
                            time: formattedTime
                        };
                    }
                }))
            });
        })
        ();
    </script>
@endPushOnce
