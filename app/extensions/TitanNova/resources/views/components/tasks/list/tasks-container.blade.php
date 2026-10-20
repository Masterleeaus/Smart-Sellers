<x-table
    class="relative"
    id="titan-nova-tasks-list"
    ::class="{ 'animate-pulse': currentTasks.has('fetchingTasks') }"
    x-ref="tasksList"
>
    <x-slot:head>
        <th>
            <button
                class="flex items-center gap-2"
                @click.prevent="sortTasks('title')"
                type="button"
            >
                @lang('Title')
                <span class="-ms-1 flex flex-col items-center">
                    <x-tabler-chevron-up
                        class="-mb-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'content' && sort.sortDirection === 'desc' }"
                        stroke-width="2.5"
                    />
                    <x-tabler-chevron-down
                        class="-mt-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'content' && sort.sortDirection === 'asc' }"
                        stroke-width="2.5"
                    />
                </span>
            </button>
        </th>

        <th>
            <button
                class="flex items-center gap-2"
                @click.prevent="sortTasks('status')"
                type="button"
            >
                @lang('Status')
                <span class="-ms-1 flex flex-col items-center">
                    <x-tabler-chevron-up
                        class="-mb-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'status' && sort.sortDirection === 'desc' }"
                        stroke-width="2.5"
                    />
                    <x-tabler-chevron-down
                        class="-mt-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'status' && sort.sortDirection === 'asc' }"
                        stroke-width="2.5"
                    />
                </span>
            </button>
        </th>

        <th class="min-w-36">
            <button
                class="flex items-center gap-2"
                type="button"
                @click.prevent="sortTasks('created_at')"
            >
                @lang('Date')
                <span class="-ms-1 flex flex-col items-center">
                    <x-tabler-chevron-up
                        class="-mb-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'created_at' && sort.sortDirection === 'desc' }"
                        stroke-width="2.5"
                    />
                    <x-tabler-chevron-down
                        class="-mt-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'created_at' && sort.sortDirection === 'asc' }"
                        stroke-width="2.5"
                    />
                </span>
            </button>
        </th>

        <th class="text-end">
            @lang('Actions')
        </th>
    </x-slot:head>

    <x-slot:body>
        @include('titan-nova::components.tasks.list.task-items', ['items' => $tasks])
    </x-slot:body>

    <x-slot:foot
        class="border-t"
    >
        @if ($tasks->hasMorePages())
            <tr>
                <td colspan="5">
                    <div class="titan-nova-tasks-list-load-more-wrap">
                        <a
                            class="titan-nova-tasks-list-load-more group inline-flex w-full items-center justify-center gap-2 text-xs font-medium"
                            href="{{ route('dashboard.user.titan-nova.agent.task-items', ['page' => 2, 'task_style' => 'list']) }}"
                            x-intersect:enter.half="!allTasksLoaded && !loadingMore && $ajax($el.href, { target: '_none' })"
                            x-ref="loadMoreTrigger"
                            @click.prevent="!allTasksLoaded && !loadingMore && $ajax($el.href, { target: '_none' })"
                            @ajax:send="onAjaxSend"
                            @ajax:success="onAjaxSuccess"
                            @ajax:error="onAjaxError"
                        >
                            <span x-text="allTasksLoaded ? '{{ __('All tasks loaded') }}' : loadingMore ? '{{ __('Loading...') }}' : '{{ __('Load more') }}'">
                                @lang('Load more')
                            </span>
                            <span class="inline-grid size-8 place-items-center rounded-full border">
                                <x-tabler-progress-down
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-5"
                                    x-show="!loadingMore && !allTasksLoaded"
                                />
                                <x-tabler-loader-2
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 animate-spin"
                                    x-cloak
                                    x-show="loadingMore"
                                />
                                <x-tabler-check
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-5"
                                    x-cloak
                                    x-show="!loadingMore && allTasksLoaded"
                                />
                            </span>
                        </a>
                    </div>
                </td>
            </tr>
        @endif
    </x-slot:foot>
</x-table>
