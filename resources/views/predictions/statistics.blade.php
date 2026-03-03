@extends('layouts.app')

@section('title', 'Statistical Significance')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Statistical Significance Module</h1>
            <p class="text-muted mb-0">Reviewed cases used for analysis: {{ $reviewedCount }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('predictions.statistics.export', ['range' => $selectedRange]) }}" class="btn btn-primary">Export CSV</a>
            <a href="{{ route('predictions.comparison') }}" class="btn btn-outline-primary">Model Comparison</a>
            <a href="{{ route('predictions.audit') }}" class="btn btn-outline-danger">FP/FN Audit</a>
            <a href="{{ route('predictions.index') }}" class="btn btn-outline-secondary">Dashboard</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('predictions.statistics') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="range" class="form-label mb-1">Trend Window</label>
                    <select id="range" name="range" class="form-select" onchange="this.form.submit()">
                        <option value="all" @selected($selectedRange === 'all')>All time</option>
                        <option value="7" @selected($selectedRange === '7')>Last 7 days</option>
                        <option value="30" @selected($selectedRange === '30')>Last 30 days</option>
                        <option value="90" @selected($selectedRange === '90')>Last 90 days</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="small text-muted">This filter applies to the trend chart only. Metric tables remain based on all available reviewed data.</div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Predictions</div>
                    <div class="h4 mb-0">{{ $totalPredictions }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Ground Truth Labeled</div>
                    <div class="h4 mb-0">{{ $groundTruthCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border-success-subtle">
                <div class="card-body">
                    <div class="text-muted small">Truth Coverage</div>
                    <div class="h4 mb-0 text-success">{{ number_format((float) $groundTruthCoverage, 2) }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Cases Used in Tests</div>
                    <div class="h4 mb-0">{{ $reviewedCount }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Coverage and Reviewed Volume Trend</h2>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">
                Line: cumulative truth coverage (%). Bars: reviewed cases per day.
                Window: {{ $selectedRange === 'all' ? 'All time' : 'Last '.$selectedRange.' days' }}.
            </div>
            <canvas id="truth-trend-chart" height="110"></canvas>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Per-Model Classification Metrics (Proxy Truth)</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Support</th>
                            <th>Accuracy</th>
                            <th>Precision</th>
                            <th>Recall</th>
                            <th>F1-score</th>
                            <th>TP</th>
                            <th>TN</th>
                            <th>FP</th>
                            <th>FN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metrics as $model => $row)
                            <tr>
                                <td>{{ $model }}</td>
                                <td>{{ $row['support'] }}</td>
                                <td>{{ number_format((float) $row['accuracy'], 2) }}%</td>
                                <td>{{ number_format((float) $row['precision'], 2) }}%</td>
                                <td>{{ number_format((float) $row['recall'], 2) }}%</td>
                                <td>{{ number_format((float) $row['f1'], 2) }}%</td>
                                <td>{{ $row['tp'] }}</td>
                                <td>{{ $row['tn'] }}</td>
                                <td>{{ $row['fp'] }}</td>
                                <td>{{ $row['fn'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Pairwise Significance Tests (McNemar)</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pair</th>
                            <th>Discordant</th>
                            <th>A Better</th>
                            <th>B Better</th>
                            <th>Chi-square</th>
                            <th>p-value</th>
                            <th>Significant (p &lt; 0.05)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pairwise as $test)
                            <tr>
                                <td>{{ $test['pair'] }}</td>
                                <td>{{ $test['discordant'] }}</td>
                                <td>{{ $test['a_better'] }}</td>
                                <td>{{ $test['b_better'] }}</td>
                                <td>{{ number_format((float) $test['chi_square'], 4) }}</td>
                                <td>{{ is_null($test['p_value']) ? 'N/A' : number_format((float) $test['p_value'], 6) }}</td>
                                <td>
                                    @if (is_null($test['p_value']))
                                        N/A
                                    @else
                                        <span class="badge {{ $test['significant'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $test['significant'] ? 'Yes' : 'No' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">Insufficient reviewed data for pairwise testing.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mb-0">
        <strong>Method note:</strong>
        Ground truth labels are used when recorded. If missing, proxy truth is derived from radiologist feedback
        (accepted prediction treated as correct, rejected prediction treated as incorrect with opposite class assumed true).
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(() => {
    const canvas = document.getElementById('truth-trend-chart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const labels = @json($trendLabels);
    const coverage = @json($truthCoverageTrend);
    const reviewedVolume = @json($reviewedVolumeTrend);

    if (!Array.isArray(labels) || labels.length === 0) {
        return;
    }

    new Chart(canvas, {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Reviewed Cases / Day',
                    data: reviewedVolume,
                    yAxisID: 'yVolume',
                },
                {
                    type: 'line',
                    label: 'Truth Coverage %',
                    data: coverage,
                    yAxisID: 'yCoverage',
                    tension: 0.25,
                }
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                yCoverage: {
                    type: 'linear',
                    position: 'left',
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Coverage %'
                    }
                },
                yVolume: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                    title: {
                        display: true,
                        text: 'Reviewed Cases'
                    }
                }
            }
        }
    });
})();
</script>
@endpush
@endsection
