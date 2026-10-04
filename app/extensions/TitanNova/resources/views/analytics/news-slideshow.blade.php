@if (filled($news))
    <div
        class="mb-6 flex items-center justify-between gap-3 rounded-xl border px-5 py-2.5 transition"
        x-data="{
            totalItems: {{ count($news ?? []) }},
            activeIndex: 0,
            autoplayInterval: null,
            autoplayDelay: 5000,
            init() {
                if (this.totalItems > 1) {
                    this.startAutoplay();
                }
            },
            startAutoplay() {
                this.stopAutoplay();
                if (this.totalItems > 1) {
                    this.autoplayInterval = setInterval(() => this.next(false), this.autoplayDelay);
                }
            },
            stopAutoplay() {
                if (this.autoplayInterval) {
                    clearInterval(this.autoplayInterval);
                    this.autoplayInterval = null;
                }
            },
            prev(manual = true) {
                if (this.totalItems < 1) return;
                this.activeIndex = (this.activeIndex - 1 + this.totalItems) % this.totalItems;
                if (manual) this.startAutoplay();
            },
            next(manual = true) {
                if (this.totalItems < 1) return;
                this.activeIndex = (this.activeIndex + 1) % this.totalItems;
                if (manual) this.startAutoplay();
            }
        }"
        x-init="init()"
    >
        <svg
            class="shrink-0 text-heading-foreground"
            width="21"
            height="14"
            viewBox="0 0 21 14"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M21.0004 0.75V6.75C21.0004 6.94891 20.9214 7.13968 20.7807 7.28033C20.6401 7.42098 20.4493 7.5 20.2504 7.5C20.0515 7.5 19.8607 7.42098 19.7201 7.28033C19.5794 7.13968 19.5004 6.94891 19.5004 6.75V2.56031L11.781 10.2806C11.7114 10.3504 11.6287 10.4057 11.5376 10.4434C11.4466 10.4812 11.349 10.5006 11.2504 10.5006C11.1519 10.5006 11.0543 10.4812 10.9632 10.4434C10.8722 10.4057 10.7894 10.3504 10.7198 10.2806L7.50042 7.06031L1.28104 13.2806C1.14031 13.4214 0.94944 13.5004 0.750417 13.5004C0.551394 13.5004 0.360523 13.4214 0.219792 13.2806C0.0790616 13.1399 0 12.949 0 12.75C0 12.551 0.0790616 12.3601 0.219792 12.2194L6.96979 5.46937C7.03945 5.39964 7.12216 5.34432 7.21321 5.30658C7.30426 5.26884 7.40186 5.24941 7.50042 5.24941C7.59898 5.24941 7.69657 5.26884 7.78762 5.30658C7.87867 5.34432 7.96139 5.39964 8.03104 5.46937L11.2504 8.68969L18.4401 1.5H14.2504C14.0515 1.5 13.8607 1.42098 13.7201 1.28033C13.5794 1.13968 13.5004 0.948912 13.5004 0.75C13.5004 0.551088 13.5794 0.360322 13.7201 0.21967C13.8607 0.0790178 14.0515 0 14.2504 0H20.2504C20.4493 0 20.6401 0.0790178 20.7807 0.21967C20.9214 0.360322 21.0004 0.551088 21.0004 0.75Z"
            />
        </svg>

        <div class="grid grow grid-cols-1 place-items-center">
            @foreach ($news as $news_item)
                <p
                    class="col-start-1 col-end-1 row-start-1 row-end-1 m-0 w-full text-sm font-normal text-heading-foreground transition"
                    @if ($loop->index !== 0) x-cloak @endif
                    x-show="activeIndex === {{ $loop->index }}"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                >
                    {{ $news_item }}
                </p>
            @endforeach
        </div>
        <div class="ms-auto flex shrink-0 select-none items-center">
            <button
                class="inline-grid size-10 place-items-center hover:scale-110 active:scale-90 disabled:pointer-events-none disabled:opacity-50"
                type="button"
                title="{{ __('Previous') }}"
                @click.prevent="prev"
            >
                <x-tabler-chevron-left class="size-5 rtl:rotate-180" />
            </button>
            <button
                class="inline-grid size-10 place-items-center hover:scale-110 active:scale-90 disabled:pointer-events-none disabled:opacity-50"
                type="button"
                title="{{ __('Next') }}"
                @click.prevent="next"
            >
                <x-tabler-chevron-right class="size-5 rtl:rotate-180" />
            </button>
        </div>
    </div>
@endif
