@extends('layouts.app')

@section('title', 'FP/FN Audit Panel')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">False Positive / False Negative Audit Panel</h1>
            <p class="text-muted mb-0">Ground-truth-based audit when available, otherwise feedback-derived proxy truth.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('predictions.comparison') }}" class="btn btn-outline-primary">Model Comparison</a>
            <a href="{{ route('predictions.index') }}" class="btn btn-outline-secondary">Prediction Dashboard</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Cases</div>
                    <div class="h4 mb-0">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Reviewed Cases</div>
                    <div class="h4 mb-0">{{ $summary['reviewed'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border-danger-subtle">
                <div class="card-body">
                    <div class="text-muted small">Proxy FP Rate</div>
                    <div class="h4 mb-0 text-danger">{{ number_format((float) $summary['proxy_fp_rate'], 2) }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="text-muted small">Proxy FN Rate</div>
                    <div class="h4 mb-0 text-warning">{{ number_format((float) $summary['proxy_fn_rate'], 2) }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Audit Summary</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 260px;">Proxy True Positive (Accepted Malignant)</th>
                            <td>{{ $summary['proxy_tp'] }}</td>
                        </tr>
                        <tr>
                            <th>Proxy True Negative (Accepted Benign)</th>
                            <td>{{ $summary['proxy_tn'] }}</td>
                        </tr>
                        <tr>
                            <th>Proxy False Positive (Rejected Malignant)</th>
                            <td>{{ $summary['proxy_fp'] }}</td>
                        </tr>
                        <tr>
                            <th>Proxy False Negative (Rejected Benign)</th>
                            <td>{{ $summary['proxy_fn'] }}</td>
                        </tr>
                        <tr>
                            <th>Unreviewed</th>
                            <td>{{ $summary['unreviewed'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">
                Truth source: confirmed ground truth label takes precedence; if missing, feedback-derived proxy truth is used.
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Model-Wise Proxy Error Rates</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Total</th>
                            <th>Reviewed</th>
                            <th>Proxy FP</th>
                            <th>Proxy FN</th>
                            <th>Proxy FP Rate</th>
                            <th>Proxy FN Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modelStats as $model => $row)
                            <tr>
                                <td>{{ $model }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['reviewed'] }}</td>
                                <td>{{ $row['proxy_fp'] }}</td>
                                <td>{{ $row['proxy_fn'] }}</td>
                                <td>{{ number_format((float) $row['proxy_fp_rate'], 2) }}%</td>
                                <td>{{ number_format((float) $row['proxy_fn_rate'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Flagged Cases (Proxy FP/FN)</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Patient</th>
                            <th>MRN</th>
                            <th>Type</th>
                            <th>Model</th>
                            <th>Modality</th>
                            <th>Prediction</th>
                            <th>Confidence</th>
                            <th>Severity</th>
                            <th>Finding</th>
                            <th>Comment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($flaggedCases as $case)
                            <tr>
                                <td>#{{ $case['id'] }}</td>
                                <td>{{ $case['patient'] }}</td>
                                <td>{{ $case['mrn'] }}</td>
                                <td>
                                    <span class="badge {{ $case['bucket'] === 'proxy_fp' ? 'text-bg-danger' : 'text-bg-warning' }}">
                                        {{ strtoupper(str_replace('_', ' ', $case['bucket'])) }}
                                    </span>
                                </td>
                                <td>{{ $case['model'] }}</td>
                                <td>{{ $case['modality'] }}</td>
                                <td>{{ $case['predicted_label'] }}</td>
                                <td>{{ number_format($case['probability'] * 100, 2) }}%</td>
                                <td>{{ !is_null($case['severity_score']) ? number_format((float) $case['severity_score'], 2) : 'N/A' }}</td>
                                <td>{{ $case['finding_location'] ?? 'N/A' }}</td>
                                <td>{{ $case['feedback_comment'] ?? 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('predictions.show', $case['id']) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-4">No flagged proxy FP/FN cases yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
