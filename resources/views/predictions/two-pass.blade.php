@extends('layouts.app')

@section('title', 'Two-Pass Radiologist Review')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Radiologist Two-Pass Dashboard</h1>
            <p class="text-muted mb-0">Baseline (without AI cues) versus AI-assisted interpretation outcomes.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('predictions.statistics') }}" class="btn btn-outline-success">Statistics</a>
            <a href="{{ route('predictions.index') }}" class="btn btn-outline-secondary">Dashboard</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total Reviews</div><div class="h4 mb-0">{{ $summary['total_reviews'] }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Baseline Accuracy</div><div class="h4 mb-0">{{ number_format((float) $summary['baseline_accuracy'], 2) }}%</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">AI-Assisted Accuracy</div><div class="h4 mb-0">{{ number_format((float) $summary['assisted_accuracy'], 2) }}%</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border-primary-subtle"><div class="card-body"><div class="text-muted small">Accuracy Gain</div><div class="h4 mb-0 text-primary">{{ number_format((float) $summary['accuracy_gain'], 2) }}%</div></div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Average Time Reduction</div><div class="h4 mb-0 text-success">{{ number_format((float) ($summary['time_reduction_percent'] ?? 0), 2) }}%</div><div class="small text-muted">Baseline {{ number_format((float) ($summary['avg_baseline_time_seconds'] ?? 0), 0) }}s → Assisted {{ number_format((float) ($summary['avg_assisted_time_seconds'] ?? 0), 0) }}s</div></div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Average Confidence Gain</div><div class="h4 mb-0 text-success">{{ number_format((float) ($summary['confidence_gain'] ?? 0), 2) }}%</div><div class="small text-muted">Baseline {{ number_format((float) ($summary['avg_baseline_confidence'] ?? 0), 2) }}% → Assisted {{ number_format((float) ($summary['avg_assisted_confidence'] ?? 0), 2) }}%</div></div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Overlooked Findings Recovered</div><div class="h4 mb-0 text-success">{{ $summary['overlooked_recovered'] ?? 0 }}</div><div class="small text-muted">{{ number_format((float) ($summary['overlooked_recovered_rate'] ?? 0), 2) }}% of reviewed cases</div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Outcome Shift Summary</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <tr><th style="width: 260px;">Improved (baseline wrong, assisted correct)</th><td>{{ $summary['improved'] }}</td></tr>
                        <tr><th>Worsened (baseline correct, assisted wrong)</th><td>{{ $summary['worsened'] }}</td></tr>
                        <tr><th>Unchanged</th><td>{{ $summary['unchanged'] }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Recent Two-Pass Reviews</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Patient</th>
                            <th>MRN</th>
                            <th>Model</th>
                            <th>Baseline</th>
                            <th>AI-Assisted</th>
                            <th>Time (s)</th>
                            <th>Proxy Truth</th>
                            <th>Outcome Shift</th>
                            <th>Recovered</th>
                            <th>Reviewer</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $item)
                            <tr>
                                <td>#{{ $item['prediction_id'] }}</td>
                                <td>{{ $item['patient'] }}</td>
                                <td>{{ $item['mrn'] }}</td>
                                <td>{{ $item['model'] }}</td>
                                <td>{{ $item['baseline_label'] }}</td>
                                <td>{{ $item['assisted_label'] }}</td>
                                <td>
                                    @if(!is_null($item['baseline_time_seconds']) && !is_null($item['assisted_time_seconds']))
                                        {{ (int) $item['baseline_time_seconds'] }} → {{ (int) $item['assisted_time_seconds'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $item['truth_label'] ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $item['delta'] === 'Improved' ? 'text-bg-success' : ($item['delta'] === 'Worsened' ? 'text-bg-danger' : 'text-bg-secondary') }}">
                                        {{ $item['delta'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ !empty($item['overlooked_recovered']) ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ !empty($item['overlooked_recovered']) ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>{{ $item['reviewer'] }}</td>
                                <td><a href="{{ route('predictions.show', $item['prediction_id']) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center py-4">No two-pass reviews submitted yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
