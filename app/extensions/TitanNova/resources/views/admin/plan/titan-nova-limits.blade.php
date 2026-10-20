<div class="mt-10 rounded-2xl border border-border bg-card p-6">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                {{ __('Titan Nova Limits') }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ __('Define how many agents and monthly tasks are included in this plan. Set -1 for unlimited access and 0 to disable the feature entirely.') }}
            </p>
        </div>
    </div>

    <div class="row gap-y-5">
        <div class="col-12 col-md-6">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Agent Limit') }}"
                    tooltip="{{ __('Maximum number of Titan Nova agents a customer can create') }}"
                    error="plan.titan-nova_limits.agents"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.titan-nova_limits.agents"
                        placeholder="{{ __('e.g. 5 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Monthly Task Limit') }}"
                    tooltip="{{ __('Maximum number of tasks created per month across all agents') }}"
                    error="plan.titan-nova_limits.monthly_tasks"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.titan-nova_limits.monthly_tasks"
                        placeholder="{{ __('e.g. 120 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>
    </div>
</div>
