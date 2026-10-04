<div class="grid grid-cols-1 gap-6">
    @include('titan-nova::analytics.charts.completed-tasks', ['chartData' => $completedChartData, 'months' => $completedMonths])
</div>
