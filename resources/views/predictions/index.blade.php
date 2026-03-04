@extends('layouts.app')

@section('title', 'Prediction Dashboard')

@push('head')
<style>
    .landing-shell {
        background: linear-gradient(145deg, #f8fbff 0%, #eef4ff 100%);
        border-radius: 1.2rem;
        border: 1px solid #dbe7ff;
        box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .landing-hero {
        background: radial-gradient(circle at 20% 20%, rgba(56, 189, 248, 0.22), rgba(37, 99, 235, 0.08) 40%, transparent 60%),
            linear-gradient(120deg, #ffffff 0%, #f5f9ff 100%);
        border-bottom: 1px solid #dde8ff;
    }

    .kpi-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.9rem;
        background: #fff;
        box-shadow: 0 0.4rem 1rem rgba(15, 23, 42, 0.05);
    }

    .activity-item {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.75rem;
        background: #fff;
    }

    .stat-chip {
        border-radius: 999px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.8rem;
        padding: 0.2rem 0.65rem;
        display: inline-flex;
        align-items: center;
    }

    .lung-3d-wrap {
        position: relative;
        width: 280px;
        height: 280px;
        margin-inline: auto;
        perspective: 900px;
    }

    .lung-glow {
        position: absolute;
        inset: 20px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(34, 211, 238, 0.4) 0%, rgba(56, 189, 248, 0.18) 35%, rgba(2, 132, 199, 0.08) 60%, transparent 72%);
        filter: blur(3px);
        animation: pulseGlow 3.8s ease-in-out infinite;
    }

    .lung-body {
        position: absolute;
        inset: 0;
        transform-style: preserve-3d;
        animation: floatLung 6s ease-in-out infinite;
    }

    .lung-svg {
        filter: drop-shadow(0 8px 16px rgba(14, 116, 144, 0.18));
    }

    .lung-breath {
        transform-origin: 50% 55%;
        animation: breathe 4.8s ease-in-out infinite;
    }

    .bronchial-tree {
        stroke: rgba(255, 255, 255, 0.58);
        stroke-width: 2;
        fill: none;
        stroke-linecap: round;
        opacity: 0.8;
    }

    .lung-highlight {
        mix-blend-mode: screen;
        opacity: 0.62;
    }

    .lung-shadow {
        position: absolute;
        left: 50%;
        bottom: 18px;
        transform: translateX(-50%);
        width: 145px;
        height: 26px;
        border-radius: 50%;
        background: rgba(15, 23, 42, 0.16);
        filter: blur(8px);
        animation: shadowPulse 3.8s ease-in-out infinite;
    }

    .nodule-dot {
        position: absolute;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: radial-gradient(circle at 30% 30%, #fef08a, #f97316);
        box-shadow: 0 0 18px rgba(249, 115, 22, 0.7);
        animation: orbit 5.5s linear infinite;
    }

    .nodule-dot.dot-a { top: 70px; left: 44px; }
    .nodule-dot.dot-b { top: 105px; right: 55px; animation-delay: -1.2s; }
    .nodule-dot.dot-c { bottom: 78px; left: 50%; animation-delay: -2.6s; }

    .trend-chart-wrap {
        position: relative;
        height: 220px;
    }

    @keyframes floatLung {
        0%, 100% { transform: translateY(0) rotateY(-6deg) rotateX(3deg); }
        50% { transform: translateY(-10px) rotateY(6deg) rotateX(-2deg); }
    }

    @keyframes breathe {
        0%, 100% { transform: scale(0.98); }
        50% { transform: scale(1.02); }
    }

    @keyframes pulseGlow {
        0%, 100% { transform: scale(0.95); opacity: 0.65; }
        50% { transform: scale(1.03); opacity: 1; }
    }

    @keyframes orbit {
        0% { transform: translateY(0) scale(0.95); }
        50% { transform: translateY(-8px) scale(1.15); }
        100% { transform: translateY(0) scale(0.95); }
    }

    @keyframes shadowPulse {
        0%, 100% { transform: translateX(-50%) scaleX(0.95); opacity: 0.55; }
        50% { transform: translateX(-50%) scaleX(1.08); opacity: 0.35; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="landing-shell mb-4">
        <div class="landing-hero p-4 p-lg-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="stat-chip">Lung Oncology AI</span>
                        <span class="stat-chip">Clinical Command Center</span>
                    </div>
                    <h1 class="display-6 fw-semibold mb-2">Landing Dashboard Overview</h1>
                    <p class="text-muted mb-4">Unified view of risk screening, model operations, and recent radiology activity for lung cancer decision support.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('scans.create') }}" class="btn btn-primary px-3">New Upload</a>
                        <a href="{{ route('predictions.statistics') }}" class="btn btn-outline-primary px-3">Open Statistics</a>
                        <a href="{{ route('predictions.comparison') }}" class="btn btn-outline-secondary px-3">Model Workspace</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="lung-3d-wrap" aria-hidden="true">
                        <div class="lung-glow"></div>
                        <div class="lung-body">
                            <svg viewBox="0 0 220 240" class="w-100 h-100 lung-svg" role="img" aria-label="Animated 3D lung visual">
                                <defs>
                                    <linearGradient id="lungGradient" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#7dd3fc"/>
                                        <stop offset="45%" stop-color="#22d3ee"/>
                                        <stop offset="100%" stop-color="#2563eb"/>
                                    </linearGradient>
                                    <radialGradient id="lungTexture" cx="35%" cy="30%" r="75%">
                                        <stop offset="0%" stop-color="rgba(255,255,255,0.48)"/>
                                        <stop offset="45%" stop-color="rgba(255,255,255,0.18)"/>
                                        <stop offset="100%" stop-color="rgba(15,23,42,0.12)"/>
                                    </linearGradient>
                                </defs>

                                <g class="lung-breath">
                                    <ellipse cx="110" cy="36" rx="12" ry="14" fill="#0f172a" opacity="0.62"/>
                                    <rect x="103" y="38" width="14" height="40" rx="7" fill="#0f172a" opacity="0.62"/>

                                    <path d="M109 70 C78 70, 55 92, 52 132 C50 165, 69 196, 95 206 C109 212, 115 196, 115 175 L115 81 C115 74, 113 70, 109 70Z" fill="url(#lungGradient)" opacity="0.96"/>
                                    <path d="M111 70 C142 70, 165 92, 168 132 C170 165, 151 196, 125 206 C111 212, 105 196, 105 175 L105 81 C105 74, 107 70, 111 70Z" fill="url(#lungGradient)" opacity="0.96"/>

                                    <path class="lung-highlight" d="M92 88 C72 98, 62 124, 67 155 C72 181, 88 196, 102 199 C102 170,102 138,102 112 C102 98,98 90,92 88Z" fill="url(#lungTexture)"/>
                                    <path class="lung-highlight" d="M128 88 C148 98, 158 124, 153 155 C148 181, 132 196, 118 199 C118 170,118 138,118 112 C118 98,122 90,128 88Z" fill="url(#lungTexture)"/>

                                    <path class="bronchial-tree" d="M110 78 L110 112"/>
                                    <path class="bronchial-tree" d="M110 96 L88 116"/>
                                    <path class="bronchial-tree" d="M110 96 L132 116"/>
                                    <path class="bronchial-tree" d="M88 116 L76 136"/>
                                    <path class="bronchial-tree" d="M88 116 L96 142"/>
                                    <path class="bronchial-tree" d="M132 116 L144 136"/>
                                    <path class="bronchial-tree" d="M132 116 L124 142"/>

                                    <circle cx="88" cy="128" r="6" fill="#f59e0b" opacity="0.95"/>
                                    <circle cx="132" cy="154" r="5" fill="#fb7185" opacity="0.9"/>
                                </g>
                            </svg>
                        </div>
                        <div class="nodule-dot dot-a"></div>
                        <div class="nodule-dot dot-b"></div>
                        <div class="nodule-dot dot-c"></div>
                        <div class="lung-shadow"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 p-lg-5">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="kpi-card p-3 h-100">
                        <div class="text-muted small">Total Predictions</div>
                        <div class="h3 mb-1">{{ number_format($totalPredictions) }}</div>
                        <div class="small text-muted">All processed scans</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="kpi-card p-3 h-100">
                        <div class="text-muted small">Ground Truth Coverage</div>
                        <div class="h3 mb-1 text-success">{{ number_format((float) $groundTruthCoverage, 2) }}%</div>
                        <div class="small text-muted">{{ $groundTruthCount }} confirmed labels</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="kpi-card p-3 h-100">
                        <div class="text-muted small">Predictions (7 Days)</div>
                        <div class="h3 mb-1">{{ number_format($weeklyPredictions) }}</div>
                        <div class="small text-muted">Recent throughput</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="kpi-card p-3 h-100">
                        <div class="text-muted small">Avg. Risk Probability</div>
                        <div class="h3 mb-1 text-primary">{{ number_format((float) $averageRiskPercent, 2) }}%</div>
                        <div class="small text-muted">Across all predictions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-5">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h2 class="h5 mb-1">Recent Activities</h2>
                    <p class="text-muted small mb-0">Latest prediction and review events</p>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    @forelse ($recentActivities as $activity)
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $activity->scan->patient->full_name ?? 'Unknown Patient' }}</div>
                                    <div class="small text-muted">MRN: {{ $activity->scan->patient->medical_record_number ?? '-' }} · {{ strtoupper($activity->scan->modality ?? '-') }}</div>
                                </div>
                                <span class="badge {{ $activity->predicted_label === 'Malignant' ? 'text-bg-danger' : 'text-bg-success' }}">{{ $activity->predicted_label }}</span>
                            </div>
                            <div class="small text-muted mt-2">
                                Model {{ $activity->model_version ?? 'N/A' }} · {{ number_format((float) $activity->probability * 100, 2) }}% · {{ $activity->created_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">No recent activities yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h2 class="h5 mb-1">Statistics Snapshot</h2>
                    <p class="text-muted small mb-0">Distribution by diagnosis, modality, and deployed model usage</p>
                </div>
                <div class="card-body">
                    @php
                        $labelTotal = max($malignantCount + $benignCount, 1);
                        $malignantRate = ($malignantCount / $labelTotal) * 100;
                        $benignRate = ($benignCount / $labelTotal) * 100;
                        $modalityTotal = max($ctCount + $xrayCount, 1);
                        $ctRate = ($ctCount / $modalityTotal) * 100;
                        $xrayRate = ($xrayCount / $modalityTotal) * 100;
                        $modelTotal = max($resnetCount + $densenetCount + $yolov8Count + $kerashfCount + $hybridCount, 1);
                        $resnetRate = ($resnetCount / $modelTotal) * 100;
                        $densenetRate = ($densenetCount / $modelTotal) * 100;
                        $yolov8Rate = ($yolov8Count / $modelTotal) * 100;
                        $kerashfRate = ($kerashfCount / $modelTotal) * 100;
                        $hybridRate = ($hybridCount / $modelTotal) * 100;
                    @endphp

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1"><span>Malignant vs Benign</span><span>{{ number_format($malignantRate, 1) }}% / {{ number_format($benignRate, 1) }}%</span></div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-danger" style="width: {{ $malignantRate }}%"></div>
                            <div class="progress-bar bg-success" style="width: {{ $benignRate }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1"><span>CT vs X-Ray Intake</span><span>{{ number_format($ctRate, 1) }}% / {{ number_format($xrayRate, 1) }}%</span></div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-info" style="width: {{ $ctRate }}%"></div>
                            <div class="progress-bar bg-primary" style="width: {{ $xrayRate }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="small text-muted mb-2">Model Utilization</div>
                        <div class="row g-2">
                            <div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Hybrid</div><div class="fw-semibold">{{ number_format($hybridRate, 1) }}%</div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><div class="small text-muted">ResNet</div><div class="fw-semibold">{{ number_format($resnetRate, 1) }}%</div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><div class="small text-muted">DenseNet</div><div class="fw-semibold">{{ number_format($densenetRate, 1) }}%</div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><div class="small text-muted">YOLOv8</div><div class="fw-semibold">{{ number_format($yolov8Rate, 1) }}%</div></div></div>
                            <div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><div class="small text-muted">KerasHF</div><div class="fw-semibold">{{ number_format($kerashfRate, 1) }}%</div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1">Weekly Trend</h2>
                <p class="text-muted small mb-0">Interactive uploads volume and malignant-rate trend</p>
            </div>
            <span class="stat-chip">Last 7 Days</span>
        </div>
        <div class="card-body">
            <div class="trend-chart-wrap">
                <canvas id="weeklyTrendChart" aria-label="Weekly uploads and malignant rate trend chart"></canvas>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h2 class="h5 mb-1">Recent Cases</h2>
            <p class="text-muted small mb-0">Operational queue with quick actions</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>MRN</th>
                        <th>Modality</th>
                        <th>Mode</th>
                        <th>Model</th>
                        <th>Prediction</th>
                        <th>Probability</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($predictions as $prediction)
                        <tr>
                            <td>{{ $prediction->scan->patient->full_name ?? '-' }}</td>
                            <td>{{ $prediction->scan->patient->medical_record_number ?? '-' }}</td>
                            <td>{{ strtoupper($prediction->scan->modality ?? '-') }}</td>
                            <td>
                                @php
                                    $operatingMode = (string) data_get($prediction->raw_response, 'operating_mode', 'diagnostic');
                                    $operatingModeBadge = $operatingMode === 'screening' ? 'text-bg-warning' : 'text-bg-primary';
                                @endphp
                                <span class="badge {{ $operatingModeBadge }}">{{ ucfirst($operatingMode) }}</span>
                            </td>
                            <td>{{ $prediction->model_version ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $prediction->predicted_label === 'Malignant' ? 'text-bg-danger' : 'text-bg-success' }}">
                                    {{ $prediction->predicted_label }}
                                </span>
                            </td>
                            <td>{{ number_format((float) $prediction->probability * 100, 2) }}%</td>
                            <td class="text-end">
                                <a href="{{ route('predictions.show', $prediction) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('predictions.report', $prediction) }}" class="btn btn-sm btn-outline-primary">Report</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">No predictions available yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        {{ $predictions->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(() => {
    const canvas = document.getElementById('weeklyTrendChart');
    if (!canvas || typeof window.Chart === 'undefined') {
        return;
    }

    const labels = @json($trendLabels);
    const uploads = @json($trendUploads);
    const malignantRate = @json($trendMalignantRate);

    new window.Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Uploads',
                    data: uploads,
                    backgroundColor: 'rgba(37, 99, 235, 0.28)',
                    borderColor: 'rgba(37, 99, 235, 0.85)',
                    borderWidth: 1,
                    borderRadius: 6,
                    yAxisID: 'yUploads',
                },
                {
                    type: 'line',
                    label: 'Malignant Rate (%)',
                    data: malignantRate,
                    borderColor: 'rgba(220, 38, 38, 0.95)',
                    backgroundColor: 'rgba(220, 38, 38, 0.12)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    yAxisID: 'yRate',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                    },
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = context.parsed.y ?? 0;
                            if (context.dataset.label === 'Malignant Rate (%)') {
                                return `${context.dataset.label}: ${Number(value).toFixed(2)}%`;
                            }

                            return `${context.dataset.label}: ${value}`;
                        },
                    },
                },
            },
            scales: {
                yUploads: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.2)',
                    },
                    ticks: {
                        precision: 0,
                    },
                },
                yRate: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback(value) {
                            return `${value}%`;
                        },
                    },
                },
                x: {
                    grid: {
                        display: false,
                    },
                },
            },
        },
    });
})();
</script>
@endpush
