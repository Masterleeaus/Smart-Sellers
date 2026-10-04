@php
    $total_steps = 7;

    $days_of_week = collect(range(0, 6))
        ->mapWithKeys(function ($index) {
            $day = \Carbon\Carbon::now()->startOfWeek()->addDays($index);

            return [
                $index => [
                    'number' => $day->dayOfWeekIso,
                    'name' => $day->englishDayOfWeek,
                    'label' => $day->translatedFormat('l'),
                    'shorthand' => $day->translatedFormat('D'),
                ],
            ];
        })
        ->toArray();

    $time_slots = [
        [
            'key' => 'morning',
            'label' => 'Morning',
            'start' => '08:00',
            'end' => '12:00',
        ],
        [
            'key' => 'noon',
            'label' => 'Noon',
            'start' => '12:00',
            'end' => '16:00',
        ],
        [
            'key' => 'evening',
            'label' => 'Evening',
            'start' => '17:00',
            'end' => '22:00',
        ],
    ];
@endphp

@extends('panel.layout.app', ['disable_tblr' => true, 'disable_titlebar' => true])
@section('title', __('Create Titan Nova Agent'))
@section('titlebar_subtitle', __('Create, schedule and coordinate tasks with an AI-powered agent'))

@push('css')
    <style>
        @media (min-width: 992px) {

            .lqd-navbar-expander,
            .lqd-navbar,
            .lqd-header,
            .lqd-page-footer {
                display: none !important;
            }

            .lqd-page-wrapper {
                padding-inline: 0 !important;
            }
        }
    </style>
@endpush

@section('content')
    <div
        class="flex min-h-screen flex-col items-center justify-center py-10 lg:py-28"
        x-data="agentWizard"
        style="--current-step: 0; --total-steps: {{ $total_steps }}"
        :style="{ '--current-step': currentStep, '--total-steps': totalSteps }"
    >
        <div class="absolute inset-x-0 top-[--header-height] z-5 flex items-center p-4 lg:fixed lg:top-0">
            <div class="flex basis-1/3">
                <div
                    x-cloak
                    x-show="currentStep > 0"
                    x-transition
                >
                    <x-button
                        variant="link"
                        @click.prevent="prevStep()"
                    >
                        <x-tabler-arrow-left class="size-4" />
                        @lang('Back')
                    </x-button>
                </div>
            </div>

            <div class="flex basis-1/3 justify-center">
                <div class="relative h-[5px] w-40 overflow-hidden rounded-full bg-foreground/5 backdrop-blur-lg">
                    <div
                        class="absolute inset-y-0 start-0 w-full origin-left scale-x-[calc(var(--current-step)/var(--total-steps))] rounded-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to transition rtl:origin-right">
                    </div>
                </div>
            </div>

            <div class="flex basis-1/3 items-center justify-end gap-9">
                <p
                    class="m-0 text-xs font-medium"
                    x-show="currentStep > 0 && currentStep <= 7"
                    x-transition
                    x-cloak
                >
                    @lang('Step')
                    <span x-text="Math.max(1,currentStep)">1</span>
                    @lang('of')
                    {{ $total_steps }}
                </p>
                <x-button
                    class="hidden size-9 place-items-center rounded-full bg-background text-foreground shadow-xs lg:inline-grid"
                    variant="link"
                    title="{{ __('Close') }}"
                    href="{{ route('dashboard.user.titan-nova.agent.index') }}"
                >
                    <x-tabler-x class="size-4" />
                </x-button>
            </div>
        </div>

        <div class="container px-0">
            <div class="mx-auto lg:max-w-[500px]">
                <form
                    class="grid w-full grid-cols-1 place-items-center"
                    @submit.prevent="submitForm"
                    @keydown.enter.prevent
                    novalidate
                >
                    @include('titan-nova::create.create-step-0')
                    @include('titan-nova::create.create-step-1')
                    @include('titan-nova::create.create-step-2')
                    @include('titan-nova::create.create-step-3')
                    @include('titan-nova::create.create-step-4')
                    @include('titan-nova::create.create-step-5')
                    @include('titan-nova::create.create-step-6', ['days_of_week' => $days_of_week])
                    @include('titan-nova::create.create-step-7', ['time_slots' => $time_slots])
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('agentWizard', () => ({
                totalSteps: {{ $total_steps }},
                currentStep: 0,
                submitting: false,
                topic_generating: false,
                task_type_options: [],
                selected_task_types: [],
                has_emoji: false,
                has_image: false,
                has_web_search: false,
                has_keyword_search: false,
                stepsErrors: new Map(),
                daysOfWeek: @json($days_of_week),
                timeSlots: @json($time_slots),

                stepTitles: [
                    '{{ __('Agent Purpose') }}',
                    '{{ __('Task Types') }}',
                    '{{ __('Task Modes') }}',
                    '{{ __('Task Settings') }}',
                    '{{ __('Settings') }}',
                    '{{ __('Schedule') }}',
                    '{{ __('Review') }}'
                ],

                formData: {
                    name: '',
                    topic: '',
                    task_type_options: [],
                    selected_task_types: [],
                    task_modes: [],
                    language: '{{ array_key_exists('en-US', config('openai_languages', [])) ? 'en-US' : array_key_first(config('openai_languages', [])) ?? 'en-US' }}',
                    plan_type: 'weekly',
                    priority: 'normal',
                    has_emoji: false,
                    has_image: false,
                    has_web_search: false,
                    has_keyword_search: false,
                    default_task_duration_minutes: '30',
                    frequency: 'weekly',
                    schedule_days: [],
                    schedule_times: [],
                    daily_task_count: 1
                },

                init() {
                    this.buildStepsErrors();

                    // Set defaults
                    const daysSource = Array.isArray(this.daysOfWeek) ? this.daysOfWeek : Object.values(this.daysOfWeek);
                    this.formData.schedule_days = daysSource
                        .slice(0, 5)
                        .map(day => {
                            if (day && typeof day === 'object' && 'number' in day) {
                                return String(day.number);
                            }

                            const numericValue = Number(day);

                            return Number.isNaN(numericValue) ? null : String(numericValue);
                        })
                        .filter(day => day !== null);
                    this.formData.schedule_times = [this.timeSlots.find(t => t.key === 'noon')];
                },

                buildStepsErrors() {
                    for (let i = 0; i <= this.totalSteps; i++) {
                        this.stepsErrors.set(i, []);
                    }
                },

                emptyStepsErrors() {
                    this.buildStepsErrors();
                },

                checkCurrentStepErrors() {
                    this.stepsErrors.set(this.currentStep, []);

                    switch (this.currentStep) {
                        case 1:
                            const hasTopic = this.formData.selected_task_types.length > 0;
                            if (!hasTopic) {
                                // this.stepsErrors.get(this.currentStep).push('{{ __('Please select at least one task type.') }}');
                                toastr.error('{{ __('Please select at least one task type.') }}');
                            }
                            break;
                        case 2:
                            if (!this.formData.task_modes.length) {
                                // this.stepsErrors.get(this.currentStep).push('{{ __('Please select at least one task mode.') }}');
                                toastr.error('{{ __('Please select at least one task mode.') }}');
                            }
                            break;
                        case 4:
                            if (!this.formData.schedule_days.length || !this.formData.schedule_times.length) {
                                // this.stepsErrors.get(this.currentStep).push('{{ __('Please set a schedule day and time.') }}');
                                toastr.error('{{ __('Please set a schedule day and time.') }}');
                            }
                            break;
                    }
                },

                canProceed(errorsArray) {
                    return !this.stepsErrors.get(this.currentStep).length
                },

                async nextStep(errorsArray) {
                    this.checkCurrentStepErrors();
                    const hasTopic = this.formData.selected_task_types.length > 0;
                    const hasTaskType = this.formData.task_modes.length > 0;
                    const hasSchedule = this.formData.schedule_days.length > 0 && this.formData.schedule_times.length > 0;

                    if (!hasTopic && this.currentStep === 1) {
                        return;
                    }

                    if (!hasTaskType && this.currentStep === 2) {
                        return;
                    }

                    if (!hasSchedule && this.currentStep === 4) {
                        return;
                    }

                    if (this.canProceed() && this.currentStep < 6) {
                        this.currentStep++;
                    }
                },

                prevStep() {
                    if (this.currentStep > 0) {
                        this.currentStep--;
                    }
                },

                 async generateTaskTypes() {
                    const topic = (this.formData.topic || '').trim();
                    this.topic_generating = true;

                    try {
                        const response = await fetch('{{ route('dashboard.user.titan-nova.agent.generate-topics') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                topic
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.formData.task_type_options = data.topics.topic_data;
                        } else {
                            alert('Failed to generate task types. Please try again.');
                        }
                    } catch (error) {
                        console.error('Task-type creation error:', error);
                        alert('Failed to generate task types. Please try again.');
                    } finally {
                        this.topic_generating = false;
                    }

                    return this.formData.task_type_options;
                },

                hasTimeSlot(slot) {
                    return this.formData.schedule_times.some(t => t.key === slot);
                },

                toggleTimeSlot(slot) {
                    const index = this.formData.schedule_times.findIndex(t => t.key === slot);

                    if (index > -1) {
                        this.formData.schedule_times.splice(index, 1);
                        this.ensureDailyTaskCountMatchesSlots();

                        return;
                    }

                    const maxSlots = this.maxSelectableSlots();
                    if (this.formData.schedule_times.length >= maxSlots) {
                        this.notifyTimeSlotLimit(maxSlots);

                        return;
                    }

                    const match = this.timeSlots.find(t => t.key === slot);
                    if (!match) {
                        return;
                    }

                    this.formData.schedule_times.push(match);
                    this.ensureDailyTaskCountMatchesSlots();
                },

                handleDailyTaskCountChange() {
                    let count = Number(this.formData.daily_task_count) || 1;
                    count = Math.max(1, Math.min(10, Math.floor(count)));

                    const currentSlots = this.formData.schedule_times.length;
                    const maxSlots = this.maxSelectableSlots(count);

                    if (currentSlots > maxSlots) {
                        this.formData.schedule_times = this.formData.schedule_times.slice(0, maxSlots);
                    }

                    if (count < this.formData.schedule_times.length) {
                        count = this.formData.schedule_times.length;
                    }

                    this.formData.daily_task_count = count;
                },

                ensureDailyTaskCountMatchesSlots() {
                    const slotCount = this.formData.schedule_times.length;

                    if (slotCount === 0) {
                        return;
                    }

                    if (!this.formData.daily_task_count || this.formData.daily_task_count < slotCount) {
                        this.formData.daily_task_count = Math.min(10, slotCount);
                    }
                },

                maxSelectableSlots(count = null) {
                    const value = Number(count ?? this.formData.daily_task_count) || 1;

                    if (value <= 1) {
                        return 1;
                    }

                    if (value === 2) {
                        return 2;
                    }

                    return 3;
                },

                notifyTimeSlotLimit(limit) {
                    if (window?.toastr) {
                        const message = limit === 1 ?
                            '{{ __('Select only one time slot when scheduling one task per day.') }}' :
                            '{{ __('Increase the number of tasks per day before selecting more time slots.') }}';
                        window.toastr.info(message);
                    }
                },

                async submitForm() {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    if (!this.formData.name.trim()) {
                        toastr.error('{{ __('Please fill the agent name.') }}');
                        return;
                    }

                    this.currentStep = 7;
                    this.submitting = true;

                    const payload = {};

                    for (const [key, value] of Object.entries(this.formData)) {
                        if (key === 'selected_task_types' && Array.isArray(value)) {
                            payload.selected_task_types = value;
                        } else if (key === 'task_type_options' && Array.isArray(value)) {
                            payload.task_type_options = value;
                        } else if (key === 'task_modes' && Array.isArray(value)) {
                            payload.task_modes = value;
                        } else if (key === 'schedule_days' && Array.isArray(value)) {
                            payload.schedule_days = value;
                        } else if (key === 'schedule_times' && Array.isArray(value)) {
                            payload.schedule_times = value;
                        } else if (key === 'has_emoji' || key === 'has_image' || key === 'has_keyword_search' || key === 'has_web_search') {
                            payload[key] = value ? 1 : 0;
                        } else if (value !== null && value !== undefined) {
                            payload[key] = value;
                        }
                    }

                    let backendErrorsHandled = false;

                    try {
                        const response = await fetch('{{ route('dashboard.user.titan-nova.agent.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json().catch(() => ({}));

                        if (!response.ok || data.success === false) {
                            backendErrorsHandled = this.handleBackendErrors(data.errors || null);
                            const backendMessage = data.message || this.extractFirstError(data.errors) || 'Failed to create agent.';
                            throw new Error(backendMessage);
                        }

                        this.emptyStepsErrors();
                        this.currentStep = 7;
                    } catch (error) {
                        toastr.error(error.message || '{{ __('Failed to create agent. Please try again.') }}')
                        if (!backendErrorsHandled) {
                            this.currentStep = 6;
                        }
                    } finally {
                        this.submitting = false;
                    }
                },

                handleBackendErrors(errors) {
                    if (!errors || typeof errors !== 'object') {
                        return false;
                    }

                    this.emptyStepsErrors();

                    const fieldStepMap = new Map([
                        ['topic', 1],
                        ['task_type_options', 1],
                        ['task_modes', 4],
                        ['plan_type', 5],
                        ['priority', 5],
                        ['language', 5],
                        ['has_image', 5],
                        ['schedule_days', 6],
                        ['schedule_times', 6],
                        ['daily_task_count', 6],
                        ['name', 7],
                    ]);

                    let firstStep = null;
                    let hasErrors = false;

                    Object.entries(errors).forEach(([field, messages]) => {
                        const normalizedField = field.split('.')[0];
                        const step = fieldStepMap.get(normalizedField) ?? 7;
                        const normalizedMessages = Array.isArray(messages) ? messages : [messages];
                        let added = false;

                        normalizedMessages.forEach(message => {
                            if (!message) {
                                return;
                            }

                            this.stepsErrors.get(step).push(message);
                            hasErrors = true;
                            added = true;
                        });

                        if (added) {
                            if (firstStep === null) {
                                firstStep = step;
                            } else {
                                firstStep = Math.min(firstStep, step);
                            }
                        }
                    });

                    if (hasErrors && firstStep !== null) {
                        this.currentStep = firstStep;
                    }

                    return hasErrors;
                },

                extractFirstError(errors) {
                    if (!errors || typeof errors !== 'object') {
                        return null;
                    }

                    for (const messages of Object.values(errors)) {
                        if (Array.isArray(messages) && messages.length) {
                            return messages[0];
                        }

                        if (typeof messages === 'string' && messages.trim().length) {
                            return messages;
                        }
                    }

                    return null;
                }
            }))
        })
    </script>
@endpush
