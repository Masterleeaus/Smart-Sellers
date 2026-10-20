@php
    $theme = get_theme();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true, 'disable_tblr' => true, 'layout_wide' => true])
@section('title', __('AI Agents'))
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

@section('content')
    <div @class(['px-5', 'py-5' => $theme !== 'titan-nova-dashboard'])>
        @include('titan-nova::agents.banner', ['determineAgentOfMonth' => $determineAgentOfMonth])
        @include('titan-nova::agents.agents-grid', ['agents' => $agents])
    </div>
@endsection
