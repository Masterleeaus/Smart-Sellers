@php
    use App\Extensions\TitanNova\System\Models\TitanNovaTask;
@endphp
@extends('panel.layout.settings', ['layout' => 'fullwidth', 'disable_tblr' => true])
@section('title', __('Edit Task'))
@section('titlebar_pretitle')
    <x-button class="text-inherit hover:text-foreground" variant="link" href="{{ route('dashboard.user.titan-nova.agent.tasks') }}">
        <x-tabler-chevron-left class="size-4" stroke-width="1.5" />
        {{ __('Back to tasks') }}
    </x-button>
@endsection
@section('titlebar_actions')
    <div class="flex gap-4 lg:justify-end">
        <x-button id="run_task_button" variant="success" type="button" onclick="titanNovaRunTask({{ $task->id }})">
            {{ __('Run Task') }}
        </x-button>
        <x-button id="task_button" type="submit" form="task_form">{{ __('Save') }}</x-button>
    </div>
@endsection

@section('settings')
    <form class="[&_.tox]:bg-input-background" id="task_form" onsubmit="return titanNovaTaskSave({{ $task->id }});" enctype="multipart/form-data">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <x-forms.input id="title" label="{{ __('Task Title') }}" name="title" size="lg" value="{{ $task->title }}" />
                <x-forms.input id="content" label="{{ __('Task Instructions') }}" name="content" type="textarea" size="lg">{{ $task->instructions }}</x-forms.input>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <x-forms.input id="task_type" label="{{ __('Task Type') }}" name="task_type" size="lg" value="{{ $task->task_type }}" />
                    <x-forms.input id="duration_minutes" label="{{ __('Duration in Minutes') }}" name="duration_minutes" type="number" min="5" max="1440" size="lg" value="{{ $task->duration_minutes ?: 30 }}" />
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <x-forms.input id="task_mode" label="{{ __('Task Mode') }}" name="task_mode" type="select" size="lg">
                        @foreach ([
                            TitanNovaTask::MODE_MANUAL => __('Manual'),
                            TitanNovaTask::MODE_AI_ASSISTED => __('AI Assisted'),
                            TitanNovaTask::MODE_AUTOMATED => __('Automated'),
                            TitanNovaTask::MODE_APPROVAL_REQUIRED => __('Approval Required'),
                            TitanNovaTask::MODE_EXTERNAL_WORKFLOW => __('External Workflow'),
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($task->task_mode === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.input>
                    <x-forms.input id="priority" label="{{ __('Priority') }}" name="priority" type="select" size="lg">
                        @foreach (['low' => __('Low'), 'normal' => __('Normal'), 'high' => __('High'), 'urgent' => __('Urgent')] as $value => $label)
                            <option value="{{ $value }}" @selected($task->priority === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.input>
                    <x-forms.input id="channel" label="{{ __('Execution Driver') }}" name="channel" type="select" size="lg">
                        <option value="manual" @selected($task->channel === 'manual')>{{ __('Manual') }}</option>
                        <option value="titan_action" @selected($task->channel === 'titan_action')>{{ __('Titan Capability') }}</option>
                    </x-forms.input>
                </div>
            </div>

            <div class="space-y-5">
                <x-forms.input id="status" label="{{ __('Task Status') }}" name="status" type="select" size="lg">
                    @foreach (TitanNovaTask::getStatusArray() as $status => $statusLabel)
                        <option value="{{ $status }}" @selected($task->status === $status)>{{ $statusLabel }}</option>
                    @endforeach
                </x-forms.input>
                <x-forms.input id="scheduled_at" label="{{ __('Scheduled Date and Time') }}" name="scheduled_at" type="datetime-local" size="lg" value="{{ optional($task->scheduled_at)->format('Y-m-d\\TH:i') }}" />
                <x-forms.input id="categories" type="select" name="categories" multiple size="none" label="{{ __('Workstreams') }}" add-new>
                    @foreach ($task->categories ?? [] as $category)<option value="{{ $category }}" selected>{{ $category }}</option>@endforeach
                </x-forms.input>
                <x-forms.input id="tags" type="select" name="tags" multiple size="none" label="{{ __('Labels') }}" add-new>
                    @foreach ($task->tags ?? [] as $tag)<option value="{{ $tag }}" selected>{{ $tag }}</option>@endforeach
                </x-forms.input>
                @if ($task->execution_result)
                    <div class="rounded-xl border p-4 text-xs">
                        <p class="mb-2 font-semibold">{{ __('Latest Execution Receipt') }}</p>
                        <pre class="whitespace-pre-wrap">{{ json_encode($task->execution_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif
            </div>
        </div>
    </form>
@endsection

@push('script')
<script src="{{ custom_theme_url('/assets/libs/tinymce/tinymce.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.tinymce) {
            tinymce.init({ selector: '#content', height: 520, menubar: false, statusbar: false, plugins: ['advlist', 'link', 'lists', 'code'], toolbar: 'bold italic underline | bullist numlist | link | code' });
        }
    });

    function titanNovaTaskSave(taskId) {
        const button = document.getElementById('task_button');
        button.disabled = true;
        const formData = new FormData();
        formData.append('id', taskId);
        formData.append('title', document.getElementById('title').value);
        formData.append('content', window.tinymce?.activeEditor?.getContent() ?? document.getElementById('content').value);
        ['status', 'scheduled_at', 'task_type', 'task_mode', 'priority', 'duration_minutes', 'channel'].forEach(key => formData.append(key, document.getElementById(key).value));
        formData.append('categories', $('#categories').val() ?? '');
        formData.append('tags', $('#tags').val() ?? '');

        fetch(`/dashboard/user/titan-nova/agent/tasks/${taskId}/update`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: formData,
        }).then(response => response.json()).then(data => {
            if (!data.success) throw new Error(data.message ?? '{{ __('Task could not be saved.') }}');
            toastr.success('{{ __('Task saved successfully.') }}');
        }).catch(error => toastr.error(error.message)).finally(() => button.disabled = false);
        return false;
    }

    function titanNovaRunTask(taskId) {
        const button = document.getElementById('run_task_button');
        button.disabled = true;
        fetch(`/dashboard/user/titan-nova/agent/tasks/${taskId}/run`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id: taskId }),
        }).then(response => response.json()).then(data => {
            if (!data.success) throw new Error(data.message ?? '{{ __('Task could not be run.') }}');
            toastr.success(data.message);
            document.getElementById('status').value = data.status;
        }).catch(error => toastr.error(error.message)).finally(() => button.disabled = false);
    }
</script>
@endpush
