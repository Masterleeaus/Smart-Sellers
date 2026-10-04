<div class="flex items-center pb-8">
    <div class="hidden lg:block lg:w-1/2">
        <h2 class="text-[30px] font-medium leading-none">
            <span class="block opacity-50">
                @lang('Calendar')
            </span>
            @lang('View and plan your content')
        </h2>
    </div>

    <div class="flex lg:w-1/2 lg:justify-end">
        @include('titan-nova::calendar.calendar-filters', ['agents' => $agents])
    </div>
</div>
