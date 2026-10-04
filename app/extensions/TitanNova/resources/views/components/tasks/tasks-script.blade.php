<script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
<script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>

<script>
    (() => {
        document.addEventListener('alpine:init', () => {
            Alpine.data('titanNovaTasks', () => ({
                totalTasksCount: {{ $total_tasks_count ?? 0 }},
                scheduledTasksCount: {{ $scheduled_tasks_count ?? 0 }},
                pendingTasksCount: {{ $pending_tasks_count ?? 0 }},
                defaultAgentId: {{ $default_agent_id ?? 'null' }},
                creationStatus: @json($creation_status ?? ['status' => 'idle']),
                generatedTasksCount: {{ $creation_status['created_tasks_count'] ?? $creation_status['created_count'] ?? 0 }},
                creationStatusPollId: null,
                creationStatusEndpoint: '{{ route('dashboard.user.titan-nova.agent.api.creation-status') }}',
                allTasksLoaded: false,
                loadingMore: false,
                editingTask: null,
                editingTaskPagination: {
                    currentPage: null,
                    prevPageUrl: null,
                    nextPageUrl: null,
                },
                filters: {
                    channel: '',
                    channel_id: '',
                    agent_id: [],
                    account: '',
                },
                sort: {
                    'sortBy': 'created_at',
                    'sortDirection': 'desc'
                },
                editingTaskInitialStatus: null,
                currentTasks: new Set(),
                pendingCounterEl: document.querySelector('#titan-nova-pending-tasks-counter .lqd-number-counter-value'),
                scheduledCounterEl: document.querySelector('#titan-nova-scheduled-tasks-counter .lqd-number-counter-value'),
                flickityData: null,
                cols: {
                    '(max-width: 767px)': 1,
                    '(min-width: 768px) and (max-width: 991px)': 2,
                    '(min-width: 992px)': 4,
                },
                autoNavigateOnTaskUpdates: true,
                readyTextTemplate: "{{ ':generated of :total tasks are being created.' }}",

                get isCreationBusy() {
                    return ['queued', 'generating'].includes(this.creationStatus?.status);
                },

                get creationStatusLabel() {
                    if (this.isCreationBusy) {
                        return '{{ __('We are generating fresh tasks for you...') }}';
                    }

                    return '{{ __('Task creation done.') }}';
                },

                get showReadyProgressText() {
                    return this.isCreationBusy;
                },

                get readyProgressText() {
                    const generatedFromStatus = Number(this.creationStatus?.created_tasks_count ?? this.generatedTasksCount ?? 0);
                    const plannedFromStatus = Number(this.creationStatus?.planned_tasks_count ?? this.creationStatus?.total_requested ?? 0);
                    const pendingCount = Math.max(this.pendingTasksCount ?? 0, 0);

                    const readyCount = Math.max(0, pendingCount + generatedFromStatus);
                    const totalCount = Math.max(0, pendingCount + plannedFromStatus);

                    return this.readyTextTemplate
                        .replace(':generated', Math.max(0, readyCount))
                        .replace(':total', Math.max(0, totalCount));
                },

                get sortLabel() {
                    const {
                        sortBy
                    } = this.sort;
                    let label = sortBy;

                    switch (sortBy) {
                        case 'created_at':
                            label = '{{ __('Date') }}';
                            break;
                    }

                    return label;
                },

                init() {
                    if ('Flickity' in window && this.$refs.tasksCarousel) {
                        Flickity.prototype._createResizeClass = function() {
                            this.element.classList.add('flickity-resize');
                        };

                        Flickity.createMethods.push('_createResizeClass');

                        var resize = Flickity.prototype.resize;
                        Flickity.prototype.resize = function() {
                            this.element.classList.remove('flickity-resize');
                            resize.call(this);
                            this.element.classList.add('flickity-resize');
                        };

                        this.flickityData = new Flickity(this.$refs.tasksCarousel, {
                            cellSelector: '.titan-nova-task-item',
                            prevNextButtons: false,
                            pageDots: false,
                            cellAlign: 'left',
                            contain: true
                        });

						window.dispatchEvent(new Event('resize'));

                        this.flickityData.on('dragStart', () => {
                            this.flickityData.slider.style.willChange = 'transform';
                        });
                        this.flickityData.on('settle', () => {
                            this.flickityData.slider.style.willChange = 'auto';
                        });
                    }

                    this.externalRefreshHandler = () => {
                        this.filterTasks();
                    };

                    window.addEventListener('titan-nova:task-created', this.externalRefreshHandler);
                    window.addEventListener('titan-nova:duplicate-task', event => {
                        if (!event.detail?.id) {
                            return;
                        }

                        this.duplicateTask(event.detail.id);
                    });

                    this.startCreationStatusPolling();
                },

                getSidedrawer() {
                    const editSidedrawerEl = document.querySelector('#titan-nova-sidedrawer');
                    const editSidedrawerData = Alpine.$data(editSidedrawerEl);

                    return editSidedrawerData;
                },

                async fetchTask({
                    query,
                    url,
                    taskKey = null,
                }) {
                    if (!url && !query) {
                        return toastr.error('@lang('Please provide a valid url or query.')')
                    }

                    const taskKeys = ['fetchingTask'];

                    if (taskKey) {
                        taskKeys.push(taskKey);
                    }

                    taskKeys.forEach(key => this.currentTasks.add(key));

                    url = url ?? `/dashboard/user/titan-nova/agent/api/tasks?per_page=1&${query}`;

                    try {
                        const res = await fetch(url, {
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();

                        if (!data.success) {
                            const message = data.message || '@lang('Failed fetching task')'
                            toastr.error(message);

                            return null;
                        }

                        const task = (data.tasks ?? data.tasks).data[0];

                        if (!task) {
                            toastr.warning('@lang('No Tasks Found.')')

                            return null;
                        }

                        return data;
                    } catch (err) {
                        const message = err.message || '@lang('Failed fetching task')'
                        toastr.error(message);

                        return null;
                    } finally {
                        taskKeys.forEach(key => this.currentTasks.delete(key));
                    }
                },

                async fetchCreationStatus() {
                    if (!this.creationStatusEndpoint) {
                        return;
                    }

                    try {
                        const response = await fetch(this.creationStatusEndpoint, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();

                        if (data?.success) {
                            this.creationStatus = data.status ?? { status: 'idle' };
                            if (typeof data.ready_text_template === 'string' && data.ready_text_template.length) {
                                this.readyTextTemplate = data.ready_text_template;
                            }

                            if (typeof data.pending_tasks_count === 'number' || typeof data.scheduled_tasks_count === 'number') {
                                const pending = typeof data.pending_tasks_count === 'number'
                                    ? data.pending_tasks_count
                                    : this.pendingTasksCount;

                                const scheduled = typeof data.scheduled_tasks_count === 'number'
                                    ? data.scheduled_tasks_count
                                    : this.scheduledTasksCount;

                                this.updateCounters(pending, scheduled, data.total_tasks_count);
                            }
                            if (typeof data.created_tasks_count === 'number') {
                                this.generatedTasksCount = data.created_tasks_count;
                            }
                        }
                    } catch (error) {
                        console.error('Failed to fetch creation status', error);
                    }
                },

                startCreationStatusPolling() {
                    if (!this.defaultAgentId && (!this.creationStatus || this.creationStatus.status === 'idle')) {
                        return;
                    }

                    this.fetchCreationStatus();
                    this.stopCreationStatusPolling();

                    this.creationStatusPollId = setInterval(() => {
                        this.fetchCreationStatus();
                    }, 10000);
                },

                stopCreationStatusPolling() {
                    if (this.creationStatusPollId) {
                        clearInterval(this.creationStatusPollId);
                        this.creationStatusPollId = null;
                    }
                },

                async openEditSidedrawer({
                    query,
                    url,
                    taskKey = null,
                    autoNavigateOnTaskUpdates = true
                }) {
                    const sidedrawer = this.getSidedrawer();
                    const data = await this.fetchTask({
                        query,
                        url,
                        taskKey
                    });
                    const task = (data.tasks ?? data.tasks).data[0];

                    if (!task) {
                        sidedrawer.sidedrawerOpen = false;
                        return;
                    }

                    sidedrawer.sidedrawerOpen = true;

                    this.editingTask = task;
                    const pagination = data.tasks ?? data.tasks;
                    this.editingTaskPagination.currentPage = pagination.current_page;
                    this.editingTaskPagination.prevPageUrl = pagination.prev_page_url;
                    this.editingTaskPagination.nextPageUrl = pagination.next_page_url;
                    this.editingTaskInitialStatus = task.status;

                    this.autoNavigateOnTaskUpdates = autoNavigateOnTaskUpdates;
                },

                async updateTask(taskId) {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    this.currentTasks.add('updateTask');

                    const {
                        scheduled_at,
                        content,
                        task_type,
                        task_mode,
                        priority,
                        duration_minutes,
                        channel
                    } = this.editingTask;

                    const res = await fetch(`/dashboard/user/titan-nova/agent/api/tasks/${taskId}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            scheduled_at: new Date(scheduled_at).toISOString(),
                            content,
                            task_type,
                            task_mode,
                            priority,
                            duration_minutes,
                            channel,
                        })
                    });
                    const data = await res.json();

                    this.currentTasks.delete('updateTask');

                    if (!data.success) {
                        const message = data.message ?? '@lang('An error occurred')';
                        return toastr.error(message);
                    }

                    toastr.success(data.message ?? '@lang('Task updated successfully.')');

                    this.editingTask = data.task;

                    const taskEl = document.querySelector(`.titan-nova-task-item[data-task-id="${taskId}"]`);
                    const taskElAlpineData = taskEl && Alpine.$data(taskEl);
                    const dashboardCalendarEl = document.querySelector('#titan-nova-calendar');

                    if (taskElAlpineData) {
                        ['task_type', 'task_mode', 'priority', 'duration_minutes', 'channel', 'scheduled_at', 'content', 'status'].forEach(prop => {
                            taskElAlpineData[prop] = data.task[prop];
                        })
                    }

                    if (dashboardCalendarEl) {
                        const calendarData = Alpine.$data(dashboardCalendarEl);

                        if (calendarData.calendar) {
                            calendarData.calendar.refetchEvents();
                        }
                    }

                    if (this.editingTaskInitialStatus === 'draft' && data.task.status === 'scheduled') {
                        this.updateCounters(
                            Math.max(0, this.pendingTasksCount - 1),
                            Math.min(this.totalTasksCount, this.scheduledTasksCount + 1)
                        );
                    }

                    this.editingTaskInitialStatus = data.task.status;

                    this.$dispatch('titan-nova-task-updated', {
                        task: data.task
                    });
                },

                async approveTask(taskId) {

					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    this.currentTasks.add('approveTask')

                    const res = await fetch(`/dashboard/user/titan-nova/agent/tasks/${taskId}/approve`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    this.currentTasks.delete('approveTask');

                    if (!data.success) {
                        const message = data.message ?? '@lang('An error occurred')';
                        return toastr.error(message)
                    }

                    const message = data.message ?? '@lang('Task approved and scheduled!')';

                    toastr.success(message);

                    const taskEl = document.querySelector(`.titan-nova-task-item[data-task-id="${taskId}"]`);
                    const dashboardCalendarEl = document.querySelector('#titan-nova-calendar');

                    if (taskEl && Alpine.$data(taskEl).status) {
                        Alpine.$data(taskEl).status = 'scheduled';
                    }

                    if (this.editingTaskInitialStatus === 'draft') {
                        this.updateCounters(
                            Math.max(0, this.pendingTasksCount - 1),
                            Math.min(this.totalTasksCount, this.scheduledTasksCount + 1)
                        );
                    }

                    if (dashboardCalendarEl) {
                        const calendarData = Alpine.$data(dashboardCalendarEl);

                        if (calendarData.calendar) {
                            calendarData.calendar.refetchEvents();
                        }
                    }

                    this.editingTaskInitialStatus = 'scheduled';

                    this.$dispatch('titan-nova-task-approved', {
                        taskId: taskId
                    });
                },

                async rejectTask(taskId) {

					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    if (!confirm("{{ __('Are you sure you want to reject and delete the task?') }}")) {
                        return
                    }

                    this.currentTasks.add('rejectTask')

                    const res = await fetch(`/dashboard/user/titan-nova/agent/tasks/${taskId}/reject`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    this.currentTasks.delete('rejectTask')

                    if (!data.success) {
                        const message = data.message ?? '@lang('An error occurred')';
                        return toastr.error(message)
                    }

                    const message = data.message ?? '@lang('Task rejected and deleted.')';

                    toastr.success(message);

                    const taskEl = document.querySelector(`.titan-nova-task-item[data-task-id="${taskId}"]`);
                    const dashboardCalendarEl = document.querySelector('#titan-nova-calendar');

                    if (taskEl) {
                        const closestCarousel = taskEl.closest('.flickity-enabled');

                        taskEl.remove();

                        if (closestCarousel && 'Flickity' in window) {
                            Flickity.data(closestCarousel)?.reloadCells();
                            Flickity.data(closestCarousel)?.reposition();
                        }
                    }

                    if (dashboardCalendarEl) {
                        const calendarData = Alpine.$data(dashboardCalendarEl);

                        if (calendarData.calendar) {
                            calendarData.calendar.refetchEvents();
                        }
                    }

                    const sidedrawer = this.getSidedrawer();
                    sidedrawer.sidedrawerOpen = false;

                    this.updateCounters(
                        Math.max(0, this.pendingTasksCount - 1),
                        Math.max(0, this.scheduledTasksCount)
                    );

                    this.editingTaskInitialStatus = null;

                    this.$dispatch('titan-nova-task-rejected', {
                        taskId: taskId
                    });
                },

                async duplicateTask(taskId) {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif
                    if (this.currentTasks.has('duplicateTask')) {
                        return;
                    }

                    this.currentTasks.add('duplicateTask');

                    try {
                        const res = await fetch(`/dashboard/user/titan-nova/agent/tasks/${taskId}/duplicate`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            throw new Error(data.message || '@lang('Failed to duplicate task')');
                        }

                        toastr.success(data.message || '@lang('Task duplicated successfully.')');
                        this.filterTasks();
                    } catch (error) {
                        toastr.error(error.message || '@lang('Failed to duplicate task')');
                    } finally {
                        this.currentTasks.delete('duplicateTask');
                    }
                },

                async regenerateTaskContent(taskId) {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    const taskKey = `regenerateTask-${taskId}`;

                    if (this.currentTasks.has(taskKey)) {
                        return;
                    }

                    this.currentTasks.add(taskKey);

                    try {
                        const res = await fetch(`/dashboard/user/titan-nova/agent/api/tasks/${taskId}/regenerate`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            throw new Error(data.message || '@lang('Failed to regenerate task content.')');
                        }

                        toastr.success(data.message || '@lang('Task content regenerated successfully.')');

                        const taskEl = document.querySelector(`.titan-nova-task-item[data-task-id="${taskId}"]`);

                        if (taskEl) {
                            const taskElData = Alpine.$data(taskEl);

                            if (taskElData) {
                                taskElData.content = data.task.content;
                                taskElData.task_mode = data.task.task_mode;
                            }
                        }

                        if (this.editingTask?.id === taskId) {
                            this.editingTask = {
                                ...this.editingTask,
                                instructions: data.task.content,
                                task_mode: data.task.task_mode,
                                hashtags: data.task.hashtags,
                            };
                        }
                    } catch (error) {
                        toastr.error(error.message || '@lang('Failed to regenerate task content.')');
                    } finally {
                        this.currentTasks.delete(taskKey);
                    }
                },

                onAjaxSend() {
                    this.loadingMore = true;
                },

                onAjaxSuccess() {
                    const {
                        html
                    } = this.$event.detail;
                    const nextPageUrl = html.querySelector('[data-next-page-url]')?.getAttribute('data-next-page-url');
                    const newTasks = [...html.children || []].filter(el => el.classList.contains('titan-nova-task-item'));

                    if (newTasks.length) {
                        if (this.$refs.tasksCarousel && this.flickityData) {
                            this.appendNewCarouselTasks(newTasks);
                        }
                        if (this.$refs.tasksList) {
                            this.appendNewListTasks(newTasks);
                        }
                    }

                    if (nextPageUrl) {
                        this.$refs.loadMoreTrigger?.setAttribute('href', nextPageUrl);
                    } else {
                        this.allTasksLoaded = true;
                    }
                },

                appendNewCarouselTasks(newTasks) {
                    const {
                        cells
                    } = this.flickityData;
                    const lastTaskItem = cells.at(-2);
                    const lastTaskItemIndex = cells.indexOf(lastTaskItem);
                    let cols = 1;

                    Object.keys(this.cols).forEach(mq => {
                        if (window.matchMedia(mq).matches) {
                            cols = this.cols[mq];
                        }
                    });

                    const updateDraggable = enabled => {
                        this.flickityData.options.draggable = enabled;
                        this.flickityData.slider.classList.toggle('select-none', !enabled);
                        this.flickityData.updateDraggable();
                    }

                    const onSettle = () => {
                        updateDraggable(true);

                        this.flickityData.insert(newTasks, cells.length - 1);
                        this.flickityData.selectCell(lastTaskItemIndex - Math.max(0, cols - 2), false, true);

                        this.loadingMore = false;

                        this.flickityData.off('settle', onSettle);
                    }


                    if (this.flickityData.isAnimating) {
                        updateDraggable(false);
                        this.flickityData.on('settle', onSettle);
                    } else {
                        onSettle();
                    }
                },

                appendNewListTasks(newTasks) {
                    const tableBodyEl = this.$refs.tasksList.querySelector('tbody');
                    const appendNewTasksTo = tableBodyEl ? tableBodyEl : this.$refs.tasksList;

                    appendNewTasksTo.append(...newTasks);

                    this.loadingMore = false;
                },

                onAjaxError() {
                    this.loadingMore = false;
                },

                async filterTasks({
                    channel = null,
                    channel_id = null,
                    agent_id = null,
                    query = ''
                } = {}) {
                    const taskStyle = this.$refs.tasksCarousel ? 'carousel' : 'list';
                    this.currentTasks.add('fetchingTasks');

                    this.allTasksLoaded = false;
                    this.loadingMore = true;

                    const params = new URLSearchParams({
                        sort_by: this.sort.sortBy,
                        sort_direction: this.sort.sortDirection,
                        task_style: taskStyle
                    });

                    this.filters.channel = channel ?? this.filters.channel;

                    this.filters.channel_id = channel_id ?? this.filters.channel_id;

                    if (agent_id) {
                        if (this.filters.agent_id.includes(agent_id)) {
                            this.filters.agent_id = this.filters.agent_id.filter(id => id !== agent_id);
                        } else {
                            this.filters.agent_id.push(agent_id);
                        }
                    }

                    Object.entries(this.filters).forEach(([key, value]) => {
                        const hasValue = Array.isArray(value) ? value.length : !!value;

                        if (hasValue) {
                            params.append(key, value);
                        }
                    })

                    let url = `/dashboard/user/titan-nova/agent/task-items?${params}${query ? `&${query}` : ''}`;

                    const res = await fetch(url, {
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                    this.loadingMore = false;
                    this.currentTasks.delete('fetchingTasks');

                    if (!res.ok) {
                        return toastr.error('@lang('Failed fetching task')');
                    }

                    const data = await res.text();

                    const tempEl = document.createElement('div');
                    tempEl.innerHTML = data;

                    if (this.$refs.tasksCarousel && this.flickityData) {
                        this.flickityData.remove(
                            this.flickityData.cells
                            .map(cell => cell.element)
                            .filter(element => !element.classList.contains('titan-nova-tasks-carousel-load-more-wrap'))
                        );
                        this.flickityData.insert(tempEl.children, 0);
                        this.flickityData.selectCell(0, false, true);
                    }

                    if (this.$refs.tasksList) {
                        const tableBodyEl = this.$refs.tasksList.querySelector('tbody');
                        const appendNewTasksTo = tableBodyEl ? tableBodyEl : this.$refs.tasksList;

                        appendNewTasksTo.innerHTML = data;
                    }

                    const nextPageUrl = tempEl.querySelector('[data-next-page-url]')?.getAttribute('data-next-page-url');

                    if (nextPageUrl) {
                        this.$refs.loadMoreTrigger?.setAttribute('href', nextPageUrl);
                    } else {
                        this.allTasksLoaded = true;
                    }
                },

                async sortTasks(sortBy, sortDirection = 'toggle') {
                    if (!sortBy || !sortDirection) {
                        return toastr.error('{{ __('Please provide all sort options.') }}')
                    }

                    this.sort.sortBy = sortBy;

                    if (sortDirection === 'toggle') {
                        this.sort.sortDirection =
                            (this.sort.sortBy !== sortBy || this.sort.sortDirection === 'asc') ?
                            'desc' :
                            'asc';
                    } else if (sortDirection === 'desc' || sortDirection === 'asc') {
                        this.sort.sortDirection = sortDirection;
                    }

                    await this.filterTasks()
                },

                updateCounters(pendingTasksCount, scheduledTasksCount, totalTasksCount = null) {
                    this.pendingTasksCount = pendingTasksCount;
                    this.scheduledTasksCount = scheduledTasksCount;
                    if (typeof totalTasksCount === 'number') {
                        this.totalTasksCount = totalTasksCount;
                    }

                    if (this.pendingCounterEl) {
                        Alpine.$data(this.pendingCounterEl).updateValue({
                            value: this.pendingTasksCount
                        });
                    }

                    if (this.scheduledCounterEl) {
                        Alpine.$data(this.scheduledCounterEl).updateValue({
                            value: this.scheduledTasksCount
                        });
                    }
                },
            }))
        })
    })();
</script>
