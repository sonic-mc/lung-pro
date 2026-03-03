@extends('layouts.app')

@section('title', 'Patient Scan History')

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Patient Scan History & Progress Tracking</h1>
            <p class="text-muted mb-0">{{ $patient->full_name }} (MRN: {{ $patient->medical_record_number }})</p>
        </div>
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">Back to Patients</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Latest Scan</strong></div>
                <div class="card-body">
                    @if ($latest)
                        <p class="mb-1"><strong>Date:</strong> {{ $latest->evaluated_at ?? $latest->created_at }}</p>
                        <p class="mb-1"><strong>Prediction:</strong> {{ $latest->predicted_label }}</p>
                        <p class="mb-1"><strong>Probability:</strong> {{ number_format($latest->probability * 100, 2) }}%</p>
                        <p class="mb-1"><strong>Nodule Diameter:</strong> {{ is_null($latest->nodule_diameter_mm) ? 'N/A' : number_format($latest->nodule_diameter_mm, 2).' mm' }}</p>
                        <p class="mb-1"><strong>Tumor Area:</strong> {{ is_null($latest->tumor_area_mm2) ? 'N/A' : number_format($latest->tumor_area_mm2, 2).' mm²' }}</p>
                        <p class="mb-1"><strong>Tumor Volume:</strong> {{ is_null($latest->tumor_volume_mm3) ? 'N/A' : number_format($latest->tumor_volume_mm3, 2).' mm³' }}</p>
                        <p class="mb-1"><strong>Growth Rate:</strong> {{ is_null($latest->growth_rate_percent) ? 'N/A' : number_format($latest->growth_rate_percent, 2).'%' }}</p>
                        <p class="mb-0"><strong>Burden:</strong> {{ is_null($latest->nodule_burden_percent) ? 'N/A' : number_format($latest->nodule_burden_percent, 2).'%' }}</p>
                    @else
                        <p class="text-muted mb-0">No scan records available.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Previous Scan Comparison</strong></div>
                <div class="card-body">
                    @if ($latest && $previous)
                        <p class="mb-1"><strong>Previous Date:</strong> {{ $previous->evaluated_at ?? $previous->created_at }}</p>
                        <p class="mb-1"><strong>Previous Prediction:</strong> {{ $previous->predicted_label }}</p>
                        <p class="mb-1"><strong>Previous Probability:</strong> {{ number_format($previous->probability * 100, 2) }}%</p>
                        <hr>
                        <p class="mb-1"><strong>Probability Change:</strong> {{ number_format(($latest->probability - $previous->probability) * 100, 2) }}%</p>
                        <p class="mb-1"><strong>Nodule Diameter Change:</strong> {{ number_format(($latest->nodule_diameter_mm ?? 0) - ($previous->nodule_diameter_mm ?? 0), 2) }} mm</p>
                        <p class="mb-1"><strong>Tumor Volume Change:</strong> {{ number_format(($latest->tumor_volume_mm3 ?? 0) - ($previous->tumor_volume_mm3 ?? 0), 2) }} mm³</p>
                        <p class="mb-0"><strong>Growth Rate:</strong> {{ is_null($latest->growth_rate_percent) ? 'N/A' : number_format($latest->growth_rate_percent, 2).'%' }}</p>
                    @else
                        <p class="text-muted mb-0">At least two scans are required for direct comparison.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Probability Trend Graph</strong></div>
                <div class="card-body">
                    <canvas id="probabilityChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Growth Tracking of Nodules</strong></div>
                <div class="card-body">
                    <canvas id="diameterChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><strong>Tumor Growth Rate Trend</strong></div>
                <div class="card-body">
                    <canvas id="growthRateChart" height="90"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="output-review">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Output Review Queue</strong>
            <span class="small text-muted">Review and validate historical AI outputs</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Model</th>
                            <th>Output</th>
                            <th>Probability</th>
                            <th>Review Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($timeline as $entry)
                            @php
                                $reviewed = in_array((string) ($entry->feedback?->decision ?? ''), ['accept', 'reject'], true);
                            @endphp
                            <tr>
                                <td>{{ optional($entry->evaluated_at ?? $entry->created_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ $entry->model_version ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $entry->predicted_label === 'Malignant' ? 'text-bg-danger' : 'text-bg-success' }}">
                                        {{ $entry->predicted_label }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $entry->probability * 100, 2) }}%</td>
                                <td>
                                    @if ($reviewed)
                                        <span class="badge text-bg-success">Reviewed ({{ ucfirst((string) $entry->feedback?->decision) }})</span>
                                    @else
                                        <span class="badge text-bg-warning">Pending Review</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('predictions.show', $entry) }}#radiologist-feedback" class="btn btn-sm btn-outline-primary">Review Output</a>
                                    <a href="{{ route('predictions.report', $entry) }}" class="btn btn-sm btn-outline-secondary">Report</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No prediction outputs found for review.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Disease Progression Timeline</strong></div>
        <div class="card-body">
            @forelse ($timeline as $entry)
                <div class="border-start border-3 ps-3 mb-3">
                    <div class="small text-muted">{{ $entry->evaluated_at ?? $entry->created_at }}</div>
                    <div><strong>{{ $entry->predicted_label }}</strong> · {{ number_format($entry->probability * 100, 2) }}% · {{ $entry->confidence_band ?? 'N/A' }}</div>
                    <div class="small text-muted">Location: {{ $entry->finding_location ?? 'N/A' }} | Severity: {{ is_null($entry->severity_score) ? 'N/A' : number_format($entry->severity_score, 2).'/100' }} | Diameter: {{ is_null($entry->nodule_diameter_mm) ? 'N/A' : number_format($entry->nodule_diameter_mm, 2).' mm' }} | Volume: {{ is_null($entry->tumor_volume_mm3) ? 'N/A' : number_format($entry->tumor_volume_mm3, 2).' mm³' }} | Growth: {{ is_null($entry->growth_rate_percent) ? 'N/A' : number_format($entry->growth_rate_percent, 2).'%' }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">No progression records yet.</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const labels = @json($labels);
    const probabilitySeries = @json($probabilitySeries);
    const diameterSeries = @json($diameterSeries);
    const growthRateSeries = @json($growthRateSeries);

    const probabilityCtx = document.getElementById('probabilityChart');
    if (probabilityCtx) {
        new Chart(probabilityCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'AI Probability (%)',
                    data: probabilitySeries,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.2)',
                    tension: 0.25,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, max: 100 },
                },
            },
        });
    }

    const diameterCtx = document.getElementById('diameterChart');
    if (diameterCtx) {
        new Chart(diameterCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Estimated Nodule Diameter (mm)',
                    data: diameterSeries,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.3)',
                }],
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }

    const growthRateCtx = document.getElementById('growthRateChart');
    if (growthRateCtx) {
        new Chart(growthRateCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Tumor Growth Rate (%)',
                    data: growthRateSeries,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,0.2)',
                    tension: 0.25,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: false },
                },
            },
        });
    }
})();
</script>
@endpush
@endsection
