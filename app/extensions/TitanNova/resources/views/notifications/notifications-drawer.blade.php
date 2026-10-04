@php
    $button_classname = @twMerge('lqd-titan-nova-notification-trigger size-6 !p-0 max-lg:hidden max-lg:size-10 flex items-center justify-center hover:bg-transparent max-lg:rounded-full max-lg:border max-lg:dark:bg-white/[3%] relative', $attributes->get('class'));
@endphp

<div
    class="inline-flex items-center"
    x-data="titanNovaNotifications"
>
    <x-button
        class="{{ $button_classname }}"
        size="none"
        href="#"
        title="{{ __('Titan Nova Notifications') }}"
        variant="link"
        @click="toggleSidedrawer(true)"
    >
        <span
            class="absolute -end-1.5 -top-1.5 inline-grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-3xs font-medium leading-none text-white"
            x-text="unreadsCount >= 100 ? '99+' : unreadsCount"
            x-show="unreadsCount > 0"
            x-transition
            x-cloak
        >
            0
        </span>
        <x-tabler-trending-up
            class="size-5"
            stroke-width="1.5"
        />
    </x-button>

    <x-sidedrawer id="titan-nova-notifications-sidedrawer">

        <template x-if="notifications.length">
            <div class="py-5">
                <div class="mb-5 flex items-center justify-between gap-2 px-6">
                    <h3 class="m-0">
                        @lang('Notifications')
                    </h3>

                    <x-button
                        variant="link"
                        href="#"
                        @click.prevent="openClearAllModal"
                    >
                        @lang('Clear All')
                    </x-button>
                </div>
                <template
                    x-for="notification in notifications"
                    :key="notification.id"
                >
                    <div class="relative flex gap-3 px-6 py-2 transition hover:bg-primary/5">
                        <figure class="inline-grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-foreground/5 text-foreground">
                            <template x-if="notification.agent_image">
                                <img
                                    class="size-full object-cover object-center"
                                    :src="notification.agent_image"
                                    :alt="notification.agent_name"
                                >
                            </template>
                            <template x-if="!notification.agent_image">
                                <svg
                                    class="-mb-1.5 size-6 opacity-70"
                                    width="53"
                                    height="60"
                                    viewBox="0 0 53 60"
                                    fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M26.4191 59.3442C17.0361 59.3442 8.44287 56.9236 1.81433 51.2617C0.199606 49.8832 -0.398015 47.2257 0.26792 44.7404C2.35094 36.9664 6.7234 35.2167 16.2625 35.2167H36.0775C45.619 35.2167 50.3425 36.4263 52.5702 44.7404C53.34 47.6133 52.6432 49.8808 51.0262 51.2597C44.3998 56.9236 35.8043 59.3442 26.4191 59.3442Z"
                                    />
                                    <path
                                        d="M26.4171 0C34.8842 0 41.7449 6.87005 41.7449 15.3416C41.7449 23.8152 34.8842 30.6833 26.4171 30.6833C17.9541 30.6833 11.0933 23.8152 11.0933 15.3416C11.0933 6.87005 17.9541 0 26.4171 0Z"
                                    />
                                </svg>
                            </template>
                        </figure>
                        <div class="grow">
                            <p
                                class="mb-0.5 text-2xs font-medium"
                                x-text="notification.agent_name"
                            ></p>
                            <p
                                class="mb-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary"
                                x-text="notification.type_label"
                                x-show="notification.type_label"
                            ></p>
                            <p
                                class="mb-1.5 text-balance text-2xs/[1.38em] opacity-80"
                                x-text="notification.content"
                            ></p>
                            <div class="flex items-center gap-3">
                                <a
                                    class="relative z-2 text-[12px] underline underline-offset-2 transition hover:no-underline"
                                    :href="notification.link"
                                    @click.prevent="openNotification(notification)"
                                    x-show="notification.link"
                                >
                                    @lang('Learn More')
                                </a>
                                <a
                                    class="relative z-2 text-[12px] opacity-50 transition hover:opacity-100"
                                    href="#"
                                    @click.prevent="clearNotification(notification.id)"
                                >
                                    @lang('Clear')
                                </a>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-1.5">
                            <p
                                class="m-0 text-[12px] opacity-80"
                                x-text="notification.relative_time"
                            ></p>
                            <span
                                class="inline-flex size-2 shrink-0 rounded-full bg-primary"
                                x-show="notification.is_unread"
                            ></span>
                        </div>

                        <a
                            class="absolute inset-0 z-1"
                            href="#"
                            @click.prevent="openNotification(notification)"
                            x-show="notification.link"
                        ></a>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="!notifications.length">
            <div class="flex h-full w-full flex-col items-center justify-center px-6 py-5 text-center">
                <div class="mb-4 inline-grid size-24 place-items-center rounded-full bg-foreground/5 text-heading-foreground">
                    <svg
                        class="size-10"
                        xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path
                            stroke="none"
                            d="M0 0h24v24H0z"
                            fill="none"
                        />
                        <path
                            d="M14.235 19c.865 0 1.322 1.024 .745 1.668a3.992 3.992 0 0 1 -2.98 1.332a3.992 3.992 0 0 1 -2.98 -1.332c-.552 -.616 -.158 -1.579 .634 -1.661l.11 -.006h4.471z"
                        />
                        <path
                            d="M12 2c1.358 0 2.506 .903 2.875 2.141l.046 .171l.008 .043a8.013 8.013 0 0 1 4.024 6.069l.028 .287l.019 .289v2.931l.021 .136a3 3 0 0 0 1.143 1.847l.167 .117l.162 .099c.86 .487 .56 1.766 -.377 1.864l-.116 .006h-16c-1.028 0 -1.387 -1.364 -.493 -1.87a3 3 0 0 0 1.472 -2.063l.021 -.143l.001 -2.97a8 8 0 0 1 3.821 -6.454l.248 -.146l.01 -.043a3.003 3.003 0 0 1 2.562 -2.29l.182 -.017l.176 -.004zm2 8h-4l-.117 .007a1 1 0 0 0 .117 1.993h4l.117 -.007a1 1 0 0 0 -.117 -1.993z"
                        />
                    </svg>
                </div>

                <h3 class="mb-1.5 text-[18px]">
                    @lang('No Notifications')
                </h3>
                <p class="text-xs font-medium opacity-60">
                    @lang('Stay tuned. We will notify you soon.')
                </p>
            </div>
        </template>
    </x-sidedrawer>

    <div
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/10 p-4"
        x-show="isClearAllModalOpen"
        x-transition.opacity
        x-cloak
        @keydown.escape.window="closeClearAllModal"
        @click.self="closeClearAllModal"
    >
        <div class="w-full max-w-sm rounded-2xl bg-card-background p-6 text-card-foreground shadow-2xl">
            <h3 class="mb-4 text-lg font-semibold">
                @lang('Clear all notifications?')
            </h3>
            <p class="mb-0 text-xs opacity-60">
                @lang('This will permanently remove every notification. You will not be able to recover them.')
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <x-button
                    class="grow sm:grow-0"
                    variant="outline"
                    size="sm"
                    @click="closeClearAllModal"
                >
                    @lang('Cancel')
                </x-button>
                <x-button
                    class="grow sm:grow-0"
                    variant="destructive"
                    size="sm"
                    x-bind:disabled="isClearingAll"
                    @click="clearAllNotifications"
                >
                    <span x-show="!isClearingAll">
                        @lang('Clear All')
                    </span>
                    <span
                        class="inline-flex items-center gap-1"
                        x-show="isClearingAll"
                    >
                        <x-tabler-loader-2 class="size-4 animate-spin" />
                        @lang('Clearing')
                    </span>
                </x-button>
            </div>
        </div>
    </div>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('titanNovaNotifications', () => ({
                notifications: [],
                unreadsCount: 0,
                notificationsSidedrawer: null,
                scrollContainer: null,
                perPage: 6,
                nextPage: 1,
                hasMore: true,
                isFetching: false,
                pollInterval: null,
                pollDelay: 60000,
                openedForFirstTime: false,
                isClearAllModalOpen: false,
                isClearingAll: false,
                endpoints: {
                    list: '{{ route('dashboard.user.titan-nova.agent.analyses.index') }}',
                    base: '{{ url('dashboard/user/titan-nova/agent/analyses') }}',
                    clearAll: '{{ route('dashboard.user.titan-nova.agent.analyses.clear-all') }}',
                },

                async init() {
                    this.notificationsSidedrawer = document.querySelector('#titan-nova-notifications-sidedrawer');
                    this.scrollContainer = this.notificationsSidedrawer?.querySelector('.lqd-sidedrawer-content');

                    if (this.scrollContainer) {
                        this.scrollContainer.addEventListener('scroll', this.handleScroll.bind(this), {
                            passive: true
                        });
                    }

                    await this.fetchNotifications();

                    this.pollInterval = setInterval(() => {
                        !this.isFetching && this.fetchNotifications();
                    }, this.pollDelay);
                },

                toggleSidedrawer(show) {
                    const sidedrawerData = this.notificationsSidedrawer && Alpine.$data(this.notificationsSidedrawer);

                    if (sidedrawerData.sidedrawerOpen == null) {
                        return console.error('{{ __('Could not find the notifications sidedrawer') }}')
                    }

                    sidedrawerData.sidedrawerOpen = show;

                    if (!this.openedForFirstTime) {
                        this.openedForFirstTime = true;
                        this.handleScroll();
                    }
                },

                handleScroll(event) {
                    if (!this.scrollContainer || this.isFetching || !this.hasMore) {
                        return;
                    }

                    const nearBottom = this.scrollContainer.scrollTop + this.scrollContainer.clientHeight >= this.scrollContainer.scrollHeight - 120;

                    if (nearBottom) {
                        this.fetchNotifications({
                            append: true
                        });
                    }
                },

                async fetchNotifications({
                    append = false
                } = {}) {
                    if (this.isFetching || (append && !this.hasMore)) {
                        return;
                    }

                    this.isFetching = true;

                    try {
                        const page = append ? this.nextPage : 1;

                        if (!append) {
                            this.nextPage = 1;
                            this.hasMore = true;
                        }

                        const url = new URL(this.endpoints.list, window.location.origin);
                        url.searchParams.set('per_page', this.perPage);
                        url.searchParams.set('page', page);

                        const res = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        this.isFetching = false;

                        const data = await res.json();

                        if (!res.ok) {
                            throw new Error(data.message || '{{ __('Notifications could not be fetched.') }}');
                        }

                        const items = Array.isArray(data.data) ? data.data : [];
                        const transformed = items.map(item => this.transformNotification(item));

                        this.notifications = append ? [...this.notifications, ...transformed] :
                            transformed;

                        const unreadFromMeta = data.meta?.unread_count;
                        this.unreadsCount = typeof unreadFromMeta === 'number' ?
                            unreadFromMeta :
                            this.notifications.filter(notif => notif.is_unread).length;

                        const currentPage = data.meta?.current_page ?? page;
                        const lastPage = data.meta?.last_page ?? currentPage;

                        this.hasMore = currentPage < lastPage;
                        this.nextPage = this.hasMore ? currentPage + 1 : currentPage;

                        this.handleScroll();
                    } catch (error) {
                        this.isFetching = false;
                        console.error(error);
                    }
                },

                transformNotification(item) {
                    return {
                        id: item.id,
                        agent_name: item.agent_name || '{{ __('Agent') }}',
                        agent_image: item.agent_image || null,
                        type_label: item.type_label || '',
                        instructions: item.summary || item.summary || '',
                        link: item.link || null,
                        created_at: item.created_at,
                        relative_time: this.formatRelativeTime(item.created_at),
                        is_unread: Boolean(item.is_unread),
                        route: item.route || null
                    };
                },

                formatRelativeTime(timestamp) {
                    if (!timestamp) {
                        return '';
                    }

                    const date = new Date(timestamp);
                    if (Number.isNaN(date.getTime())) {
                        return '';
                    }

                    const diff = date.getTime() - Date.now();
                    const divisions = [{
                            amount: 60,
                            unit: 'seconds'
                        },
                        {
                            amount: 60,
                            unit: 'minutes'
                        },
                        {
                            amount: 24,
                            unit: 'hours'
                        },
                        {
                            amount: 7,
                            unit: 'days'
                        },
                        {
                            amount: 4.34524,
                            unit: 'weeks'
                        },
                        {
                            amount: 12,
                            unit: 'months'
                        },
                        {
                            amount: Number.POSITIVE_INFINITY,
                            unit: 'years'
                        },
                    ];

                    let duration = diff / 1000;
                    for (const division of divisions) {
                        if (Math.abs(duration) < division.amount) {
                            const rtf = new Intl.RelativeTimeFormat(document.documentElement.lang || 'en', {
                                numeric: 'auto',
                            });

                            return rtf.format(Math.round(duration), division.unit.replace(/s$/, ''));
                        }

                        duration /= division.amount;
                    }

                    return '';
                },

                async clearNotification(notificationId) {
                    const notification = this.notifications.find(notif => notif.id === notificationId);

                    try {
                        await this.deleteNotification(notificationId);
                        this.notifications = this.notifications.filter(notif => notif.id !== notificationId);
                        if (notification?.is_unread) {
                            this.unreadsCount = Math.max(0, this.unreadsCount - 1);
                        }
                    } catch (error) {
                        toastr.error(error.message || '{{ __('Could not clear the notification') }}');
                    }
                },

                async deleteNotification(notificationId) {
                    const res = await fetch(`${this.endpoints.base}/${notificationId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) {
                        const data = await res.json().catch(() => ({}));

                        throw new Error(data.message || '{{ __('Could not clear the notification.') }}');
                    }
                },

                async markNotificationAsRead(notificationId) {
                    const res = await fetch(`${this.endpoints.base}/${notificationId}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    if (!res.ok) {
                        return toastr.error(data.message || '{{ __('Could not clear the notification.') }}');
                    }

                    this.notifications = this.notifications.map(notif => notif.id === notificationId ? {
                            ...notif,
                            is_unread: false
                        } :
                        notif
                    );
                },

                async openNotification(notification) {
                    if (!notification.link) {
                        return;
                    }

                    try {
                        await this.markNotificationAsRead(notification.id);
                    } catch (error) {
                        console.error(error);
                    }

                    window.location.href = notification.link;
                },

                openClearAllModal() {
                    this.isClearAllModalOpen = true;
                },

                closeClearAllModal() {
                    if (!this.isClearingAll) {
                        this.isClearAllModalOpen = false;
                    }
                },

                async clearAllNotifications() {
                    if (this.isClearingAll) {
                        return;
                    }

                    this.isClearingAll = true;

                    try {
                        const res = await fetch(this.endpoints.clearAll, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();

                        if (!res.ok) {
                            throw new Error(data.message || '{{ __('Could not clear notifications.') }}');
                        }

                        this.notifications = [];
                        this.unreadsCount = 0;
                        this.hasMore = false;
                        this.nextPage = 1;
                        this.closeClearAllModal();
                    } catch (error) {
                        toastr.error(error.message || '{{ __('Could not clear notifications.') }}');
                    } finally {
                        this.isClearingAll = false;
                    }
                }
            }))
        })
    </script>
@endPushOnce
