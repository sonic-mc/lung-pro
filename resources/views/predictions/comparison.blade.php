@extends('layouts.app')

@section('title', 'Model Comparison Workspace')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Model Comparison Workspace</h1>
            <p class="text-muted mb-0">Aggregate comparison across {{ $totalPredictions }} predictions. Ground-truth coverage: {{ $groundTruthCount }} / {{ $totalPredictions }} ({{ number_format((float) $groundTruthCoverage, 2) }}%).</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('predictions.statistics') }}" class="btn btn-outline-success">Statistics</a>
            <a href="{{ route('predictions.audit') }}" class="btn btn-outline-danger">FP/FN Audit</a>
            <a href="{{ route('scans.create') }}" class="btn btn-primary">New Upload</a>
            <a href="{{ route('predictions.index') }}" class="btn btn-outline-secondary">Prediction Dashboard</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Predictions</div>
                    <div class="h4 mb-0">{{ $totalPredictions }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Ground Truth Labeled</div>
                    <div class="h4 mb-0">{{ $groundTruthCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-success-subtle">
                <div class="card-body">
                    <div class="text-muted small">Truth Coverage</div>
                    <div class="h4 mb-0 text-success">{{ number_format((float) $groundTruthCoverage, 2) }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Primary Model Usage and Outcomes</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Cases</th>
                            <th>Avg Confidence</th>
                            <th>Malignant Rate</th>
                            <th>Feedback Accepted</th>
                            <th>Feedback Rejected</th>
                            <th>Acceptance Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($primaryStats as $model => $row)
                            <tr>
                                <td>{{ $model }}</td>
                                <td>{{ $row['cases'] }}</td>
                                <td>{{ number_format((float) $row['avg_probability'], 2) }}%</td>
                                <td>{{ number_format((float) $row['malignant_rate'], 2) }}%</td>
                                <td>{{ $row['accepted'] }}</td>
                                <td>{{ $row['rejected'] }}</td>
                                <td>
                                    @if (! is_null($row['acceptance_rate']))
                                        {{ number_format((float) $row['acceptance_rate'], 2) }}%
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">Acceptance rate uses radiologist feedback decision as a practical clinical proxy where available.</div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Multi-Model Output Summary (All Cases)</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Cases</th>
                            <th>Avg Confidence</th>
                            <th>Malignant Rate</th>
                            <th>Agreement with Final Output</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparisonStats as $model => $row)
                            <tr>
                                <td>{{ $model }}</td>
                                <td>{{ $row['cases'] }}</td>
                                <td>{{ number_format((float) $row['avg_probability'], 2) }}%</td>
                                <td>{{ number_format((float) $row['malignant_rate'], 2) }}%</td>
                                <td>{{ number_format((float) $row['agreement_rate'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Recent Cases (Comparison Snapshot)</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Patient</th>
                            <th>MRN</th>
                            <th>Primary Model</th>
                            <th>Final Output</th>
                            <th>ResNet</th>
                            <th>DenseNet</th>
                            <th>Hybrid</th>
                            <th>YOLOv8</th>
                            <th>KerasHF</th>
                            <th>Feedback</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCases as $case)
                            @php
                                $comparisonByModel = [];
                                foreach ($case['comparisons'] as $entry) {
                                    $name = data_get($entry, 'model');
                                    if (is_string($name) && $name !== '') {
                                        $comparisonByModel[$name] = $entry;
                                    }
                                }
                            @endphp
                            <tr>
                                <td>#{{ $case['id'] }}</td>
                                <td>{{ $case['patient'] }}</td>
                                <td>{{ $case['mrn'] }}</td>
                                <td>{{ $case['primary_model'] }}</td>
                                <td>{{ $case['final_label'] }} ({{ number_format($case['final_probability'] * 100, 2) }}%)</td>
                                <td>
                                    {{ data_get($comparisonByModel, 'ResNet.result', 'N/A') }}
                                    @if (data_get($comparisonByModel, 'ResNet.probability') !== null)
                                        ({{ number_format(((float) data_get($comparisonByModel, 'ResNet.probability')) * 100, 2) }}%)
                                    @endif
                                </td>
                                <td>
                                    {{ data_get($comparisonByModel, 'DenseNet.result', 'N/A') }}
                                    @if (data_get($comparisonByModel, 'DenseNet.probability') !== null)
                                        ({{ number_format(((float) data_get($comparisonByModel, 'DenseNet.probability')) * 100, 2) }}%)
                                    @endif
                                </td>
                                <td>
                                    {{ data_get($comparisonByModel, 'Hybrid.result', 'N/A') }}
                                    @if (data_get($comparisonByModel, 'Hybrid.probability') !== null)
                                        ({{ number_format(((float) data_get($comparisonByModel, 'Hybrid.probability')) * 100, 2) }}%)
                                    @endif
                                </td>
                                <td>
                                    {{ data_get($comparisonByModel, 'YOLOv8.result', 'N/A') }}
                                    @if (data_get($comparisonByModel, 'YOLOv8.probability') !== null)
                                        ({{ number_format(((float) data_get($comparisonByModel, 'YOLOv8.probability')) * 100, 2) }}%)
                                    @endif
                                </td>
                                <td>
                                    {{ data_get($comparisonByModel, 'KerasHF.result', 'N/A') }}
                                    @if (data_get($comparisonByModel, 'KerasHF.probability') !== null)
                                        ({{ number_format(((float) data_get($comparisonByModel, 'KerasHF.probability')) * 100, 2) }}%)
                                    @endif
                                </td>
                                <td>{{ $case['feedback'] ? ucfirst($case['feedback']) : 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('predictions.show', $case['id']) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-4">No prediction data available for comparison.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
