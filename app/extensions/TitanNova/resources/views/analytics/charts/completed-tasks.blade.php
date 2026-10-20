@php
	use Illuminate\Support\Str;

	$chart_data = $chartData ?? [];
	$months = $months ?? [];

	// Demo data creation
	if ($app_is_demo) {
		$demo_agents = ['Justy', 'Blate'];
		$chart_data = [];

		// Generate "All" data
		$chart_data[] = [
			'label' => __('All'),
			'id' => '*',
			'chart_series' => [
				'name' => 'all',
				'data' => array_map(fn() => rand(20, 100), range(0, 11)),
			],
			'today_tasks' => rand(3, 15),
		];

		// Generate data for each channel
		foreach ($demo_agents as $agent) {
			$chart_data[] = [
				'label' => __(ucfirst($agent)),
				'id' => $agent,
				'chart_series' => [
					'name' => $agent,
					'data' => array_map(fn() => rand(5, 30), range(0, 11)),
				],
				'today_tasks' => rand(1, 5),
			];
		}
	}

	if (empty($chart_data)) {
		$chart_data = [
			[
				'label' => __('All'),
				'id' => '*',
				'chart_series' => [
					'name' => 'all',
					'data' => array_fill(0, 12, 0),
				],
				'today_tasks' => 0,
			],
		];
	}

	if (empty($months)) {
		$months = collect(range(0, 11))
			->map(fn ($i) => now()->copy()->subMonths(11 - $i)->startOfMonth())
			->all();
	}

	if ($app_is_demo) {
		$agent = collect($chart_data)->firstWhere('id', 'Justy');

		$initialFilter = $facebook['chart_series']['name'] ?? 'Justy';
		$initialLabel = $facebook['label'] ?? __('Justy');
		$initialToday = $facebook['today_tasks'] ?? 0;
	} else {
		$initialFilter = $chart_data[0]['chart_series']['name'] ?? 'all';
		$initialLabel = $chart_data[0]['label'] ?? __('All');
		$initialToday = $chart_data[0]['today_tasks'] ?? 0;
	}
@endphp

<div x-data='titanNovaCompletedTasksChart'>
	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Tasks')
				<small class="opacity-55 text-4xs">@lang('includes all task statuses')</small>
			</h4>

			<x-dropdown.dropdown
				class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
				offsetY="10px"
				anchor="end"
			>
				<x-slot:trigger>
                    <span x-text="activeFilterLabel">
                        {{ $initialLabel }}
                    </span>
					<x-tabler-chevron-down class="size-4" />
				</x-slot:trigger>

				<x-slot:dropdown
					class="min-w-44 p-2"
				>
					@foreach ($chart_data as $data)
						<x-button
							@class([
								'w-full justify-start !rounded px-4 py-2 text-start text-xs font-medium [&.active]:bg-foreground/5',
								'active' => $loop->index === 0,
							])
							variant="link"
							::class="{ 'active': activeFilter === '{{ $data['chart_series']['name'] }}' }"
							@click.prevent="setActiveFilter('{{ $data['chart_series']['name'] }}')"
						>
							{{ $data['label'] }}
						</x-button>
					@endforeach
				</x-slot:dropdown>
			</x-dropdown.dropdown>
		</x-slot:head>

		<p class="mb-6 text-2xs font-medium text-heading-foreground">
			<x-number-counter
				class="align-middle text-[24px] leading-none"
				id="completed-tasks-counter"
				value="{{ $initialToday }}"
			/>
			@lang('Today')
		</p>

		<div
			class="min-h-56"
			id="titan-nova-completed-tasks-chart"
		></div>
	</x-card>
</div>

@push('script')
	<script>
		document.addEventListener('alpine:init', () => {
			Alpine.data('titanNovaCompletedTasksChart', () => ({
				chartData: @json($chart_data),
				activeFilter: "{{ $initialFilter }}",
				chart: null,
				chartEl: document.querySelector('#titan-nova-completed-tasks-chart'),
				counterEl: document.querySelector('#completed-tasks-counter .lqd-number-counter-value'),

				get activeFilterLabel() {
					return this.chartData.find(data => data.chart_series.name === this.activeFilter)?.label ?? '{{ __('All') }}'
				},

				init() {
					this.chart = new ApexCharts(this.chartEl, {
						series: this.chartData.map(data => data.chart_series),
						colors: ['hsl(var(--primary))'],
						chart: {
							type: 'bar',
							height: 260,
							toolbar: {
								show: false,
								autoSelected: 'pan'
							},
						},
						plotOptions: {
							bar: {
								horizontal: false,
								columnWidth: '85%',
								borderRadius: 5,
								distributed: true,
							},
						},
						dataLabels: {
							enabled: false
						},
						xaxis: {
							categories: [
								@foreach ($months as $month)
									'{{ ($month instanceof \Carbon\CarbonInterface ? $month : \Carbon\Carbon::parse($month))->locale(app()->getLocale())->shortMonthName }}',
								@endforeach
							],
							labels: {
								offsetY: 0,
								style: {
									colors: 'hsl(var(--foreground) / 40%)',
									fontSize: '11px',
									fontFamily: 'inherit',
									fontWeight: 500,
								},
							},
							axisBorder: {
								show: false,
							},
							axisTicks: {
								show: false,
							},
						},
						yaxis: {
							labels: {
								offsetX: -10,
								style: {
									colors: 'hsl(var(--foreground) / 40%)',
									fontSize: '13px',
									fontFamily: 'inherit',
									fontWeight: 400,
								},
							},
						},
						grid: {
							show: true,
							borderColor: 'hsl(var(--border))',
						},
						legend: {
							show: false
						}
					});

					this.chart.render().then(() => {
						this.setActiveFilter(this.activeFilter);
					});
				},

				setActiveFilter(filter) {
					if (!filter) return;

					this.activeFilter = filter;

					const counterElData = this.counterEl && Alpine.$data(this.counterEl);

					this.chartData.forEach(data => {
						const {
							name
						} = data.chart_series;

						if (name === this.activeFilter) {
							this.chart.showSeries(name);
						} else {
							this.chart.hideSeries(name);
						}

					});

					if (counterElData && counterElData.updateValue) {
						counterElData.updateValue({
							value: this.chartData.find(data => data.chart_series.name === this.activeFilter)?.today_tasks ?? 0
						})
					}
				}
			}))
		});
	</script>
@endpush
