<div class="py-10">
    @if ($agents->isEmpty())
        <h2 class="mb-4">
            @lang('No agents yet')
        </h2>
        <p class="mb-5">
            @lang('Create your first AI Titan Nova agent to help you write and manage your blog content')
        </p>
        <x-button
            href="{{ route('dashboard.user.titan-nova.agent.create') }}"
            size="lg"
        >
            <x-tabler-plus class="size-4" />
            @lang('Add Agent')
        </x-button>
    @else
        <h2 class="mb-9">
            @lang('Titan Nova Agents')
        </h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($agents as $agent)
                <x-card>
                    <div class="absolute end-4 top-4 z-1">
                        <x-dropdown.dropdown
                            anchor="end"
                            offsetY="15px"
                        >
                            <x-slot:trigger
                                class="size-11 p-0"
                                variant="outline"
                                size="none"
                            >
                                <x-tabler-dots class="size-6" />
                            </x-slot:trigger>
                            <x-slot:dropdown
                                class="min-w-48 p-2"
                            >
                                <x-button
                                    class="w-full justify-start !rounded px-2 py-2 text-start hover:bg-foreground/5"
                                    variant="link"
                                    href="{{ route('dashboard.user.titan-nova.agent.edit', $agent) }}"
                                    title="@lang('Edit')"
                                >
                                    @lang('Edit')

                                </x-button>
								@if(\App\Helpers\Classes\Helper::appIsDemo())
									<form
										class="w-full"
{{--										action="{{ route('dashboard.user.titan-nova.agent.destroy', $agent) }}"--}}
{{--										method="POST"--}}
										onsubmit="return confirm('@lang('Are you sure you want to delete this agent? All associated tasks will also be deleted.')')"
									>
										@csrf
										@method('DELETE')
										<x-button
											class="w-full justify-start !rounded px-2 py-2 text-start"
											class="w-full justify-start !rounded px-2 py-2 text-start"
											type="button"
											hover-variant="danger"
											variant="link"
											onclick="return toastr.info('This feature is disabled in Demo version.');"
										>
											@lang('Delete')
										</x-button>
									</form>
								@else
									<form
										class="w-full"
										action="{{ route('dashboard.user.titan-nova.agent.destroy', $agent) }}"
										method="POST"
										onsubmit="return confirm('@lang('Are you sure you want to delete this agent? All associated tasks will also be deleted.')')"
									>
										@csrf
										@method('DELETE')
										<x-button
											class="w-full justify-start !rounded px-2 py-2 text-start"
											class="w-full justify-start !rounded px-2 py-2 text-start"
											type="submit"
											hover-variant="danger"
											variant="link"
										>
											@lang('Delete')
										</x-button>
									</form>
								@endif

                            </x-slot:dropdown>
                        </x-dropdown.dropdown>
                    </div>
                    <div class="text-center">
                        <div class="mb-4 flex justify-center">
                            <figure
                                class="inline-grid size-[100px] place-items-center overflow-hidden rounded-full bg-gradient-to-br from-heading-foreground/10 from-[-10%] to-heading-foreground/75 text-white dark:from-heading-foreground/5 dark:to-heading-foreground/50"
                            >
                                @if ($agent->image)
                                    <img
                                        class="size-full object-cover object-center"
                                        src="{{ $agent->image }}"
                                        alt="{{ $agent->name }}"
                                    >
                                @else
                                    <svg
                                        class="-mb-5"
                                        width="53"
                                        height="60"
                                        viewBox="0 0 53 60"
                                        fill="currentColor"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M26.4191 59.3442C17.0361 59.3442 8.44287 56.9236 1.81433 51.2617C0.199606 49.8832 -0.398015 47.2257 0.26792 44.7404C2.35094 36.9664 6.7234 35.2167 16.2625 35.2167H36.0775C45.619 35.2167 50.3425 36.4263 52.5702 44.7404C53.34 47.6133 52.6432 49.8808 51.0262 51.2597C44.3998 56.9236 35.8043 59.3442 26.4191 59.3442Z"
                                        />
                                        <path
                                            d="M26.4171 0C34.8842 0 41.7449 6.87005 41.7449 15.3416C41.7449 23.8152 34.8842 30.6833 26.4171 30.6833C17.9541 30.6833 11.0933 23.8152 11.0933 15.3416C11.0933 6.87005 17.9541 0 26.4171 0Z"
                                        />
                                    </svg>
                                @endif
                            </figure>
                        </div>

                        <p class="mb-4 flex items-center justify-center gap-1.5 text-[12px] font-medium">
                            <span @class([
                                'inline-flex size-[9px] rounded-full',
                                'bg-green-500' => $agent->is_active,
                                'bg-foreground/50' => !$agent->is_active,
                            ])></span>
                            {{ $agent->is_active ? __('Active') : __('Inactive') }}
                        </p>

                        <h4 class="mb-3.5 font-body text-base font-medium">
                            {{ $agent->name }}
                        </h4>

                        @if ($agent->description)
                            <p class="mb-4 text-balance text-2xs leading-[1.4em]">
                                {{ $agent->description }}
                            </p>
                        @endif

                        <p class="mb-4 text-4xs">
                            @lang('Created:')
                            {{ $agent->created_at }}
                        </p>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
