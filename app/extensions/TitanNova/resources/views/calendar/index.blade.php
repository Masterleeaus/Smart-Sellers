@php
    use Illuminate\Support\Str;

    $theme = \Theme::get();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Calendar'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('View and plan your content'))
@section('titlebar_actions')
    @include('titan-nova::components.titlebar-actions')
@endsection

@if ($theme !== 'titan-nova-dashboard')
    @section('titlebar_actions')
        @include('titan-nova::calendar.calendar-filters', ['agents' => $agents])
    @endsection
@endif

@push('css')
    <style>
        #titan-nova-calendar .fc-toolbar.fc-header-toolbar {
            margin-bottom: 2rem;
        }

        #titan-nova-calendar .fc-header-toolbar .fc-toolbar-chunk:first-child {
            border-bottom: 1px solid hsl(var(--border));
        }

        #titan-nova-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button {
            padding: 15px 8px;
            border-block: 1px solid transparent;
            border-radius: 0 !important;
            margin-bottom: -1px;
            color: hsl(var(--foreground) / 65%);
        }

        #titan-nova-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button:hover {
            background: none;
            color: hsl(var(--foreground));
        }

        #titan-nova-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button:focus {
            box-shadow: none !important;
        }

        #titan-nova-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button.fc-button-active {
            border-bottom-color: currentColor;
            background: none;
            color: hsl(var(--heading-foreground));
        }

        #titan-nova-calendar .fc-toolbar-title {
            font-size: 17px;
            font-weight: 500;
        }

        #titan-nova-calendar .fc-event {
            padding: 0;
            background: none;
        }

        .fc-event.lqd-fc-filter-out {
            pointer-events: none;
        }

        .fc-event.lqd-fc-filter-out .lqd-event-content {
            opacity: 0.5;
            transform: scale(0.9);
        }
    </style>
@endpush

@section('content')
    <div @class([
        'pb-10',
        'group-[&.focus-mode]/body:pt-8' =>
            $theme === 'titan-nova-dashboard',
        'pt-10' => $theme !== 'titan-nova-dashboard',
    ])>
        @if ($theme === 'titan-nova-dashboard')
            @include('titan-nova::calendar.titlebar')
        @endif

        <div
            id="titan-nova-calendar"
            x-data="titanNovaCalendar"
            @filter-calendar-by-agent.window="filterByAgent"
        ></div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/fullcalendar/index.global.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/core.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/dom.min.js') }}"></script>

    <script>
        function getFullURL( path ) {
            const BASE_URL = @json(url('/')) + '/';
            return BASE_URL + path;
        }
        document.addEventListener('alpine:init', () => {
            const floatingUi = window.FloatingUIDOM;
            const {
                computePosition,
                flip,
                offset,
                shift,
            } = floatingUi;

            Alpine.data('titanNovaCalendar', () => ({
                calendar: null,
                calendarEl: document.querySelector('#titan-nova-calendar'),
                filters: {
                    agentIds: []
                },

                get calendarInitialView() {
                    return window.innerWidth <= 480 ? 'dayGridTwoDay' : window.innerWidth <= 869 ? 'dayGridThreeDay' : 'dayGridMonth'
                },
                get calendarViews() {
                    return window.innerWidth <= 480 ?
                        'dayGridTwoDay,timeGridDay,listWeek' : window.innerWidth <= 869 ?
                        'dayGridThreeDay,timeGridWeek,timeGridDay,listWeek' : 'dayGridMonth,timeGridWeek,timeGridDay,listWeek';
                },
                get calendarHeaderToolbar() {
                    return {
                        start: this.calendarViews,
                        center: 'title',
                        end: 'today prev,next'
                    }
                },

                init() {
                    this.calendar = new FullCalendar.Calendar(this.calendarEl, {
                        initialView: this.calendarInitialView,
                        headerToolbar: this.calendarHeaderToolbar,
                        contentHeight: 'auto',
                        eventSources: [{
                            id: 'titan-nova-calendar-events',
                            events: async ({
                                startStr,
                                endStr
                            }, successCallback, failureCallback) => {
                                const params = new URLSearchParams({
                                    start_date: startStr,
                                    end_date: endStr,
                                    per_page: 1000,
                                    date_column: 'scheduled_at'
                                });

                                try {
                                    const response = await fetch(`{{ route('dashboard.user.titan-nova.agent.api.tasks') }}?${params}`);

                                    if (!response.ok) {
                                        throw new Error('{{ __('Failed to fetch tasks') }}');
                                    }

                                    const data = await response.json();

                                    if (!data.success) {
                                        throw new Error(data.message || '{{ __('Failed to fetch tasks') }}');
                                    }

                                    const userLocale = navigator.languages && navigator.languages.length ? navigator.languages[0] : navigator
                                        .language;
                                    const events = data.tasks.data.map(task => {
                                        const scheduledAt = task.scheduled_at ? new Date(task.scheduled_at) : null;
                                        const dateStr = scheduledAt?.toISOString().split('T')[0];
                                        const timeStr = scheduledAt?.toLocaleTimeString(userLocale, {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            hour12: false
                                        });

                                        return {
                                            id: task.id,
                                            title: task.agent?.name || '',
                                            start: dateStr,
                                            end: dateStr,
                                            allDay: true,
                                            classNames: ['group'],
                                            extendedProps: {
                                                agentId: task.agent_id,
                                                title: task.title,
                                                instructions: task.content ? task.content.split(/\s+/).slice(0, 30).join(' ') + (task.content.split(/\s+/).length > 30 ? '...' : '') : '',
                                                thumbnail: task.thumbnail || '',
                                                time: timeStr,
                                                tags: task.tags || [],
                                                categories: task.categories || [],
                                                status: task.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '',
                                                scheduledAtTimestamp: scheduledAt?.getTime() || 0,
                                            },
                                        };
                                    });

                                    successCallback(events);

                                    this.applyFilters();
                                } catch (error) {
                                    toastr.error(error.message || '{{ __('Failed to load calendar events') }}');
                                    failureCallback(error);
                                }
                            },
                        }],
                        eventOrder: (a, b) => a.extendedProps.scheduledAtTimestamp - b.extendedProps.scheduledAtTimestamp,
                        views: {
                            dayGridThreeDay: {
                                type: 'dayGridWeek',
                                duration: {
                                    days: 3
                                },
                                buttonText: '{{ __('3 day') }}'
                            },
                            dayGridTwoDay: {
                                type: 'dayGridWeek',
                                duration: {
                                    days: 2
                                },
                                buttonText: '{{ __('2 day') }}'
                            },
                        },
                        eventContent: arg => {
                            return {
                                html: `<div class="lqd-event-content rounded-full transition flex whitespace-nowrap gap-1.5 items-center p-2.5 rounded-full bg-foreground/5" data-agent-id="${arg.event.extendedProps.agentId}">

	<p class="lqd-event-info text-[12px] leading-none shrink-0 font-medium mb-0">${arg.event.extendedProps.time}</p>

	<p class="lqd-event-title text-2xs leading-4 font-semibold w-full truncate mb-0">${arg.event.extendedProps.title}</p>

	<div class="lqd-event-card text-foreground invisible whitespace-normal fixed left-0 inset-auto bottom-full !z-[100] w-[min(400px,calc(100vw-30px))] !translate-x-0 translate-y-1 rounded-[10px] bg-background p-3.5 opacity-0 shadow-md shadow-black/5 transition before:absolute before:-inset-2.5">
		<div class="flex flex-col gap-1 items-start mb-2 relative z-1">
			<div class="flex items-center gap-2.5">
				${arg.event.extendedProps.title}
			</div>

			<span class="text-3xs opacity-55">
				${arg.event.extendedProps.status}
                <span class="text-xl align-text-top leading-[0] mx-1">.</span>
                ${new Date(arg.event.start).toLocaleDateString()}
                ${'{{ __('at') }}'}
                ${arg.event.extendedProps.time}
			</span>
		</div>
		${arg.event.extendedProps.thumbnail.length ?
			`<figure
                    class="relative z-1 grid aspect-video shrink-0 grid-cols-1 place-items-center overflow-hidden rounded shadow-sm shadow-black/5 mb-3"
                >
                    <img
                        class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover"
                        x-cloak
                        x-transition.opacity
                        src="${getFullURL(arg.event.extendedProps.thumbnail)}"
                    >
            </figure>`
			:
		''}
		<div class="opacity-65 mb-3 text-2xs/[1.4em] relative z-1">
			${arg.event.extendedProps.content}
		</div>

        ${arg.event.extendedProps.categories
            ? `<p class="opacity-55 mb-0 text-3xs relative z-1">
            {{ __('Categories:') }}
            ${(arg.event.extendedProps.categories).join(', ')}
            </p>`
            : ''
        }

        ${arg.event.extendedProps.tags
            ? `<p class="opacity-55 mb-0 text-3xs relative z-1">
            {{ __('Tags:') }}
            ${(arg.event.extendedProps.tags).join(', ')}
            </p>`
            : ''
        }
	</div>
</div>`
                            }
                        },

                        eventMouseEnter: event => {
                            const eventEl = event.el;
                            const eventPopup = event.el.querySelector('.lqd-event-card');
                            const scroller = eventEl.closest('.fc-scroller');

                            computePosition(eventEl, eventPopup, {
                                placement: 'top',
                                strategy: 'fixed',
                                middleware: [
                                    flip({
                                        boundary: scroller,
                                    }),
                                    shift({
                                        boundary: scroller,
                                        padding: 5
                                    }),
                                    offset(5)
                                ],
                            }).then(({
                                x,
                                y
                            }) => {
                                Object.assign(eventPopup.style, {
                                    inset: 'auto',
                                    transform: 'none',
                                    left: `${x}px`,
                                    top: `${y}px`,
                                });
                            });
                        },

                        eventClick: function(info) {
                            window.open('{{ route('dashboard.user.titan-nova.agent.tasks.edit', ['task' => '__POST__']) }}'.replace('__POST__', info.event.id), '_blank');
                        },

                        windowResize: arg => {
                            this.calendar.changeView(this.calendarInitialView)
                            this.calendar.setOption('headerToolbar', this.calendarHeaderToolbar)
                        }
                    });

                    this.calendar.render();
                },

                filterByAgent(event) {
                    const agentId = event?.detail?.agentId;

                    if (typeof agentId === 'undefined') {
                        return toastr.error('{{ __('Agent id is not provided') }}');
                    }

                    const existingFilterIndex = this.filters.agentIds.findIndex(id => id == agentId);

                    if (existingFilterIndex !== -1) {
                        this.filters.agentIds.splice(existingFilterIndex, 1);
                    } else {
                        this.filters.agentIds.push(agentId);
                    }

                    this.applyFilters();
                },

                applyFilters() {
                    const hasFilters = Object.entries(this.filters).some(([key, value]) => Array.isArray(value) ? value.length : !!value);

                    if (!hasFilters) {
                        this.calendar.getEvents().forEach(event => {
                            const eventClassNames = event.classNames;
                            event.setProp('classNames', eventClassNames.filter(cl => cl !== 'lqd-fc-filter-out'))
                        });

                        return;
                    };

                    this.calendar.getEvents().forEach(event => {
                        const agentId = event.extendedProps.agentId;
                        const eventClassNames = event.classNames;
                        let hasActiveFilter = false;

                        // check for other filters here
                        if (this.filters.agentIds.includes(agentId)) {
                            hasActiveFilter = true;
                        }

                        if (!hasActiveFilter) {
                            if (!eventClassNames.includes('lqd-fc-filter-out')) {
                                event.setProp('classNames', [...eventClassNames, 'lqd-fc-filter-out'])
                            }
                        } else {
                            event.setProp('classNames', eventClassNames.filter(cl => cl !== 'lqd-fc-filter-out'))
                        }
                    })
                }
            }))
        })
    </script>
@endpush
