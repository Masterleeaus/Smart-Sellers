<div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Draft Tasks')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="total-followers-counter"
				value="{{ number_format($app_is_demo ? rand(50, 500) : $stats['draft_tasks']) }}"
				:options="['delay' => 200]"
			/>
		</p>
	</x-card>

	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Scheduled Tasks')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="total-followers-counter"
				value="{{ number_format($app_is_demo ? rand(50, 500) : $stats['scheduled_tasks']) }}"
				:options="['delay' => 200]"
			/>
		</p>
	</x-card>

	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Completed Tasks')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="total-followers-counter"
				value="{{ number_format($app_is_demo ? rand(50, 500) : $stats['completed_tasks']) }}"
				:options="['delay' => 200]"
			/>
		</p>
	</x-card>

	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Total Tasks')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="total-followers-counter"
				value="{{ number_format($app_is_demo ? rand(50, 500) : $stats['total_tasks']) }}"
				:options="['delay' => 200]"
			/>
		</p>
	</x-card>
</div>
