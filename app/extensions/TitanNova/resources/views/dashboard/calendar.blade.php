@php
    use Illuminate\Support\Str;
@endphp

@push('css')
    <style>
        #titan-nova-calendar {
            --fc-border-color: hsl(0 0% 100% / 5%);
            --fc-neutral-bg-color: hsl(0 0% 100% / 5%);
        }

        #titan-nova-calendar .fc-toolbar-title {
            color: currentColor;
            font-size: 14px;
            font-weight: 500;
            font-family: var(--font-body);
        }

        #titan-nova-calendar .fc-header-toolbar .fc-button-group .fc-button {
            display: inline-grid;
            place-items: center;
            width: 20px;
            height: 20px;
            padding: 0;
            border-radius: 4px !important;
            color: currentColor;
        }

        #titan-nova-calendar .fc-header-toolbar .fc-button-group .fc-button:not(:disabled):hover {
            background-color: #fff;
            color: #000;
        }

        #titan-nova-calendar .fc-col-header-cell-cushion {
            padding: 17px 10px;
            font-size: 14px;
            font-weight: 600;
            color: currentColor;
        }

        #titan-nova-calendar .fc-daygrid-event-harness {
            padding: 4px 4px 1px;
        }

        #titan-nova-calendar .fc-daygrid-event {
            white-space: normal;
        }

        #titan-nova-calendar .fc-event {
            padding: 15px;
            background-color: hsl(0 0% 100% / 10%);
            transition: background 0.3s;
        }

        #titan-nova-calendar .lqd-event-title,
        #titan-nova-calendar .lqd-event-info {
            color: #fff;
        }

        #titan-nova-calendar .fc-event:hover {
            background-color: hsl(0 0% 100% / 20%);
        }
    </style>
@endpush

<div
    class="mb-10 overflow-hidden rounded-2xl bg-cover bg-top p-5 text-white md:p-8"
    style="background-image: url({{ asset('vendor/titan-nova/images/img-5.jpg') }})"
>
    <div
        class="min-h-[450px]"
        id="titan-nova-calendar"
        x-data="titanNovaDashboardCalendar"
    >
        {{-- Skeleton loader --}}
        <div class="mb-5 h-[1lh] w-full animate-pulse rounded-md bg-white/10"></div>
        <div class="grid grid-cols-7">
            @for ($i = 0; $i < 7; $i++)
                <div class="h-[calc(450px-1lh-1.25rem)] animate-pulse border border-e-0 border-white/5 first:rounded-s-lg last:rounded-e-lg last:border-e"></div>
            @endfor
        </div>
    </div>
</div>

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
            Alpine.data('titanNovaDashboardCalendar', () => ({
                calendar: null,

                init() {
                    this.initCalendar();
                },

                initCalendar() {
                    const floatingUi = window.FloatingUIDOM;
                    const {
                        computePosition,
                        flip,
                        offset,
                        shift,
                    } = floatingUi;

                    this.calendar = new FullCalendar.Calendar(this.$el, {
                        initialView: this.getCalendarView(),
                        height: '450px',
                        titleFormat: {
                            month: 'short',
                            day: 'numeric'
                        },
                        dayHeaderContent: arg => {
                            const weekday = arg.date.toLocaleDateString('en-US', {
                                weekday: 'short'
                            });
                            const day = arg.date.toLocaleDateString('en-US', {
                                day: 'numeric'
                            });
                            return {
                                html: `<span class="text-white/50 text-xs font-semibold">${weekday}</span> <span class="inline-grid place-items-center size-5 text-xs font-semibold rounded-full text-white ${arg.isToday ? 'bg-white/10' : ''}">${day}</span>`
                            };
                        },
                        headerToolbar: {
                            start: 'title prev,next',
                            center: '',
                            end: ''
                        },
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
                                        const channel = task.channel?.channel;
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
                            },
                            dayGridTwoDay: {
                                type: 'dayGridWeek',
                                duration: {
                                    days: 2
                                },
                            }
                        },
                        eventContent: arg => {
                            return {
                                html: `<div class="lqd-event-content">

	<p class="lqd-event-title text-[12px] leading-4 font-semibold mb-2">${arg.event.extendedProps.title}</p>

	<p class="lqd-event-info text-3xs leading-4 font-medium opacity-55 mb-0">${arg.event.extendedProps.time} <span class="text-xl align-text-top leading-[0] mx-1">.</span> ${arg.event.extendedProps.status}</p>

	<div class="lqd-event-card invisible fixed left-0 inset-auto bottom-full !z-[100] w-[min(400px,calc(100vw-60px))] !translate-x-0 translate-y-1 rounded-[10px] bg-background p-3.5 opacity-0 shadow-md shadow-black/5 transition before:absolute before:-inset-2.5">
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
		<p class="opacity-65 mb-3 text-2xs/[1.4em] relative z-1">
			${arg.event.extendedProps.content}
		</p>

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

                            computePosition(eventEl, eventPopup, {
                                placement: 'top',
                                strategy: 'fixed',
                                middleware: [
                                    flip({
                                        boundary: this.$el.querySelector('.fc-scroller')
                                    }),
                                    shift({
                                        boundary: this.$el,
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
                            this.calendar.changeView(this.getCalendarView())
                        }
                    });

                    this.calendar.render();
                },

                getCalendarView() {
                    return window.innerWidth <= 480 ? 'dayGridTwoDay' : window.innerWidth <= 869 ? 'dayGridThreeDay' : 'dayGridWeek'
                },
            }))
        });
    </script>
@endpush
