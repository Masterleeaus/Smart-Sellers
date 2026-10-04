@php
    $banner_bg_light = asset('vendor/titan-nova/images/img-4.jpg');
    $banner_bg_dark = asset('vendor/titan-nova/images/img-4-dark.png');
@endphp

@push('css')
    <style>
        .titan-nova-banner {
            background-image: url({{ $banner_bg_light }});
        }

        .theme-dark .titan-nova-banner {
            background-image: url({{ $banner_bg_dark }});
        }
    </style>
@endpush

<div
    class="titan-nova-banner mb-10 flex flex-wrap items-center rounded-2xl bg-[url('{{ $banner_bg_light }}')] bg-cover bg-top px-5 dark:bg-white/[3%] dark:bg-[url('{{ $banner_bg_dark }}')] lg:px-12"
>
    <div class="w-full py-12 sm:py-24 lg:w-1/2">
        <h2 class="mb-12 block text-[30px] font-medium leading-[1.05em]">
            <span class="block text-[0.7em]">
                <span class="text-heading-foreground/50">
                    @lang('I’m :name', ['name' => 'Jotsy'])
                </span>
                👋
            </span>
            @lang('Here is today’s report.')
        </h2>

        <a
            class="group mb-12 inline-flex flex-wrap items-center gap-2 rounded-xl bg-background/25 p-2.5 text-xs text-[#8F4E34] shadow-[0_33px_55px_hsl(0_0%_0%/8%)] transition hover:-translate-y-1 hover:scale-105 hover:bg-background hover:text-heading-foreground dark:bg-white/5 dark:text-heading-foreground dark:hover:bg-heading-foreground dark:hover:text-heading-background sm:rounded-full"
            href="{{ route('dashboard.user.titan-nova.agent.tasks') }}"
        >
            <span
                x-show="!isCreationBusy"
                x-transition.opacity.scale
                x-cloak
                class="relative inline-grid size-[38px] place-items-center rounded-full before:absolute before:inset-0 before:rounded-full before:border before:border-current before:opacity-10"
            >
                <x-number-counter
                    class="text-base/none font-medium transition duration-200"
                    id="titan-nova-pending-tasks-counter"
                    value="{{ $pending_tasks_count }}"
                />
                <svg
                    class="absolute start-0 top-0 size-full -rotate-90 transition-all"
                    x-data="{
                        init() {
                                this.calculateDashoffset();
                                this.animateDashoffset();

                                this.$watch('scheduledTasksCount', () => {
                                    this.calculateDashoffset();
                                    this.animateDashoffset();
                                })
                            },
                            calculateDashoffset() {
                                const pendingCount = Math.max(this.$data.pendingTasksCount || 0, 0);
                                const scheduledCount = Math.max(this.$data.scheduledTasksCount || 0, 0);
                                const backlogCount = Math.max(pendingCount + scheduledCount, 1);

                                this.progressRatio = scheduledCount / backlogCount;
                                this.strokeDashoffset = 135 * this.progressRatio;
                            },
                            animateDashoffset() {
                                this.$el.animate([{ strokeDashoffset: this.strokeDashoffset }], { duration: 550, easing: 'ease', fill: 'both' })
                            }
                    }"
                    style="stroke-dasharray: 135; stroke-dashoffset: 0;"
                    width="43"
                    height="43"
                    viewBox="0 0 43 43"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <circle
                        cx="21.5"
                        cy="21.5"
                        r="21"
                    />
                </svg>
            </span>
            <span class="flex grow items-center justify-between gap-3">
                <span class="m-0 flex items-center gap-3">
                    <span
                        class="inline-grid place-items-center"
                        x-show="isCreationBusy"
                        x-transition.opacity.scale
                        x-cloak
                    >
                        <x-tabler-loader-2
                            class="size-5 animate-spin"
                        />
                    </span>
                    <template x-if="showReadyProgressText">
                        <span x-text="readyProgressText"></span>
                    </template>
                    <template x-if="!showReadyProgressText">
                        <span>{{ __('Task creation done.') }}</span>
                    </template>
                </span>
            </span>
        </a>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <x-number-counter
                    class="mb-3 text-[30px] font-medium leading-none text-heading-foreground"
                    id="titan-nova-scheduled-tasks-counter"
                    value="{{ $scheduled_tasks_count }}"
                    :options="['delay' => 150]"
                />
                <p class="m-0 text-2xs text-heading-foreground">
                    @lang('Scheduled Tasks')
                </p>
            </div>

            <div>
                <x-number-counter
                    class="mb-3 text-[30px] font-medium leading-none text-heading-foreground"
                    value="{{ $new_tasks }}"
                    :options="['delay' => 300]"
                />
                <p class="m-0 text-2xs text-heading-foreground">
                    @lang('New Tasks')
                </p>
            </div>
        </div>
    </div>

    <div class="flex w-full justify-center max-lg:order-first lg:w-1/2">
        <video
            class="max-w-[min(100%,650px)] mix-blend-darken dark:hidden"
            width="1200"
            height="1050"
            src="{{ asset('vendor/titan-nova/videos/banner-video.mp4') }}"
            autoplay
            playsinline
            muted
            loop
        ></video>
        <video
            class="hidden max-w-[min(100%,650px)] mix-blend-plus-lighter brightness-[0.85] contrast-[1.15] saturate-[1.1] dark:block"
            width="1200"
            height="1050"
            src="{{ asset('vendor/titan-nova/videos/banner-video-dark.mp4') }}"
            autoplay
            playsinline
            muted
            loop
        ></video>
    </div>
</div>
