@extends('layouts.app')

@section('title', 'Prediction Details')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Prediction Details</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('predictions.two-pass') }}" class="btn btn-outline-success">Two-Pass Dashboard</a>
            <a href="{{ route('patients.history', $prediction->scan->patient) }}" class="btn btn-outline-primary">Patient History</a>
            <a href="{{ route('predictions.report.pdf', $prediction) }}" class="btn btn-primary">Download PDF Report</a>
            <a href="{{ route('predictions.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            @php
                $aiBaseUrl = rtrim(config('services.ai_service.base_url'), '/');
                $explanationMaps = $prediction->explanation_maps ?? data_get($prediction->raw_response, 'explanation_maps', []);
                $mapUrls = [];

                if (is_array($explanationMaps)) {
                    foreach ($explanationMaps as $key => $filename) {
                        if (is_string($filename) && $filename !== '') {
                            $mapUrls[$key] = $aiBaseUrl.'/heatmaps/'.$filename;
                        }
                    }
                }

                $defaultMapKey = array_key_exists('gradcam', $mapUrls) ? 'gradcam' : array_key_first($mapUrls);
                $defaultMapUrl = $defaultMapKey ? ($mapUrls[$defaultMapKey] ?? null) : null;
                $baseImageUrl = $mapUrls['original'] ?? $defaultMapUrl;
                $modelComparisons = data_get($prediction->raw_response, 'model_comparisons', []);
                $modelVisuals = data_get($prediction->raw_response, 'model_visuals', []);
                $modelOverlayFiles = is_array($modelVisuals) ? data_get($modelVisuals, 'overlays', []) : [];
                $modelComparisonPanelFile = is_array($modelVisuals) ? data_get($modelVisuals, 'comparison_panel') : null;
                $modelOverlayUrls = [];
                foreach (['resnet', 'densenet', 'hybrid', 'yolov8', 'kerashf'] as $modelKey) {
                    $filename = $modelOverlayFiles[$modelKey] ?? null;
                    if (is_string($filename) && $filename !== '') {
                        $modelOverlayUrls[$modelKey] = $aiBaseUrl.'/heatmaps/'.$filename;
                    }
                }
                $modelComparisonPanelUrl = is_string($modelComparisonPanelFile) && $modelComparisonPanelFile !== ''
                    ? $aiBaseUrl.'/heatmaps/'.$modelComparisonPanelFile
                    : null;
                $modelComparisonByKey = [];
                if (is_array($modelComparisons)) {
                    foreach ($modelComparisons as $item) {
                        $key = strtolower((string) data_get($item, 'model', ''));
                        if ($key !== '') {
                            $modelComparisonByKey[$key] = $item;
                        }
                    }
                }
                $ctViewer = $prediction->ct_viewer ?? data_get($prediction->raw_response, 'ct_viewer');
                $ctSliceUrls = [];
                $ctSegUrls = [];
                $ctDepth = 0;

                if (is_array($ctViewer)) {
                    $ctDepth = (int) ($ctViewer['depth'] ?? 0);
                    foreach (($ctViewer['slice_files'] ?? []) as $filename) {
                        if (is_string($filename) && $filename !== '') {
                            $ctSliceUrls[] = $aiBaseUrl.'/heatmaps/'.$filename;
                        }
                    }
                    foreach (($ctViewer['segmentation_files'] ?? []) as $filename) {
                        if (is_string($filename) && $filename !== '') {
                            $ctSegUrls[] = $aiBaseUrl.'/heatmaps/'.$filename;
                        }
                    }

                    if ($ctDepth === 0) {
                        $ctDepth = min(count($ctSliceUrls), count($ctSegUrls));
                    }
                }

                $consensusText = null;
                $consensusClass = 'text-bg-secondary';
                if (is_array($modelComparisons) && count($modelComparisons) > 0) {
                    $resultCounts = [];
                    foreach ($modelComparisons as $comparison) {
                        $result = data_get($comparison, 'result');
                        if (! is_string($result) || $result === '') {
                            continue;
                        }
                        $resultCounts[$result] = ($resultCounts[$result] ?? 0) + 1;
                    }

                    if (! empty($resultCounts)) {
                        arsort($resultCounts);
                        $topCount = (int) reset($resultCounts);
                        $totalCount = (int) array_sum($resultCounts);
                        $consensusText = $topCount.'/'.$totalCount.' agree';
                        $consensusClass = $topCount === $totalCount
                            ? 'text-bg-success'
                            : ($topCount >= 2 ? 'text-bg-warning' : 'text-bg-secondary');
                    }
                }
            @endphp

            <dl class="row mb-0">
                <dt class="col-sm-3">Patient</dt>
                <dd class="col-sm-9">{{ $prediction->scan->patient->full_name ?? '-' }}</dd>

                <dt class="col-sm-3">MRN</dt>
                <dd class="col-sm-9">{{ $prediction->scan->patient->medical_record_number ?? '-' }}</dd>

                <dt class="col-sm-3">Dataset Source</dt>
                <dd class="col-sm-9">{{ $prediction->scan->dataset_source ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Prediction</dt>
                <dd class="col-sm-9">{{ $prediction->predicted_label }}</dd>

                <dt class="col-sm-3">Ground Truth</dt>
                <dd class="col-sm-9">
                    {{ $prediction->ground_truth_label ?? 'N/A' }}
                    @if ($prediction->ground_truth_source)
                        <span class="text-muted">({{ $prediction->ground_truth_source }})</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Probability</dt>
                <dd class="col-sm-9">{{ number_format($prediction->probability * 100, 2) }}%</dd>

                <dt class="col-sm-3">Model Used</dt>
                <dd class="col-sm-9">{{ $prediction->model_version ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Finding Location</dt>
                <dd class="col-sm-9">{{ $prediction->finding_location ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Severity Score</dt>
                <dd class="col-sm-9">
                    @if (! is_null($prediction->severity_score))
                        {{ number_format($prediction->severity_score, 2) }}/100
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">Confidence Band</dt>
                <dd class="col-sm-9">{{ $prediction->confidence_band ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Cancer Stage</dt>
                <dd class="col-sm-9">{{ $prediction->cancer_stage ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Confidence Reasoning</dt>
                <dd class="col-sm-9">{{ $prediction->confidence_reasoning ?? 'N/A' }}</dd>

                <dt class="col-sm-3">Tumor Diameter</dt>
                <dd class="col-sm-9">{{ !is_null($prediction->nodule_diameter_mm) ? number_format($prediction->nodule_diameter_mm, 2).' mm' : 'N/A' }}</dd>

                <dt class="col-sm-3">Tumor Area</dt>
                <dd class="col-sm-9">{{ !is_null($prediction->tumor_area_mm2) ? number_format($prediction->tumor_area_mm2, 2).' mm²' : 'N/A' }}</dd>

                <dt class="col-sm-3">Tumor Volume</dt>
                <dd class="col-sm-9">{{ !is_null($prediction->tumor_volume_mm3) ? number_format($prediction->tumor_volume_mm3, 2).' mm³' : 'N/A' }}</dd>

                <dt class="col-sm-3">Growth Rate</dt>
                <dd class="col-sm-9">{{ !is_null($prediction->growth_rate_percent) ? number_format($prediction->growth_rate_percent, 2).'%' : 'N/A' }}</dd>

                <dt class="col-sm-3">Region Confidence</dt>
                <dd class="col-sm-9">
                    @if (! is_null($prediction->region_confidence_score))
                        {{ number_format($prediction->region_confidence_score, 2) }}/100
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">Multi-Model Comparison</dt>
                <dd class="col-sm-9">
                    @if (is_array($modelComparisons) && count($modelComparisons) > 0)
                        <div class="mb-2 d-flex align-items-center gap-2">
                            <span class="small text-muted">Consensus:</span>
                            <span class="badge {{ $consensusClass }}">{{ $consensusText ?? 'N/A' }}</span>
                        </div>
                        <div class="table-responsive" style="max-width: 720px;">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Model</th>
                                        <th>Result</th>
                                        <th>Probability</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($modelComparisons as $item)
                                        <tr>
                                            <td>{{ data_get($item, 'model', 'N/A') }}</td>
                                            <td>{{ data_get($item, 'result', 'N/A') }}</td>
                                            <td>{{ number_format(((float) data_get($item, 'probability', 0)) * 100, 2) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">Model Visual Comparison</dt>
                <dd class="col-sm-9">
                    @if (! empty($modelOverlayUrls))
                        @if ($modelComparisonPanelUrl)
                            <div class="mb-3">
                                <img src="{{ $modelComparisonPanelUrl }}" alt="Combined Model Comparison Panel" class="img-fluid rounded border" style="max-width: 100%;">
                            </div>
                        @endif

                        <div class="row g-3" style="max-width: 1200px;">
                            @foreach (['resnet' => 'ResNet', 'densenet' => 'DenseNet', 'hybrid' => 'Hybrid', 'yolov8' => 'YOLOv8', 'kerashf' => 'KerasHF'] as $modelKey => $modelTitle)
                                @if (! empty($modelOverlayUrls[$modelKey]))
                                    @php
                                        $modelData = $modelComparisonByKey[$modelKey] ?? [];
                                        $modelResult = data_get($modelData, 'result', 'N/A');
                                        $modelProbability = (float) data_get($modelData, 'probability', 0);
                                        $confidenceMargin = data_get($modelData, 'confidence_margin');
                                    @endphp
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100 bg-white">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong>{{ $modelTitle }}</strong>
                                                <span class="badge {{ $modelResult === 'Malignant' ? 'text-bg-danger' : 'text-bg-success' }}">{{ $modelResult }}</span>
                                            </div>
                                            <div class="small text-muted mb-2">
                                                Confidence: {{ number_format($modelProbability * 100, 2) }}%
                                                @if (! is_null($confidenceMargin))
                                                    · Margin: {{ number_format(((float) $confidenceMargin) * 100, 2) }}%
                                                @endif
                                            </div>
                                            <img src="{{ $modelOverlayUrls[$modelKey] }}" alt="{{ $modelTitle }} Overlay" class="img-fluid rounded border" style="width:100%; max-height: 360px; object-fit: contain;">
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">Heatmap</dt>
                <dd class="col-sm-9">
                    @if ($prediction->heatmap_path)
                        @php
                            $heatmapUrl = $aiBaseUrl.'/heatmaps/'.$prediction->heatmap_path;
                        @endphp
                        <p class="mb-2">
                            <a href="{{ $heatmapUrl }}" target="_blank" rel="noopener">{{ $prediction->heatmap_path }}</a>
                        </p>
                        <img id="heatmap-image" src="{{ $heatmapUrl }}" alt="Grad-CAM Heatmap" class="img-fluid rounded border" style="max-height: 420px;">
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">Multiple XAI Maps</dt>
                <dd class="col-sm-9">
                    @if (! empty($mapUrls) && $baseImageUrl && $defaultMapUrl)
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-4">
                                <label for="xai-map-select" class="form-label mb-1">Explanation Map</label>
                                <select id="xai-map-select" class="form-select">
                                    @foreach ($mapUrls as $key => $url)
                                        <option value="{{ $key }}" {{ $key === $defaultMapKey ? 'selected' : '' }}>
                                            {{ match($key) {
                                                'gradcam' => 'Grad-CAM',
                                                'gradcampp' => 'Grad-CAM++',
                                                'scorecam' => 'Score-CAM',
                                                'saliency' => 'Saliency Map',
                                                'boundary' => 'Nodule Boundary',
                                                'original' => 'Original',
                                                default => ucfirst($key),
                                            } }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="xai-opacity" class="form-label mb-1">Attention Overlay Slider</label>
                                <input id="xai-opacity" type="range" min="0" max="100" value="55" class="form-range">
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted">Overlay Opacity: <span id="xai-opacity-value">55%</span></div>
                            </div>
                        </div>

                        <div id="xai-viewer" class="rounded border overflow-hidden" style="position: relative; width: min(100%, 720px);">
                            <img id="xai-base" src="{{ $baseImageUrl }}" alt="Base Map" class="img-fluid d-block w-100">
                            <img id="xai-overlay" src="{{ $defaultMapUrl }}" alt="Overlay Map" style="position:absolute; inset:0; width:100%; height:100%; object-fit:contain; opacity:0.55;">
                        </div>
                        <div class="small text-muted mt-2">Includes Grad-CAM, Grad-CAM++, Score-CAM, Saliency, and Boundary maps.</div>

                        <div class="mt-3 border rounded p-3 bg-white" style="max-width: 1000px;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <strong>Radiologist Comparison Viewer (Synchronized)</strong>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" id="sync-zoom-out" class="btn btn-sm btn-outline-secondary">-</button>
                                    <button type="button" id="sync-zoom-reset" class="btn btn-sm btn-outline-secondary">Reset</button>
                                    <button type="button" id="sync-zoom-in" class="btn btn-sm btn-outline-secondary">+</button>
                                    <span class="small text-muted">Zoom: <span id="sync-zoom-value">100%</span></span>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="small text-muted mb-1">Original</div>
                                    <div id="sync-pane-left" class="border rounded overflow-hidden" style="height: 380px; position: relative; background: #000; cursor: grab; touch-action: none;">
                                        <img id="sync-image-left" src="{{ $baseImageUrl }}" alt="Original Sync" style="width: 100%; height: 100%; object-fit: contain; user-select: none; pointer-events: none;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted mb-1"><span id="sync-map-label">Selected Explanation</span></div>
                                    <div id="sync-pane-right" class="border rounded overflow-hidden" style="height: 380px; position: relative; background: #000; cursor: grab; touch-action: none;">
                                        <img id="sync-image-right" src="{{ $defaultMapUrl }}" alt="Explanation Sync" style="width: 100%; height: 100%; object-fit: contain; user-select: none; pointer-events: none;">
                                    </div>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">Drag either pane to pan both. Scroll mouse wheel on either pane to zoom both.</div>
                        </div>
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">3D CT Viewer</dt>
                <dd class="col-sm-9">
                    @if (($prediction->scan->modality ?? null) === 'ct' && $ctDepth > 0 && count($ctSliceUrls) > 0 && count($ctSegUrls) > 0)
                        <div class="border rounded p-3 bg-white" style="max-width: 980px;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <strong>CT Slice Viewer with Segmentation Overlay</strong>
                                <div class="small text-muted">Nodule Diameter: {{ !is_null($prediction->nodule_diameter_mm) ? number_format($prediction->nodule_diameter_mm, 2).' mm' : 'N/A' }}</div>
                            </div>

                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-8">
                                    <label for="ct-slice-range" class="form-label mb-1">Slice Index</label>
                                    <input id="ct-slice-range" type="range" min="0" max="{{ max($ctDepth - 1, 0) }}" value="0" class="form-range">
                                </div>
                                <div class="col-md-4">
                                    <label for="ct-seg-opacity" class="form-label mb-1">Segmentation Opacity</label>
                                    <input id="ct-seg-opacity" type="range" min="0" max="100" value="60" class="form-range">
                                </div>
                            </div>

                            <div class="small text-muted mb-2">Slice: <span id="ct-slice-value">1</span> / {{ $ctDepth }}</div>

                            <div id="ct-viewer" class="rounded border overflow-hidden" style="position: relative; width: min(100%, 720px); background: #000;">
                                <img id="ct-base" src="{{ $ctSliceUrls[0] }}" alt="CT Slice" class="img-fluid d-block w-100">
                                <img id="ct-seg" src="{{ $ctSegUrls[0] }}" alt="CT Segmentation" style="position:absolute; inset:0; width:100%; height:100%; object-fit:contain; opacity:0.6;">
                            </div>
                        </div>
                    @else
                        N/A
                    @endif
                </dd>

                <dt class="col-sm-3">Evaluated At</dt>
                <dd class="col-sm-9">{{ $prediction->evaluated_at }}</dd>
            </dl>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Ground Truth Label</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('predictions.ground-truth.save', $prediction) }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="ground_truth_label" class="form-label">Confirmed Label</label>
                        <select id="ground_truth_label" name="ground_truth_label" class="form-select" required>
                            <option value="Malignant" @selected(old('ground_truth_label', $prediction->ground_truth_label) === 'Malignant')>Malignant</option>
                            <option value="Benign" @selected(old('ground_truth_label', $prediction->ground_truth_label) === 'Benign')>Benign</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="ground_truth_source" class="form-label">Source</label>
                        <input id="ground_truth_source" type="text" name="ground_truth_source" class="form-control" value="{{ old('ground_truth_source', $prediction->ground_truth_source) }}" placeholder="e.g., Histopathology, Consensus board, Follow-up CT">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-outline-dark">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="radiologist-feedback">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Radiologist Feedback</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('predictions.feedback', $prediction) }}" id="feedback-form">
                @csrf
                <div class="mb-3">
                    <label class="form-label d-block" for="decision_accept">AI Prediction Decision</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="decision" id="decision_accept" value="accept"
                            {{ old('decision', $prediction->feedback?->decision) === 'accept' ? 'checked' : '' }} required>
                        <label class="form-check-label" for="decision_accept">Accept</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="decision" id="decision_reject" value="reject"
                            {{ old('decision', $prediction->feedback?->decision) === 'reject' ? 'checked' : '' }} required>
                        <label class="form-check-label" for="decision_reject">Reject</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="annotation-canvas">Manual Annotation Tools</label>
                    @if ($prediction->heatmap_path)
                        <div class="d-flex gap-2 mb-2 align-items-center">
                            <label for="pen-color" class="form-label mb-0">Color</label>
                            <input id="pen-color" type="color" value="#ff0000" class="form-control form-control-color">

                            <label for="pen-size" class="form-label mb-0">Size</label>
                            <input id="pen-size" type="range" min="1" max="12" value="3" class="form-range" style="max-width: 180px;">

                            <button type="button" id="clear-annotations" class="btn btn-sm btn-outline-danger">Clear</button>
                        </div>

                        <div id="annotation-stage" style="position: relative; width: fit-content; max-width: 100%;">
                            <img id="annotation-image" src="{{ $heatmapUrl }}" alt="Annotatable Heatmap" class="img-fluid rounded border">
                            <canvas id="annotation-canvas" style="position: absolute; left: 0; top: 0; cursor: crosshair;"></canvas>
                        </div>
                    @else
                        <p class="text-muted mb-0">Heatmap unavailable for annotation.</p>
                    @endif
                </div>

                <input type="hidden" id="annotations" name="annotations" value="{{ old('annotations') }}">

                <div class="mb-3">
                    <label for="review_comment" class="form-label">Review Note</label>
                    <textarea id="review_comment" name="review_comment" class="form-control" rows="3" placeholder="Clinical rationale for accept/reject...">{{ old('review_comment', $prediction->feedback?->review_comment) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Feedback</button>
            </form>
        </div>
    </div>

    @php
        $activeTwoPassReview = $prediction->twoPassReviews->firstWhere('reviewer_id', auth()->id())
            ?? $prediction->twoPassReviews->first();
    @endphp

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Radiologist Two-Pass Assessment</h2>
            <a href="{{ route('predictions.two-pass') }}" class="btn btn-sm btn-outline-success">Open Dashboard</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('predictions.two-pass.save', $prediction) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="baseline_label" class="form-label">Baseline Interpretation (Without AI)</label>
                        <select id="baseline_label" name="baseline_label" class="form-select" required>
                            <option value="Malignant" @selected(old('baseline_label', $activeTwoPassReview?->baseline_label) === 'Malignant')>Malignant</option>
                            <option value="Benign" @selected(old('baseline_label', $activeTwoPassReview?->baseline_label) === 'Benign')>Benign</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="baseline_confidence" class="form-label">Baseline Confidence (%)</label>
                        <input id="baseline_confidence" type="number" step="0.01" min="0" max="100" name="baseline_confidence" class="form-control" value="{{ old('baseline_confidence', $activeTwoPassReview?->baseline_confidence) }}" placeholder="0 - 100">
                    </div>
                    <div class="col-md-6">
                        <label for="baseline_time_seconds" class="form-label">Baseline Reading Time (seconds)</label>
                        <input id="baseline_time_seconds" type="number" step="1" min="1" max="7200" name="baseline_time_seconds" class="form-control" value="{{ old('baseline_time_seconds', $activeTwoPassReview?->baseline_time_seconds) }}" placeholder="e.g., 95">
                    </div>
                    <div class="col-md-6">
                        <label for="assisted_label" class="form-label">AI-Assisted Interpretation</label>
                        <select id="assisted_label" name="assisted_label" class="form-select" required>
                            <option value="Malignant" @selected(old('assisted_label', $activeTwoPassReview?->assisted_label) === 'Malignant')>Malignant</option>
                            <option value="Benign" @selected(old('assisted_label', $activeTwoPassReview?->assisted_label) === 'Benign')>Benign</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="assisted_confidence" class="form-label">AI-Assisted Confidence (%)</label>
                        <input id="assisted_confidence" type="number" step="0.01" min="0" max="100" name="assisted_confidence" class="form-control" value="{{ old('assisted_confidence', $activeTwoPassReview?->assisted_confidence) }}" placeholder="0 - 100">
                    </div>
                    <div class="col-md-6">
                        <label for="assisted_time_seconds" class="form-label">AI-Assisted Reading Time (seconds)</label>
                        <input id="assisted_time_seconds" type="number" step="1" min="1" max="7200" name="assisted_time_seconds" class="form-control" value="{{ old('assisted_time_seconds', $activeTwoPassReview?->assisted_time_seconds) }}" placeholder="e.g., 61">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="overlooked_nodule_recovered" name="overlooked_nodule_recovered" @checked(old('overlooked_nodule_recovered', $activeTwoPassReview?->overlooked_nodule_recovered))>
                            <label class="form-check-label" for="overlooked_nodule_recovered">
                                AI assistance recovered a previously overlooked suspicious nodule finding
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="two_pass_notes" class="form-label">Notes</label>
                        <textarea id="two_pass_notes" name="notes" rows="3" class="form-control" placeholder="Brief rationale for interpretation shift, if any.">{{ old('notes', $activeTwoPassReview?->notes) }}</textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">Save Two-Pass Review</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Comments</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('predictions.comments', $prediction) }}" class="mb-4">
                @csrf
                <label for="comment" class="form-label">Add Comment</label>
                <textarea id="comment" name="comment" class="form-control mb-2" rows="3" placeholder="Write discussion notes..." required>{{ old('comment') }}</textarea>
                <button type="submit" class="btn btn-outline-primary">Post Comment</button>
            </form>

            <div>
                @forelse ($prediction->comments as $comment)
                    <div class="border rounded p-3 mb-2 bg-white">
                        <div class="small text-muted mb-1">
                            {{ $comment->user?->name ?? 'Radiology Team' }} · {{ $comment->created_at }}
                        </div>
                        <div>{{ $comment->comment }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No comments yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const image = document.getElementById('annotation-image');
    const canvas = document.getElementById('annotation-canvas');
    const hidden = document.getElementById('annotations');
    const colorInput = document.getElementById('pen-color');
    const sizeInput = document.getElementById('pen-size');
    const clearButton = document.getElementById('clear-annotations');

    if (!image || !canvas || !hidden || !colorInput || !sizeInput || !clearButton) {
        return;
    }

    const ctx = canvas.getContext('2d');
    let drawing = false;
    let currentStroke = null;
    let strokes = [];

    const resizeCanvas = () => {
        const rect = image.getBoundingClientRect();
        canvas.width = Math.round(rect.width);
        canvas.height = Math.round(rect.height);
        canvas.style.width = `${Math.round(rect.width)}px`;
        canvas.style.height = `${Math.round(rect.height)}px`;
        redraw();
    };

    const getPos = (event) => {
        const rect = canvas.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    };

    const redraw = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const stroke of strokes) {
            if (!stroke.points.length) continue;
            ctx.strokeStyle = stroke.color;
            ctx.lineWidth = stroke.size;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.beginPath();
            ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
            for (let i = 1; i < stroke.points.length; i++) {
                ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
            }
            ctx.stroke();
        }
        hidden.value = JSON.stringify(strokes);
    };

    const start = (event) => {
        drawing = true;
        const point = getPos(event);
        currentStroke = {
            color: colorInput.value,
            size: Number(sizeInput.value),
            points: [point],
        };
        strokes.push(currentStroke);
        redraw();
    };

    const move = (event) => {
        if (!drawing || !currentStroke) return;
        currentStroke.points.push(getPos(event));
        redraw();
    };

    const stop = () => {
        drawing = false;
        currentStroke = null;
        redraw();
    };

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointerleave', stop);

    clearButton.addEventListener('click', () => {
        strokes = [];
        redraw();
    });

    const existingAnnotations = @json($prediction->feedback?->annotations);

    if (hidden.value) {
        try {
            const parsed = JSON.parse(hidden.value);
            if (Array.isArray(parsed)) {
                strokes = parsed;
            }
        } catch (error) {
            strokes = [];
        }
    } else if (Array.isArray(existingAnnotations)) {
        strokes = existingAnnotations;
    }

    if (image.complete) {
        resizeCanvas();
    } else {
        image.addEventListener('load', resizeCanvas, { once: true });
    }
    window.addEventListener('resize', resizeCanvas);
})();
</script>

<script>
(() => {
    const mapUrls = @json($mapUrls);
    const mapSelect = document.getElementById('xai-map-select');
    const opacitySlider = document.getElementById('xai-opacity');
    const opacityValue = document.getElementById('xai-opacity-value');
    const overlay = document.getElementById('xai-overlay');
    const syncImageRight = document.getElementById('sync-image-right');
    const syncMapLabel = document.getElementById('sync-map-label');

    if (!mapSelect || !opacitySlider || !opacityValue || !overlay) {
        return;
    }

    const updateOverlay = () => {
        const key = mapSelect.value;
        const url = mapUrls[key] || mapUrls['gradcam'] || mapUrls[Object.keys(mapUrls)[0]];
        const opacity = Number(opacitySlider.value) / 100;
        overlay.src = url;
        overlay.style.opacity = key === 'original' ? '0' : `${opacity}`;
        opacityValue.textContent = `${opacitySlider.value}%`;

        if (syncImageRight) {
            syncImageRight.src = url;
        }

        if (syncMapLabel) {
            const selectedText = mapSelect.options[mapSelect.selectedIndex]?.text || 'Selected Explanation';
            syncMapLabel.textContent = selectedText;
        }
    };

    mapSelect.addEventListener('change', updateOverlay);
    opacitySlider.addEventListener('input', updateOverlay);
    updateOverlay();
})();
</script>

<script>
(() => {
    const leftPane = document.getElementById('sync-pane-left');
    const rightPane = document.getElementById('sync-pane-right');
    const leftImage = document.getElementById('sync-image-left');
    const rightImage = document.getElementById('sync-image-right');
    const zoomInButton = document.getElementById('sync-zoom-in');
    const zoomOutButton = document.getElementById('sync-zoom-out');
    const zoomResetButton = document.getElementById('sync-zoom-reset');
    const zoomValue = document.getElementById('sync-zoom-value');

    if (!leftPane || !rightPane || !leftImage || !rightImage || !zoomInButton || !zoomOutButton || !zoomResetButton || !zoomValue) {
        return;
    }

    const state = {
        scale: 1,
        minScale: 1,
        maxScale: 6,
        translateX: 0,
        translateY: 0,
        dragging: false,
        startX: 0,
        startY: 0,
    };

    const images = [leftImage, rightImage];
    const panes = [leftPane, rightPane];

    const applyTransform = () => {
        const transformValue = `translate(${state.translateX}px, ${state.translateY}px) scale(${state.scale})`;
        images.forEach((image) => {
            image.style.transformOrigin = 'center center';
            image.style.transform = transformValue;
        });

        zoomValue.textContent = `${Math.round(state.scale * 100)}%`;
    };

    const startDrag = (event) => {
        state.dragging = true;
        state.startX = event.clientX - state.translateX;
        state.startY = event.clientY - state.translateY;
        panes.forEach((pane) => pane.style.cursor = 'grabbing');
    };

    const onDrag = (event) => {
        if (!state.dragging) {
            return;
        }

        state.translateX = event.clientX - state.startX;
        state.translateY = event.clientY - state.startY;
        applyTransform();
    };

    const stopDrag = () => {
        state.dragging = false;
        panes.forEach((pane) => pane.style.cursor = 'grab');
    };

    const adjustScale = (factor) => {
        state.scale = Math.max(state.minScale, Math.min(state.maxScale, state.scale * factor));
        applyTransform();
    };

    const resetView = () => {
        state.scale = 1;
        state.translateX = 0;
        state.translateY = 0;
        applyTransform();
    };

    panes.forEach((pane) => {
        pane.addEventListener('pointerdown', startDrag);
        pane.addEventListener('pointermove', onDrag);
        pane.addEventListener('pointerup', stopDrag);
        pane.addEventListener('pointerleave', stopDrag);
        pane.addEventListener('pointercancel', stopDrag);

        pane.addEventListener('wheel', (event) => {
            event.preventDefault();
            adjustScale(event.deltaY < 0 ? 1.1 : 0.9);
        }, { passive: false });
    });

    zoomInButton.addEventListener('click', () => adjustScale(1.15));
    zoomOutButton.addEventListener('click', () => adjustScale(0.87));
    zoomResetButton.addEventListener('click', resetView);

    applyTransform();
})();
</script>

<script>
(() => {
    const sliceUrls = @json($ctSliceUrls);
    const segUrls = @json($ctSegUrls);
    const sliceSlider = document.getElementById('ct-slice-range');
    const opacitySlider = document.getElementById('ct-seg-opacity');
    const baseImage = document.getElementById('ct-base');
    const segImage = document.getElementById('ct-seg');
    const sliceValue = document.getElementById('ct-slice-value');

    if (!sliceSlider || !opacitySlider || !baseImage || !segImage || !sliceValue || !Array.isArray(sliceUrls) || !Array.isArray(segUrls)) {
        return;
    }

    const maxIndex = Math.min(sliceUrls.length, segUrls.length) - 1;

    const renderSlice = () => {
        const index = Math.max(0, Math.min(maxIndex, Number(sliceSlider.value)));
        if (sliceUrls[index]) {
            baseImage.src = sliceUrls[index];
        }
        if (segUrls[index]) {
            segImage.src = segUrls[index];
        }
        sliceValue.textContent = `${index + 1}`;
    };

    const renderOpacity = () => {
        segImage.style.opacity = `${Math.max(0, Math.min(100, Number(opacitySlider.value))) / 100}`;
    };

    sliceSlider.max = String(Math.max(maxIndex, 0));
    sliceSlider.addEventListener('input', renderSlice);
    opacitySlider.addEventListener('input', renderOpacity);

    renderSlice();
    renderOpacity();
})();
</script>
@endpush
@endsection
