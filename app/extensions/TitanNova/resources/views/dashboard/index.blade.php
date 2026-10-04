@php
    $theme = get_theme();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true, 'disable_tblr' => true, 'layout_wide' => true])
@section('title')
    {{ __('Welcome') }}, {{ auth()->user()->name }}.
@endsection
@section('titlebar_subtitle', __('Manage your AI-powered Titan Nova Agents'))
@section('titlebar_actions')
    @include('titan-nova::components.titlebar-actions')
@endsection

@push('after-body-open')
    <script>
        (() => {
            localStorage.setItem('lqdNavbarShrinked', true);
            document.body.classList.add("navbar-shrinked");
        })();
    </script>
@endpush

@push('css')
    <link
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
        rel="stylesheet"
    />

    <style>
        @keyframes slide-up {
            from {
                transform: translateY(100px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .animate-slide-up {
            animation: slide-up 0.3s ease-out;
        }
    </style>
@endpush

@section('content')
    <div @class(['px-5', 'py-5' => $theme !== 'titan-nova-dashboard'])>
        <div x-data="titanNovaTasks">
            @include('titan-nova::dashboard.banner', [
                'total_tasks_count' => $total_tasks_count,
                'scheduled_tasks_count' => $scheduled_tasks_count,
                'pending_tasks_count' => $pending_tasks_count,
                'new_tasks' => $new_tasks,
                'new_impressions' => $new_impressions,
                'default_agent_id' => $defaultAgent->id ?? null,
                'creation_status' => $creation_status ?? ['status' => 'idle'],
            ])

            @include('titan-nova::dashboard.calendar')

            @include('titan-nova::components.tasks.carousel.tasks-container', ['tasks' => $tasks])
        </div>
    </div>
@endsection

@push('script')
    @include('titan-nova::components.tasks.tasks-script', [
        'channels_with_image' => '',
        'total_tasks_count' => $total_tasks_count,
        'scheduled_tasks_count' => $scheduled_tasks_count,
        'pending_tasks_count' => $pending_tasks_count,
        'default_agent_id' => $defaultAgent->id ?? null,
        'creation_status' => $creation_status ?? ['status' => 'idle'],
    ])

    <script>
        // Check for pending tasks count and update badge
        function updatePendingCount() {
            fetch('{{ route('dashboard.user.titan-nova.agent.api.pending-count') }}', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        const badge = document.getElementById('pending-count-badge');
                        if (badge) {
                            badge.textContent = data.count;
                            badge.classList.remove('hidden');
                        }
                    }
                })
                .catch(error => console.error('Error fetching pending count:', error));
        }

        // Update count on page load
        updatePendingCount();

        // Update count every 30 seconds
        setInterval(updatePendingCount, 30000);

        // Listen for broadcast notifications (if Echo is available)
        if (typeof Echo !== 'undefined') {
            Echo.private('App.Models.User.{{ Auth::id() }}')
                .notification((notification) => {
                    if (notification.type === 'task_creation_completed') {
                        const message = notification.message || '{{ __('Tasks creation completed!') }}';
                        toastr.success(message);
                        setTimeout(updatePendingCount, 1000);
                    }
                });
        }
    </script>
@endpush
