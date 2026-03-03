@extends('layouts.app')

@section('title', 'Diagnostic Report')

@section('content')
<div class="container-fluid px-0">
    @php
        $aiBaseUrl = rtrim(config('services.ai_service.base_url'), '/');
        $modelVisuals = $reportData['modelVisuals'] ?? [];
        $comparisonPanelFile = data_get($modelVisuals, 'comparison_panel');
        $comparisonPanelUrl = is_string($comparisonPanelFile) && $comparisonPanelFile !== '' ? $aiBaseUrl.'/heatmaps/'.$comparisonPanelFile : null;
        $overlayFiles = data_get($modelVisuals, 'overlays', []);
        $overlayUrls = [];
        if (is_array($overlayFiles)) {
            foreach ($overlayFiles as $model => $filename) {
                if (is_string($filename) && $filename !== '') {
                    $overlayUrls[$model] = $aiBaseUrl.'/heatmaps/'.$filename;
                }
            }
        }
    @endphp

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">{{ env('HOSPITAL_NAME', 'LungCare Medical Center') }}</h1>
            <p class="text-muted mb-0">{{ env('HOSPITAL_TAGLINE', 'AI-Assisted Thoracic Imaging Unit') }}</p>
        </div>
        <a href="{{ route('predictions.report.pdf', $prediction) }}" class="btn btn-primary">Download PDF Diagnostic Report</a>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Patient & Study Information</strong></div>
        <div class="card-body">
            <p class="mb-1"><strong>Patient:</strong> {{ $prediction->scan->patient->full_name ?? '-' }}</p>
            <p class="mb-1"><strong>MRN:</strong> {{ $prediction->scan->patient->medical_record_number ?? '-' }}</p>
            <p class="mb-1"><strong>Modality:</strong> {{ strtoupper($prediction->scan->modality ?? '-') }}</p>
            <p class="mb-1"><strong>Dataset Source:</strong> {{ $reportData['datasetSource'] ?? 'N/A' }}</p>
            <p class="mb-0"><strong>Generated At:</strong> {{ now() }}</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Findings Summary</strong></div>
        <div class="card-body">
            <ul class="mb-0">
                @foreach ($reportData['findingsSummary'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>AI Explanation</strong></div>
        <div class="card-body">
            <ul class="mb-2">
                @foreach ($reportData['aiExplanation'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            @if (count($reportData['modelComparisons']) > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                        <tr><th>Model</th><th>Result</th><th>Probability</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($reportData['modelComparisons'] as $row)
                            <tr>
                                <td>{{ data_get($row, 'model', 'N/A') }}</td>
                                <td>{{ data_get($row, 'result', 'N/A') }}</td>
                                <td>{{ number_format(((float) data_get($row, 'probability', 0)) * 100, 2) }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($comparisonPanelUrl || count($overlayUrls) > 0)
                <hr>
                <p class="mb-2"><strong>Model Visual Comparison Assets</strong></p>

                @if ($comparisonPanelUrl)
                    <p class="mb-2">
                        <a href="{{ $comparisonPanelUrl }}" target="_blank" rel="noopener">Open Combined Comparison Panel</a>
                    </p>
                    <img src="{{ $comparisonPanelUrl }}" alt="Model Comparison Panel" class="img-fluid rounded border mb-3" style="max-width: 100%;">
                @endif

                @if (count($overlayUrls) > 0)
                    <ul class="mb-0">
                        @foreach ($overlayUrls as $model => $url)
                            <li><a href="{{ $url }}" target="_blank" rel="noopener">{{ strtoupper($model) }} overlay</a></li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Recommendation</strong></div>
        <div class="card-body">
            <p class="mb-0">{{ $reportData['recommendation'] }}</p>
        </div>
    </div>

    <p class="small text-muted mb-0">This report is AI-assisted and must be reviewed and signed by a licensed radiologist.</p>
</div>
@endsection
