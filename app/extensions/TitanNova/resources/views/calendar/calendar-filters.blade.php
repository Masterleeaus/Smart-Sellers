<div class="flex items-center">
    @foreach ($agents as $agent)
        <button
            class="relative -ms-2.5 transition before:pointer-events-none before:absolute before:-end-2 before:top-full before:mt-1 before:w-52 before:translate-y-0 before:rounded-md before:bg-background before:px-2 before:py-2 before:text-[12px] before:font-medium before:opacity-0 before:shadow-md before:shadow-black/5 before:transition before:content-[attr(title)] hover:z-2 hover:-translate-y-1 hover:scale-110 hover:before:translate-y-0 hover:before:opacity-100"
            title="{{ $agent->name }}"
            x-data="{
                active: false
            }"
            @click.prevent="$dispatch('filter-calendar-by-agent', {agentId: {{ $agent->id }}}); active = !active;"
        >
            <figure
                class="inline-grid size-8 place-items-center overflow-hidden rounded-full border-2 border-background bg-[#969696] text-background dark:bg-[#414954] dark:text-foreground"
                :class="{ 'border-green-400 shadow-md shadow-green-400/50': active }"
            >
                @if ($agent->image)
                    <img
                        class="size-full object-cover object-center"
                        src="{{ $agent->image }}"
                        alt="{{ $agent->name }}"
                    >
                @else
                    <svg
                        width="14"
                        height="19"
                        viewBox="0 0 17 23"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M8.17757 0C11.0099 0 13.305 2.29589 13.305 5.12695C13.305 7.95869 11.0099 10.2539 8.17757 10.2539C5.34664 10.2539 3.05152 7.95869 3.05152 5.12695C3.05152 2.29589 5.34664 0 8.17757 0ZM8.17416 22.3128C5.34936 22.3128 2.76217 21.284 0.766625 19.5811C0.280506 19.1665 0 18.5585 0 17.9205C0 15.0493 2.32371 12.7513 5.19549 12.7513H11.161C14.0335 12.7513 16.3483 15.0493 16.3483 17.9205C16.3483 18.5591 16.0692 19.1658 15.5824 19.5805C13.5875 21.284 10.9996 22.3128 8.17416 22.3128Z"
                        />
                    </svg>
                @endif
            </figure>
        </button>
    @endforeach
</div>
