@php
    $banner_bg_light = asset('vendor/titan-nova/images/img-6.jpg');
    $banner_bg_dark = asset('vendor/titan-nova/images/img-6-dark.png');
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
    class="titan-nova-banner relative flex flex-wrap items-center overflow-hidden rounded-2xl bg-cover bg-top [background-image:url('{{ $banner_bg_light }}')] dark:bg-white/[2%] dark:[background-image:url('{{ $banner_bg_dark }}')] lg:min-h-[50vmin] lg:flex-nowrap">
    <div class="w-full px-5 pb-16 pt-10 lg:w-[600px] lg:px-11 lg:py-36">

        @if ($determineAgentOfMonth)
            <p class="mb-7 flex items-center gap-2.5 text-base font-medium">
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="#22C38E"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M10 20C8.33333 20 6.80417 19.6333 5.4125 18.9C4.02083 18.1667 2.86667 17.1833 1.95 15.95L6.05 11.85L9.05 14.35L14 9.4V12H16V6H10V8H12.6L8.95 11.65L5.95 9.15L0.9 14.2C0.616667 13.5667 0.395833 12.8958 0.2375 12.1875C0.0791667 11.4792 0 10.75 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z"
                    />
                </svg>
                @lang('Agent of the month: :name', ['name' => $determineAgentOfMonth->name])
            </p>
        @endif

        <h2 class="mb-7 text-[30px] leading-[1.05em]">
            <span class="block text-[21px]">
                <span class="opacity-50">
                    @lang('Welcome to Titan Nova')
                </span>
                👋
            </span>
            @lang('Create and manage your Titan Novas.')
        </h2>

        <x-button
            class="outline-foreground/5"
            hover-variant="primary"
            size="lg"
            href="{{ route('dashboard.user.titan-nova.agent.create') }}"
            variant="outline"
        >
            <x-tabler-plus class="size-4" />
            @lang('Add Agent')
        </x-button>
    </div>

    <div class="-order-1 flex justify-end lg:absolute lg:end-0 lg:top-0 lg:h-full lg:max-w-[calc(100%-600px)]">
        <figure class="lg:h-full">
            <img
                class="max-lg:[mask-image:linear-gradient(#000_80%,transparent)] lg:h-full lg:object-cover ltr:max-lg:-scale-x-100"
                width="590"
                height="627"
                src="{{ asset('vendor/titan-nova/images/img-8.png') }}"
                alt="{{ __('An image of the agent of the month') }}"
                aria-hidden="true"
            >
            <img
                class="absolute top-14 max-lg:-end-20 lg:start-0"
                width="169"
                height="171"
                src="{{ asset('vendor/titan-nova/images/img-7.png') }}"
                alt="{{ __('Decorative image') }}"
                aria-hidden="true"
            >
        </figure>
    </div>
</div>
