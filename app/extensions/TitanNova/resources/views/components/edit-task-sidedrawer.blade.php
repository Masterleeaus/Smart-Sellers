<x-sidedrawer id="titan-nova-sidedrawer">
    <template x-if="editingTask">
        <div class="max-h-[calc(100vh-4rem)] min-h-full overflow-y-auto">
            <div class="flex items-center justify-between gap-1 border-b px-6 py-5">
                <div>
                    <span class="block text-2xs font-medium uppercase tracking-wide text-foreground/50" x-text="editingTask.task_type || '{{ __('Task') }}'"></span>
                    <span class="block font-heading text-[24px] font-semibold leading-tight" x-text="editingTask.title"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button class="inline-grid size-8 place-items-center rounded-full hover:bg-foreground/5" @click.prevent="openEditSidedrawer({url: editingTaskPagination.prevPageUrl})" :disabled="!editingTaskPagination.prevPageUrl"><x-tabler-chevron-left class="size-4" /></button>
                    <button class="inline-grid size-8 place-items-center rounded-full hover:bg-foreground/5" @click.prevent="openEditSidedrawer({url: editingTaskPagination.nextPageUrl})" :disabled="!editingTaskPagination.nextPageUrl"><x-tabler-chevron-right class="size-4" /></button>
                </div>
            </div>

            <div class="space-y-4 px-6 py-5" :class="{ 'animate-pulse pointer-events-none': currentTasks.has('fetchingTask') }">
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-foreground/5 p-3"><p class="mb-1 opacity-50">@lang('Mode')</p><p class="mb-0 font-medium" x-text="editingTask.task_mode || 'manual'"></p></div>
                    <div class="rounded-xl bg-foreground/5 p-3"><p class="mb-1 opacity-50">@lang('Priority')</p><p class="mb-0 font-medium" x-text="editingTask.priority || 'normal'"></p></div>
                    <div class="rounded-xl bg-foreground/5 p-3"><p class="mb-1 opacity-50">@lang('Duration')</p><p class="mb-0 font-medium"><span x-text="editingTask.duration_minutes || 30"></span> @lang('minutes')</p></div>
                    <div class="rounded-xl bg-foreground/5 p-3"><p class="mb-1 opacity-50">@lang('Driver')</p><p class="mb-0 font-medium" x-text="editingTask.channel || 'manual'"></p></div>
                </div>

                <div class="relative select-none [&.active_.air-datepicker]:block" @click.outside="pickerOpen = false" :class="{ active: pickerOpen }" x-data="{ pickerOpen: false, timepicker: null, init() { this.timepicker = new AirDatepicker(this.$refs.timePicker, { locale: defaultLocale, selectedDates: [editingTask.scheduled_at ? new Date(editingTask.scheduled_at) : new Date()], timepicker: true, inline: true, dateFormat: 'yyyy-MM-dd', timeFormat: 'HH:mm', onSelect: ({ formattedDate }) => editingTask.scheduled_at = formattedDate }); } }">
                    <div class="rounded-[10px] bg-foreground/5 px-4 py-3">
                        <p class="mb-1 text-2xs font-medium opacity-50">@lang('Scheduled Date and Time')</p>
                        <input class="m-0 w-full border-none bg-transparent p-0" type="text" x-ref="timePicker" readonly @click="pickerOpen = !pickerOpen" />
                    </div>
                </div>

                <div class="rounded-[10px] bg-foreground/5 px-4 py-3">
                    <p class="mb-2 text-2xs font-medium opacity-50">@lang('Task Instructions')</p>
                    <textarea class="m-0 w-full border-none bg-transparent text-foreground focus-visible:outline-none" rows="10" x-model="editingTask.content"></textarea>
                </div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <x-button class="w-full" variant="outline" type="button" @click.prevent="rejectTask(editingTask.id)" ::disabled="currentTasks.has('updateTask') || currentTasks.has('rejectTask')">
                        <x-tabler-loader-2 class="size-4 animate-spin" x-cloak x-show="currentTasks.has('rejectTask')" />
                        @lang('Delete Task')
                    </x-button>
                    <x-button class="w-full" variant="primary" type="button" @click.prevent="updateTask(editingTask.id)" ::disabled="currentTasks.has('updateTask') || currentTasks.has('rejectTask')">
                        <x-tabler-loader-2 class="size-4 animate-spin" x-cloak x-show="currentTasks.has('updateTask')" />
                        @lang('Save Task')
                    </x-button>
                </div>
            </div>
        </div>
    </template>
</x-sidedrawer>
