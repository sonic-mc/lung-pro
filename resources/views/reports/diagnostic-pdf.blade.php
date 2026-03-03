<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic PDF Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .header { border-bottom: 3px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 18px; }
        .hospital { font-size: 20px; font-weight: 700; color: #111827; }
        .tagline { font-size: 12px; color: #4b5563; }
        .section { border: 1px solid #d1d5db; margin-bottom: 14px; }
        .section-title { background: #f3f4f6; padding: 8px 10px; font-weight: 700; }
        .section-body { padding: 10px; }
        .kv { margin: 0 0 4px 0; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th, .table td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        .table th { background: #f9fafb; }
        .footer-note { margin-top: 18px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
@php
    $aiBaseUrl = rtrim(config('services.ai_service.base_url'), '/');
    $modelVisuals = $reportData['modelVisuals'] ?? [];
    $comparisonPanelFile = data_get($modelVisuals, 'comparison_panel');
    $comparisonPanelUrl = is_string($comparisonPanelFile) && $comparisonPanelFile !== '' ? $aiBaseUrl.'/heatmaps/'.$comparisonPanelFile : null;
    $overlayFiles = data_get($modelVisuals, 'overlays', []);
@endphp

<div class="header">
    <div class="hospital">{{ $hospitalName }}</div>
    <div class="tagline">{{ $hospitalTagline }}</div>
</div>

<div class="section">
    <div class="section-title">Patient & Study Information</div>
    <div class="section-body">
        <p class="kv"><strong>Patient:</strong> {{ $prediction->scan->patient->full_name ?? '-' }}</p>
        <p class="kv"><strong>MRN:</strong> {{ $prediction->scan->patient->medical_record_number ?? '-' }}</p>
        <p class="kv"><strong>Modality:</strong> {{ strtoupper($prediction->scan->modality ?? '-') }}</p>
        <p class="kv"><strong>Dataset Source:</strong> {{ $reportData['datasetSource'] ?? 'N/A' }}</p>
        <p class="kv"><strong>Generated At:</strong> {{ now() }}</p>
    </div>
</div>

<div class="section">
    <div class="section-title">Findings Summary</div>
    <div class="section-body">
        <ul>
            @foreach ($reportData['findingsSummary'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>
</div>

<div class="section">
    <div class="section-title">AI Explanation</div>
    <div class="section-body">
        <ul>
            @foreach ($reportData['aiExplanation'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        @if (count($reportData['modelComparisons']) > 0)
            <table class="table">
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
        @endif

        @if ($comparisonPanelUrl || (is_array($overlayFiles) && count($overlayFiles) > 0))
            <p><strong>Model Visual Comparison References</strong></p>
            @if ($comparisonPanelUrl)
                <p class="kv"><strong>Combined panel:</strong> {{ $comparisonPanelUrl }}</p>
            @endif
            @if (is_array($overlayFiles) && count($overlayFiles) > 0)
                <ul>
                    @foreach ($overlayFiles as $model => $filename)
                        <li>{{ strtoupper((string) $model) }} overlay: {{ $aiBaseUrl.'/heatmaps/'.$filename }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</div>

<div class="section">
    <div class="section-title">Recommendation</div>
    <div class="section-body">
        <p>{{ $reportData['recommendation'] }}</p>
    </div>
</div>

<div class="footer-note">
    AI-assisted report for clinical decision support. Final diagnosis remains physician responsibility.
</div>
</body>
</html>

