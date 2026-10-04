@php
    $task_modes = [
        'manual' => [
            'title' => __('Manual Task'),
            'description' => __('Nova schedules and prepares the task; a person completes it.'),
        ],
        'ai_assisted' => [
            'title' => __('AI-Assisted Task'),
            'description' => __('Nova prepares instructions, evidence requirements and recommendations for a person.'),
        ],
        'automated' => [
            'title' => __('Automated Task'),
            'description' => __('Nova may dispatch an approved Titan capability when the task becomes due.'),
        ],
        'approval_required' => [
            'title' => __('Approval-Required Task'),
            'description' => __('Nova creates and schedules the task but pauses before execution until approved.'),
        ],
        'external_workflow' => [
            'title' => __('External Workflow Task'),
            'description' => __('Nova hands the task to an authorised connected workflow or specialist agent.'),
        ],
    ];
@endphp

<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 2"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <h2 class="mb-9 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">@lang('Choose how the agent handles work')</span>
        @lang('Select the task modes')
    </h2>

    <div class="flex max-h-[300px] flex-wrap gap-2 overflow-x-hidden overflow-y-auto rounded-[20px] border px-5 py-7 text-center">
        @foreach ($task_modes as $key => $task_type)
            <label class="group relative flex min-h-[42px] items-center gap-1.5 rounded-full bg-foreground/5 px-4 py-2.5 text-xs text-heading-foreground has-[input:checked]:bg-primary/5 has-[input:checked]:text-primary">
                <input class="peer absolute inset-0 z-1 cursor-pointer opacity-0" type="checkbox" value="{{ $key }}" x-model="formData.task_modes">
                <span>{{ $task_type['title'] }}</span>
                <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 w-[clamp(269px,269px,85vw)] origin-bottom -translate-x-1/2 translate-y-1 scale-95 rounded-md border bg-background/95 px-3 py-2 text-center text-2xs font-medium text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm transition group-hover:translate-y-0 group-hover:scale-100 group-hover:opacity-100">{{ $task_type['description'] }}</span>
                <span class="inline-grid size-[22px] place-items-center rounded-full border border-foreground/15 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground">
                    <x-tabler-check class="hidden size-4 group-has-[input:checked]:flex" />
                </span>
            </label>
        @endforeach
    </div>

    <div class="mt-2">@include('titan-nova::create.step-error', ['step' => 4])</div>
    <x-button class="mt-5 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground" type="button" @click.prevent="nextStep()">
        @lang('Continue') <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
