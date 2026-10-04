@php
    $step = $step ?? 1;
@endphp

<div
    class="space-y-1 text-xs font-medium text-red-500"
    x-cloak
    x-show="stepsErrors.get({{ $step }}).length"
>
    <template x-for="err in stepsErrors.get({{ $step }})">
        <p
            class="m-0"
            x-text="err"
        ></p>
    </template>
</div>
